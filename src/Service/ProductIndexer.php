<?php

namespace App\Service;

use App\Entity\Product;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class ProductIndexer
{
    public const WRITE_ALIAS = 'products_write';
    public const READ_ALIAS = 'products_read';
    public const INDEX_PREFIX = 'products_v';

    private const MAPPING = [
        'settings' => [
            'analysis' => [
                'analyzer' => [
                    'autocomplete' => [
                        'tokenizer' => 'standard',
                        // stop pred ngram — nechceme indexovať "a", "na", "pre"...
                        'filter' => ['lowercase', 'asciifolding', 'slovak_stop', 'autocomplete_filter'],
                    ],
                    'autocomplete_search' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding', 'slovak_stop'],
                    ],
                ],
                'filter' => [
                    'slovak_stop' => [
                        'type'      => 'stop',
                        'stopwords' => [
                            'a', 'aj', 'ak', 'ale', 'alebo', 'ani', 'ako', 'az', 'ba',
                            'bez', 'by', 'cez', 'co', 'ci', 'do', 'ho', 'ich', 'im',
                            'je', 'jeho', 'jej', 'ju', 'k', 'ked', 'kde', 'kto',
                            'ktory', 'lebo', 'na', 'nad', 'nie', 'no', 'od', 'po',
                            'pod', 'pre', 'pri', 's', 'sa', 'si', 'so', 'su', 'tam',
                            'ten', 'to', 'tu', 'u', 'v', 'viac', 'vo', 'ze',
                        ],
                    ],
                    'autocomplete_filter' => [
                        'type'     => 'edge_ngram',
                        'min_gram' => 2,
                        'max_gram' => 20,
                    ],
                ],
            ],
        ],
        'mappings' => [
            'properties' => [
                'id'          => ['type' => 'integer'],
                'name'        => [
                    'type'     => 'text',
                    'analyzer' => 'autocomplete',         // ← pri indexovaní
                    'search_analyzer' => 'autocomplete_search', // ← pri hľadaní
                    'fields'   => ['keyword' => ['type' => 'keyword']],
                ],
                'description' => ['type' => 'text', 'analyzer' => 'autocomplete_search'],
                'price'       => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'createdAt'   => ['type' => 'date'],
            ],
        ],
    ];


    public function __construct(
        private readonly Client $elasticsearchClient,
    ) {
    }

    public function indexProduct(Product $product): void
    {
        $this->elasticsearchClient->index([
            'index' => self::WRITE_ALIAS,
            'id' => $product->getId(),
            'body' => $this->toDocument($product),
        ]);
    }

    public function removeProduct(int $id): void
    {
        try {
            $this->elasticsearchClient->delete([
                'index' => self::WRITE_ALIAS,
                'id' => $id,
            ]);
        } catch (ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    public function search(string $query, int $size = 10): array
    {
        $response = $this->elasticsearchClient->search([
            'index' => self::READ_ALIAS,
            'body' => [
                'size' => $size,
                'query' => [
                    'multi_match' => [
                        'query'     => $query,
                        'fields'    => ['name^2', 'description'],
                        'fuzziness' => 'AUTO', // 0 chýb pre 1-2 znaky, 1 pre 3-5, 2 pre 6+
                    ],
                ],
            ],
        ]);

        $hits = $response['hits']['hits'] ?? [];

        return array_map(static fn(array $hit) => $hit['_source'], $hits);
    }

    public function createInitialIndex(): string
    {
        $aliasesExist = $this->aliasExists(self::READ_ALIAS) || $this->aliasExists(self::WRITE_ALIAS);

        if ($aliasesExist) {
            $existing = $this->elasticsearchClient->indices()->getAlias(['name' => self::READ_ALIAS]);
            return array_key_first($existing->asArray());
        }

        $indexName = self::INDEX_PREFIX . time();
        $this->createIndex($indexName);

        $this->elasticsearchClient->indices()->putAlias([
            'index' => $indexName,
            'name' => self::WRITE_ALIAS,
        ]);
        $this->elasticsearchClient->indices()->putAlias([
            'index' => $indexName,
            'name' => self::READ_ALIAS,
        ]);

        return $indexName;
    }

    public function reindex(iterable $products): \Generator
    {
        $newIndexName = self::INDEX_PREFIX . time();
        $this->createIndex($newIndexName);

        $oldIndexName = $this->getCurrentIndexName();

        $bulk = [];
        $count = 0;

        foreach ($products as $product) {
            $bulk[] = ['index' => ['_index' => $newIndexName, '_id' => $product->getId()]];
            $bulk[] = $this->toDocument($product);
            ++$count;

            if ($count % 100 === 0) {
                $this->sendBulk($bulk);
                $bulk = [];
            }

            yield $product;
        }

        if ($bulk !== []) {
            $this->sendBulk($bulk);
        }

        $actions = [
            ['add' => ['index' => $newIndexName, 'alias' => self::WRITE_ALIAS]],
            ['add' => ['index' => $newIndexName, 'alias' => self::READ_ALIAS]],
        ];

        if ($oldIndexName !== null) {
            $actions[] = ['remove' => ['index' => $oldIndexName, 'alias' => self::WRITE_ALIAS]];
            $actions[] = ['remove' => ['index' => $oldIndexName, 'alias' => self::READ_ALIAS]];
        }

        $this->elasticsearchClient->indices()->updateAliases(['body' => ['actions' => $actions]]);

        if ($oldIndexName !== null) {
            $this->elasticsearchClient->indices()->delete(['index' => $oldIndexName]);
        }
    }

    private function createIndex(string $name): void
    {
        $this->elasticsearchClient->indices()->create([
            'index' => $name,
            'body' => self::MAPPING,
        ]);
    }

    private function aliasExists(string $alias): bool
    {
        return $this->elasticsearchClient->indices()->existsAlias(['name' => $alias])->asBool();
    }

    private function getCurrentIndexName(): ?string
    {
        if (!$this->aliasExists(self::WRITE_ALIAS)) {
            return null;
        }

        $response = $this->elasticsearchClient->indices()->getAlias(['name' => self::WRITE_ALIAS]);

        return array_key_first($response->asArray());
    }

    private function sendBulk(array $body): void
    {
        $this->elasticsearchClient->bulk(['body' => $body]);
    }

    private function toDocument(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'createdAt' => $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
