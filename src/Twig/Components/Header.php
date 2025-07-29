<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Header
{
    public array $cats;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private readonly CacheInterface $cache)
    {
        $mag = $this->cache->get('magazine-newsroom-magazine-by-newsroom', function (){
            return null;
        });

        $tags = $mag->getTags();

        $this->cats = array_filter($tags, function($tag) {
            return ($tag[0] === 'a');
        });
    }
}
