<?php

namespace App\Util\CommonMark\ImagesExtension;

use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\SoftBreak;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class RawImageLinkParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // Match URLs ending with an image extension
        return InlineParserMatch::regex('https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif|webp)(?=\s|$)');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $match = $inlineContext->getFullMatch();
        // Create an <img> element instead of a text link
        $image = new Image($match, '');
        $paragraph = new Paragraph();
        $paragraph->appendChild($image);
        $inlineContext->getContainer()->appendChild($paragraph);

        // Advance the cursor to consume the matched part (important!)
        $cursor->advanceBy(strlen($match));

        return true;
    }
}
