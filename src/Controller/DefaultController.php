<?php

declare(strict_types=1);

namespace App\Controller;

use Elastica\Query\MatchQuery;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Event\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DefaultController extends AbstractController
{
    public function __construct(
        private readonly FinderInterface $esFinder,
        private readonly CacheInterface $redisCache)
    {
    }

    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/', name: 'home')]
    public function index(FinderInterface $finder): Response
    {
        // get newsroom index, loop over categories, pick top three from each and display in sections
        $mag = $this->redisCache->get('magazine-newsroom-magazine-by-newsroom', function (){
            return null;
        });
        $tags = $mag->getTags();

        $cats = array_filter($tags, function($tag) {
            return ($tag[0] === 'a');
        });

        return $this->render('home.html.twig', [
            'indices' => $cats
        ]);
    }


    /**
     * @throws InvalidArgumentException
     */
    #[Route('/cat/{slug}', name: 'magazine-category')]
    public function magCategory($slug, CacheInterface $redisCache, FinderInterface $finder): Response
    {
        $catIndex = $redisCache->get('magazine-' . $slug, function (){
            throw new \Exception('Not found');
        });

        $articles = [];
        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'a') {
                $parts = explode(':', $tag[1]);
                if (count($parts) === 3) {
                    $fieldQuery = new MatchQuery();
                    $fieldQuery->setFieldQuery('slug', $parts[2]);
                    $res = $finder->find($fieldQuery);
                    $articles[] = $res[0];
                }
            }
        }


        return $this->render('pages/category.html.twig', [
            'list' => array_slice($articles, 0, 9)
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
