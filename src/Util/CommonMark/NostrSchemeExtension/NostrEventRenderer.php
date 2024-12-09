<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class NostrEventRenderer implements NodeRendererInterface
{

    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        if (!($node instanceof NostrSchemeData)) {
            throw new \InvalidArgumentException('Incompatible inline node type: ' . get_class($node));
        }

        if ($node->getType() === 'nevent') {
            // Construct the local link URL from the special part
            $url = '/e/' .  $node->getSpecial();
        } else if ($node->getType() === 'naddr') {
            dump($node);
            // Construct the local link URL from the special part
            $url = '/' .  $node->getSpecial();
        }

        if (isset($url)) {
            // Create the anchor element
            return new HtmlElement('a', ['href' => $url], '@' . $node->getSpecial());
        }

        return false;

    }
}
