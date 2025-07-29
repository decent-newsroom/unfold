<?php

namespace App\Repository;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Search articles by title, content, and summary using database LIKE queries
     */
    public function searchArticles(string $query, int $limit = 12, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a');

        $searchTerms = explode(' ', trim($query));
        $conditions = $qb->expr()->orX();

        foreach ($searchTerms as $index => $term) {
            $term = trim($term);
            if (empty($term)) {
                continue;
            }

            $paramName = 'term' . $index;
            $termCondition = $qb->expr()->orX(
                $qb->expr()->like('a.title', ':' . $paramName),
                $qb->expr()->like('a.content', ':' . $paramName),
                $qb->expr()->like('a.summary', ':' . $paramName)
            );
            $conditions->add($termCondition);
            $qb->setParameter($paramName, '%' . $term . '%');
        }

        return $qb
            ->where($conditions)
            ->andWhere('a.content IS NOT NULL')
            ->andWhere('LENGTH(a.content) > 250') // Only articles with substantial content
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles by multiple slugs
     */
    public function findBySlugsCriteria(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->where('a.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles by author's public key
     */
    public function findByPubkey(string $pubkey, int $limit = 25): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.pubkey = :pubkey')
            ->setParameter('pubkey', $pubkey)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
