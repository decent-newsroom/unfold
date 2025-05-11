<?php

namespace App\Twig\Components\Organisms;

use App\Service\NostrClient;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Comments
{
    public array $list = [];

    public function __construct(private readonly NostrClient $nostrClient)
    {
    }

    /**
     * @throws \Exception
     */
    public function mount($current): void
    {
        // fetch comments, kind 1111
        $this->list = $this->nostrClient->getComments($current);
    }
}
