<?php

namespace App\Twig\Components\Organisms;

use App\Entity\Nzine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ZineList
{
    public array $nzines = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function mount(?array $nzines = null): void
    {
        $this->nzines = $nzines ?? $this->entityManager->getRepository(Nzine::class)->findAll();
    }
}
