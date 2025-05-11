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

#[AsCommand(name: 'articles:indexed', description: 'Mark articles as indexed after populating')]
class MarkAsIndexedCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $articles = $this->entityManager->getRepository(Article::class)->findBy(['indexStatus' => IndexStatusEnum::TO_BE_INDEXED]);
        $count = 0;
        foreach ($articles as $article) {
            if ($article instanceof Article) {
                $count += 1;
                $article->setIndexStatus(IndexStatusEnum::INDEXED);
                $this->entityManager->persist($article);
            }
        }

        $this->entityManager->flush();

        $output->writeln($count . ' articles marked as indexed successfully.');

        return Command::SUCCESS;
    }
}
