<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DefaultController extends AbstractController
{
    public function __construct(
        private readonly FinderInterface $esFinder,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/', name: 'default')]
    public function index(): Response
    {
        $list = $this->entityManager->getRepository(Article::class)->findBy(['indexStatus' => IndexStatusEnum::INDEXED], ['createdAt' => 'DESC'], 10);

        // deduplicate by slugs
        $deduplicated = [];
        foreach ($list as $item) {
            if (!key_exists((string) $item->getSlug(), $deduplicated)) {
                $deduplicated[(string) $item->getSlug()] = $item;
            }
        }

        return $this->render('home.html.twig', [
            'list' => array_values(array_filter($deduplicated, function($item) {
                return !empty($item->getImage());
            }))
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/business', name: 'business')]
    public function business(CacheInterface $redisCache): Response
    {
        $articles = $redisCache->get('main_category_business', function (ItemInterface $item): array {
            $item->expiresAfter(36000);
            $search = [
                'finance business',
                'trading stock commodity',
                's&p500 gold oil',
                'currency bitcoin',
                'international military incident'
            ];

            return $this->getArticles($search);
        });

        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/technology', name: 'technology')]
    public function technology(CacheInterface $redisCache): Response
    {
        $articles = $redisCache->get('main_category_technology', function (ItemInterface $item): array {
            $item->expiresAfter(36000);
            $search = [
                'technology innovation',
                'ai llm chatgpt claude agent',
                'blockchain mining cryptography',
                'cypherpunk nostr',
                'server hosting'
            ];

            return $this->getArticles($search);
        });

        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/world', name: 'world')]
    public function world(CacheInterface $redisCache): Response
    {
        $articles = $redisCache->get('main_category_world', function (ItemInterface $item): array {
            $item->expiresAfter(36000);
            $search = [
                'politics policy president',
                'agreement law resolution',
                'tariffs taxes trade',
                'international military incident'
            ];

            return $this->getArticles($search);
        });

        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/lifestyle', name: 'lifestyle')]
    public function lifestyle(CacheInterface $redisCache): Response
    {
        $articles = $redisCache->get('main_category_lifestyle', function (ItemInterface $item): array {
            $item->expiresAfter(36000);
            $search = [
                'touch grass',
                'health healthy',
                'lifestyle wellness diet sunshine'
            ];

            return $this->getArticles($search);
        });

        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/art', name: 'art')]
    public function art(CacheInterface $redisCache): Response
    {
        $articles = $redisCache->get('main_category_art', function (ItemInterface $item): array {
            $item->expiresAfter(36000);
            $search = [
                'photo photography',
                'travel',
                'art painting'
            ];

            return $this->getArticles($search);
        });

        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
        ]);
    }

    /**
     * @param $search
     * @return array
     */
    public function getArticles($search): array
    {
        $articles = [];

        foreach ($search as $q) {
            $articles = array_merge($articles, $this->esFinder->find($q, 10));
        }

        // sort articles by created at date
        usort($articles, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        // deduplicate by slugs
        $deduplicated = [];
        foreach ($articles as $item) {
            if (!key_exists((string)$item->getSlug(), $deduplicated)) {
                $deduplicated[(string)$item->getSlug()] = $item;
            }
            // keep 10
            if (count($deduplicated) > 9) {
                break;
            }
        }

        return $deduplicated;
    }
}
