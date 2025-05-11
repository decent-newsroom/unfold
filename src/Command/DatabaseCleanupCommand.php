<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:cleanup', description: 'Remove articles with do_not_index rating')]
class DatabaseCleanupCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $repository = $this->entityManager->getRepository(Article::class);
        $items = $repository->findBy(['indexStatus' => IndexStatusEnum::DO_NOT_INDEX]);

        if (empty($items)) {
            $output->writeln('<info>No items found.</info>');
            return Command::SUCCESS;
        }

        foreach ($items as $item) {
            $this->entityManager->remove($item);
        }

        $this->entityManager->flush();

        $output->writeln('<comment>Deleted ' . count($items) . ' items.</comment>');


        return Command::SUCCESS;
    }
}
