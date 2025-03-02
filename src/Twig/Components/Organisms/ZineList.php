<?php

namespace App\Twig\Components\Organisms;

use App\Entity\Event;
use App\Entity\Nzine;
use App\Enum\KindsEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ZineList
{
    public array $nzines = [];
    public array $indices = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function mount(?array $nzines = null): void
    {
        $this->nzines = $nzines ?? $this->entityManager->getRepository(Nzine::class)->findAll();
        if (count($this->nzines) > 0) {
            // find indices for each nzine
            foreach ($this->nzines as $zine) {
                $ids = $this->entityManager->getRepository(Event::class)->findBy(['pubkey' => $zine->getNpub(), 'kind' => KindsEnum::PUBLICATION_INDEX]);
                $id = array_filter($ids, function($k) use ($zine) {
                    return $k->getSlug() == $zine->getSlug();
                });
                if ($id) {
                    $this->indices[$zine->getNpub()] = array_pop($id);
                }
            }
        }
    }
}
