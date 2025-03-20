<?php

namespace App\Provider;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;


class ArticleProvider implements PagerProviderInterface
{

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function provide(array $options = []): PagerInterface
    {
        $articles = $this->entityManager->getRepository(Article::class)->findBy(['indexStatus' => IndexStatusEnum::TO_BE_INDEXED],['createdAt' => 'ASC'],200);
        return new PagerfantaPager(new Pagerfanta(new ArrayAdapter($articles)));
    }
}
