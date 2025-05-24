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
use App\Service\NostrClient;
use App\Factory\ArticleFactory;
use Psr\Log\LoggerInterface;

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
                                FinderInterface $finder,
                                NostrClient $nostrClient,
                                ArticleFactory $articleFactory,
                                LoggerInterface $logger): Response
    {
        $catIndex = $redisCache->get('magazine-' . $slug, function (){
            throw new \Exception('Not found');
        });

        $list = [];
        $coordinates = []; // Store full coordinates (kind:author:slug)
        $category = [];

        // Extract category metadata and article coordinates
        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'title') {
                $category['title'] = $tag[1];
            }
            if ($tag[0] === 'summary') {
                $category['summary'] = $tag[1];
            }
            if ($tag[0] === 'a') {
                $coordinates[] = $tag[1]; // Store the full coordinate
            }
        }

        // Limit to first 9 coordinates to avoid excessive processing
        $coordinates = array_slice($coordinates, 0, 9);

        if (!empty($coordinates)) {
            // Extract slugs for elasticsearch query
            $slugs = array_map(function($coordinate) {
                $parts = explode(':', $coordinate);
                return count($parts) === 3 ? $parts[2] : '';
            }, $coordinates);
            $slugs = array_filter($slugs); // Remove empty values

            // Try to fetch articles from elasticsearch first
            $query = new Terms('slug', array_values($slugs));
            $articles = $finder->find($query);

            // Create a map of slug => item to remove duplicates
            $slugMap = [];
            foreach ($articles as $item) {
                $slug = $item->getSlug();
                if ($slug !== '') {
                    $slugMap[$slug] = $item;
                }
            }

            // Find missing coordinates
            $missingCoordinates = [];
            foreach ($coordinates as $coordinate) {
                $parts = explode(':', $coordinate);
                if (count($parts) === 3 && !isset($slugMap[$parts[2]])) {
                    $missingCoordinates[] = $coordinate;
                }
            }

            // If we have missing articles, fetch them directly using NostrClient's getArticlesByCoordinates
            if (!empty($missingCoordinates)) {

                $logger->info('There were missing articles', [
                    'missing' => $missingCoordinates
                ]);

//                try {
//                    $nostrArticles = $nostrClient->getArticlesByCoordinates($missingCoordinates);
//
//                    foreach ($nostrArticles as $coordinate => $event) {
//                        $parts = explode(':', $coordinate);
//                        if (count($parts) === 3) {
//                            $article = $articleFactory->createFromLongFormContentEvent($event);
//                            // Save article to database for future queries
//                            $nostrClient->saveEachArticleToTheDatabase($article);
//                            // Add to the slugMap
//                            $slugMap[$article->getSlug()] = $article;
//                        }
//                    }
//                } catch (\Exception $e) {
//                    $logger->error('Error fetching missing articles', [
//                        'error' => $e->getMessage()
//                    ]);
//                }
            }

            // Build ordered list based on original coordinates order
            foreach ($coordinates as $coordinate) {
                $parts = explode(':', $coordinate);
                if (count($parts) === 3 && isset($slugMap[$parts[2]])) {
                    $list[] = $slugMap[$parts[2]];
                }
            }
        }

        return $this->render('pages/category.html.twig', [
            'list' => $list,
            'category' => $category,
            'index' => $catIndex
        ]);
    }
}
