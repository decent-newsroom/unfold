<?php

namespace App\Twig\Components\Molecules;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class NostrPreview
{
    public array $preview;
    
    public function mount(array $preview): void
    {
        $this->preview = $preview;
    }
}
