<?php

namespace App\Command;

use App\Service\ProductIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:create-index',
    description: 'Create the initial Elasticsearch index with aliases',
)]
class ElasticsearchCreateIndexCommand extends Command
{
    public function __construct(
        private readonly ProductIndexer $productIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indexName = $this->productIndexer->createInitialIndex();

        $io->success(sprintf('Elasticsearch index "%s" is ready (aliases: %s, %s).', $indexName, ProductIndexer::READ_ALIAS, ProductIndexer::WRITE_ALIAS));

        return Command::SUCCESS;
    }
}
