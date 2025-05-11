<?php

namespace App\Twig\Components\Molecules;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class CategoryLink
{
    public string $title;
    public string $slug;

    public function __construct(private CacheInterface $redisCache)
    {
    }

    public function mount($coordinate): void
    {
        if (key_exists(1, $coordinate)) {
            $parts = explode(':', $coordinate[1]);
            $this->slug = $parts[2];
            $cat = $this->redisCache->get('magazine-' . $parts[2], function (){
                return null;
            });

            $tags = $cat->getTags();

            $title = array_filter($tags, function($tag) {
                return ($tag[0] === 'title');
            });

            $this->title = $title[array_key_first($title)][1];
        } else {
            dump($coordinate);die();
        }

    }

}
