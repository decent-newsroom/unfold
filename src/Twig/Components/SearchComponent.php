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
        $res = $this->finder->find($this->query, 10); // Limit to 10 results
        return $res; // Limit to 10 results
    }

}
