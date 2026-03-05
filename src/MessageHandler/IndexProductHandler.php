<?php

namespace App\MessageHandler;

use App\Message\IndexProductMessage;
use App\Repository\ProductRepository;
use App\Service\ProductIndexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class IndexProductHandler
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductIndexer $productIndexer,
    ) {
    }

    public function __invoke(IndexProductMessage $message): void
    {
        $product = $this->productRepository->find($message->getProductId());

        if ($product === null) {
            $this->productIndexer->removeProduct($message->getProductId());

            return;
        }

        $this->productIndexer->indexProduct($product);
    }
}
