<?php

namespace App\EventListener;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;

class PopulateListener
{
    public function __construct( private readonly EntityManagerInterface $entityManager)
    {
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $articles = $this->entityManager->getRepository(Article::class)->findBy(['indexStatus' => IndexStatusEnum::TO_BE_INDEXED]);

        foreach ($articles as $article) {
            if ($article instanceof Article) {
                $article->setIndexStatus(IndexStatusEnum::INDEXED);
                $this->entityManager->persist($article);
            }
        }

        $this->entityManager->flush();

    }
}
