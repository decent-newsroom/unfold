<?php

namespace App\Twig\Components\Molecules;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $npub;
    public ?User $user = null;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function mount(string $npub): void
    {
        $this->npub = $npub;
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $npub]);
    }
}
