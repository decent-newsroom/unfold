<?php

namespace App\Twig\Components\Molecules;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class CategoryLink
{
    public string $title;
    public string $slug;

    public function __construct(private CacheInterface $cache)
    {
    }

    public function mount($category): void
    {
        $parts = explode(':', $category[1]);
        $this->slug = $parts[2];
        try {
            $cat = $this->cache->get('magazine-' . $parts[2], function (){
                throw new \Exception('Not found');
            });

            $tags = $cat->getTags();

            $title = array_filter($tags, function($tag) {
                return ($tag[0] === 'title');
            });

            $this->title = $title[array_key_first($title)][1];
        } catch (\Exception $e) {
            // Handle cache miss
        }
    }
}
