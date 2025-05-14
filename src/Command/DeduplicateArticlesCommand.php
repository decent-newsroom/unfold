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

#[AsCommand(name: 'articles:deduplicate', description: 'Mark duplicates with DO_NOT_INDEX.')]
class DeduplicateArticlesCommand extends Command
{
    private const BATCH_SIZE = 500;


    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Article::class);
        $slugIndex = [];
        $page = 0;

        // Process articles in batches
        while (true) {
            // Fetch a batch of articles
            $articles = $repo->findBy([], ['createdAt' => 'DESC'], self::BATCH_SIZE, $page * self::BATCH_SIZE);

            if (empty($articles)) {
                break;
            }

            foreach ($articles as $article) {
                $slug = $article->getSlug();

                // If this slug hasn't been seen, store the slug
                if (!in_array($slug, $slugIndex)) {
                    $slugIndex[] = $slug;
                    continue;
                }
                // The articles are sorted, so the first one should be kept
                // Mark current article as DO_NOT_INDEX
                $article->setIndexStatus(IndexStatusEnum::DO_NOT_INDEX);
            }

            // Flush the batch and clear memory to avoid overload
            $this->em->flush();
            $this->em->clear(); // Clear the entity manager to free up memory

            $output->writeln("Processed batch " . ($page + 1));
            $page++;
        }

        $output->writeln('Article deduplication complete.');
        return Command::SUCCESS;

    }

}
