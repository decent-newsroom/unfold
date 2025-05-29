<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use App\Service\RedisCacheService;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Class NostrRawNpubParser
 * Looks for raw nostr mentions formatted as npub1XXXX
 */
readonly class NostrRawNpubParser implements InlineParserInterface
{

    public function __construct(private RedisCacheService $redisCacheService)
    {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('npub1[0-9a-zA-Z]+');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        // Get the match and extract relevant parts
        $fullMatch = $inlineContext->getFullMatch();
        $meta = $this->redisCacheService->getMetadata($fullMatch);

        // Create a new inline node for the custom link
        $inlineContext->getContainer()->appendChild(new NostrMentionLink($meta->name, $fullMatch));

        // Advance the cursor to consume the matched part (important!)
        $cursor->advanceBy(strlen($fullMatch));

        return true;
    }
}
