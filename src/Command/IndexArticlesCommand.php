<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'articles:index', description: 'Persist selected articles to Elastic')]
class IndexArticlesCommand extends Command
{
    private const BATCH_SIZE = 100; // Define batch size

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly ObjectPersisterInterface $itemPersister)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $articles = $this->entityManager->getRepository(Article::class)->findBy(['indexStatus' => IndexStatusEnum::TO_BE_INDEXED]);

        $batchCount = 0;
        $processedCount = 0;

        foreach ($articles as $item) {
            $batchCount++;

            // Collect batch of entities for indexing
            $batchItems[] = $item;

            // Process batch when limit is reached
            if ($batchCount >= self::BATCH_SIZE) {
                $this->flushAndPersistBatch($batchItems);
                $processedCount += $batchCount;
                $batchCount = 0;
                $batchItems = [];
            }
        }

        // Process any remaining items
        if (!empty($batchItems)) {
            $this->flushAndPersistBatch($batchItems);
            $processedCount += count($batchItems);
        }

        $output->writeln("$processedCount items indexed in Elasticsearch.");
        return Command::SUCCESS;
    }

    private function flushAndPersistBatch(array $items): void
    {
        // Persist batch to Elasticsearch
        $this->itemPersister->replaceMany($items);
    }
}
