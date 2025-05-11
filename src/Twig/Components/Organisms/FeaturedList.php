<?php

namespace App\Twig\Components\Organisms;

use Elastica\Query\MatchQuery;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Event\Event;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FeaturedList
{
    public $category;
    public string $title;
    public array $list = [];

    public function __construct(private readonly CacheInterface $redisCache, private readonly FinderInterface $finder)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function mount($category): void
    {
        $parts = explode(':', $category[1]);
        /** @var Event $catIndex */
        $catIndex = $this->redisCache->get('magazine-' . $parts[2], function (){
            throw new \Exception('Not found');
        });

        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'title') {
                $this->title = $tag[1];
            }
            if ($tag[0] === 'a') {
                $parts = explode(':', $tag[1]);
                if (count($parts) === 3) {
                    $fieldQuery = new MatchQuery();
                    $fieldQuery->setFieldQuery('slug', $parts[2]);
                    $res = $this->finder->find($fieldQuery);
                    $this->list[] = $res[0];
                }
            }
            if (count($this->list) > 3) {
                break;
            }
        }
    }
}
