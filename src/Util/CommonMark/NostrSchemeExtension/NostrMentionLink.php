<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use League\CommonMark\Node\Inline\AbstractInline;

class NostrMentionLink extends AbstractInline
{
    private ?string $label;
    private string $npub;

    public function __construct(?string $label, string $npubPart)
    {
        parent::__construct();

        $this->label = $label;
        $this->npub = $npubPart;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getNpub(): string
    {
        return $this->npub;
    }
}
