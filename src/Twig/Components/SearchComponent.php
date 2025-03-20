<?php

namespace App\Twig\Components;

use FOS\ElasticaBundle\Finder\FinderInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class SearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public bool $interactive = true;

    private FinderInterface $finder;

    public function __construct(FinderInterface $finder)
    {
        $this->finder = $finder;
    }

    public function getResults()
    {
        if (empty($this->query)) {
            return [];
        }
        $res = $this->finder->find($this->query, 12); // Limit to 10 results

        // filter out items with bad slugs
        $filtered = array_filter($res, function($r) {
           return !str_contains($r->getSlug(), '/');
        });


        return $filtered;
    }

}
