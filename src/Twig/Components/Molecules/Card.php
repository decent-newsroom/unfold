<?php

namespace App\Twig\Components\Molecules;

use App\Entity\User;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Card
{
    public string $category = '';
    public object $article;

    public function __construct()
    {
    }

}
