<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class Filters extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('shortenNpub', [$this, 'shortenNpub']),
        ];
    }

    public function shortenNpub(string $npub): string
    {
        return substr($npub, 0, 8) . '…' . substr($npub, -4);
    }
}
