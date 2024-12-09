<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use swentel\nostr\Key\Key;

/**
 * Class NostrRawNpubParser
 * Looks for raw nostr mentions formatted as npub1XXXX
 */
class NostrRawNpubParser implements InlineParserInterface
{

    public function __construct()
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
        $key = new Key();
        $hex = $key->convertToHex($fullMatch);

        // Create a new inline node for the custom link
        $inlineContext->getContainer()->appendChild(new NostrMentionLink(null, $hex));

        // Advance the cursor to consume the matched part (important!)
        $cursor->advanceBy(strlen($fullMatch));

        return true;
    }
}
