<?php

namespace App\Command;

use App\Repository\ProductRepository;
use App\Service\ProductIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:reindex',
    description: 'Zero-downtime reindex of all products into a new Elasticsearch index',
)]
class ElasticsearchReindexCommand extends Command
{
    public function __construct(
        private readonly ProductIndexer $productIndexer,
        private readonly ProductRepository $productRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reindexing products into Elasticsearch');

        $total = $this->productRepository->count();
        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();

        foreach ($this->productIndexer->reindex($this->productRepository->findAllForReindex()) as $product) {
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');

        $io->success(sprintf('Reindexed %d products successfully.', $total));

        return Command::SUCCESS;
    }
}
