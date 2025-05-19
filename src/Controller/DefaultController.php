<?php

declare(strict_types=1);

namespace App\Controller;

use Elastica\Query\Terms;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

class DefaultController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $redisCache)
    {
    }

    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/', name: 'home')]
    public function index(): Response
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
            'indices' => array_values($cats)
        ]);
    }


    /**
     * @throws InvalidArgumentException
     */
    #[Route('/cat/{slug}', name: 'magazine-category')]
    public function magCategory($slug, CacheInterface $redisCache,
                                FinderInterface $finder): Response
    {
        $catIndex = $redisCache->get('magazine-' . $slug, function (){
            throw new \Exception('Not found');
        });

        $list = [];
        $slugs = [];
        $category = [];

        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'title') {
                $category['title'] = $tag[1];
            }
            if ($tag[0] === 'summary') {
                $category['summary'] = $tag[1];
            }
            if ($tag[0] === 'a') {
                $parts = explode(':', $tag[1]);
                if (count($parts) === 3) {
                    $slugs[] = $parts[2];
                }
            }
        }

        if (!empty($slugs)) {

            $query = new Terms('slug', array_values($slugs));
            $articles = $finder->find($query);

            // Create a map of slug => item to remove duplicates
            $slugMap = [];

            foreach ($articles as $item) {
                $slug = $item->getSlug();

                if ($slug !== '' && !isset($slugMap[$slug])) {
                    $slugMap[$slug] = $item;
                }
            }

            if (!empty($res)) {
                foreach ($res as $result) {
                    if (!isset($slugMap[$result->getSlug()])) {
                        $slugMap[$result->getSlug()] = $result;
                    }
                }
            }


            // Reorder by the original $slugs
            $results = [];
            foreach ($slugs as $slug) {
                if (isset($slugMap[$slug])) {
                    $results[] = $slugMap[$slug];
                }
            }
            $list = array_values($results);
        }

        return $this->render('pages/category.html.twig', [
            'list' => $list,
            'category' => $category,
            'index' => $catIndex
        ]);
    }
}
