<?php

namespace App\Twig\Components\Organisms;

use App\Entity\Article;
use App\Factory\ArticleFactory;
use App\Service\NostrClient;
use Elastica\Query\MatchQuery;
use Elastica\Query\Terms;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Event\Event;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FeaturedList
{
    public $category;
    public string $title;
    public array $list = [];

    public function __construct(
        private readonly CacheInterface $redisCache,
        private readonly FinderInterface $finder)
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
        $catIndex = $this->redisCache->get('magazine-' . $parts[2], function (){
            throw new \Exception('Not found');
        });

        $slugs = [];
        foreach ($catIndex->getTags() as $tag) {
            if ($tag[0] === 'title') {
                $this->title = $tag[1];
            }
            if ($tag[0] === 'a') {
                $parts = explode(':', $tag[1]);
                if (count($parts) === 3) {
                    $slugs[] = $parts[2];
                }
            }
        }

        $query = new Terms('slug', array_values($slugs));
        $res = $this->finder->find($query);
        $this->list = array_slice($res, 0, 4);
    }
}
