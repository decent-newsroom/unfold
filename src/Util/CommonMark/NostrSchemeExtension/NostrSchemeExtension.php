<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use App\Util\Bech32\Bech32Decoder;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class NostrSchemeExtension  implements ExtensionInterface
{

    public function __construct(private readonly Bech32Decoder $bech32Decoder)
    {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new NostrMentionParser(), 200)
            ->addInlineParser(new NostrSchemeParser($this->bech32Decoder), 199)
            ->addInlineParser(new NostrRawNpubParser(), 198)

            ->addRenderer(NostrSchemeData::class, new NostrEventRenderer(), 2)
            ->addRenderer(NostrMentionLink::class, new NostrMentionRenderer(), 1)
        ;
    }
}
