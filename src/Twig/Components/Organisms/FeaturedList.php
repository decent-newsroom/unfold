<?php

namespace App\Twig\Components\Organisms;

use App\Repository\ArticleRepository;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Event\Event;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FeaturedList
{
    public string $category;
    public string $title;
    public array $list = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ArticleRepository $articleRepository)
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function mount($category): void
    {
        $parts = explode(':', $category[1]);
        /** @var Event $catIndex */
        $catIndex = $this->cache->get('magazine-' . $parts[2], function (){
            throw new \Exception('Not found');
        });

        $slugs = [];
        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'title') {
                $this->title = $tag[1];
            }
            if ($tag[0] === 'a') {
                $parts = explode(':', $tag[1], 3);
                $slugs[] = end($parts);
                if (count($slugs) >= 5) {
                    break; // Limit to 5 items
                }
            }
        }

        // Use database query instead of Elasticsearch
        if (!empty($slugs)) {
            $articles = $this->articleRepository->findBySlugsCriteria($slugs);

            // Create a map of slug => item to get the latest version of each
            $slugMap = [];
            foreach ($articles as $article) {
                $slug = $article->getSlug();
                if ($slug !== '') {
                    if (!isset($slugMap[$slug])) {
                        $slugMap[$slug] = $article;
                    } elseif ($article->getCreatedAt() > $slugMap[$slug]->getCreatedAt()) {
                        $slugMap[$slug] = $article;
                    }
                }
            }

            // Build ordered list based on original slugs order
            $orderedList = [];
            foreach ($slugs as $slug) {
                if (isset($slugMap[$slug])) {
                    $orderedList[] = $slugMap[$slug];
                }
            }

            $this->list = array_slice($orderedList, 0, 4);
        } else {
            $this->list = [];
        }
    }
}
