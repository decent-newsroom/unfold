<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Header
{
    public array $cats;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private readonly CacheInterface $cache, private readonly ParameterBagInterface $params)
    {
        $dTag = $this->params->get('d_tag');
        $mag = $this->cache->get('magazine-' . $dTag, function (){
            return null;
        });

        // Handle case when magazine is not found
        if ($mag === null) {
            $this->cats = [];
            return;
        }

        $tags = $mag->getTags();

        $this->cats = array_filter($tags, function($tag) {
            return ($tag[0] === 'a');
        });
    }
}
