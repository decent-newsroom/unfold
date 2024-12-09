<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class NostrMentionRenderer implements NodeRendererInterface
{

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (!($node instanceof NostrMentionLink)) {
            throw new \InvalidArgumentException('Incompatible inline node type: ' . get_class($node));
        }

        $label = $node->getLabel() ?? $this->labelFromKey($node->getNpub());

        // Construct the local link URL from the npub part
        $url = '/p/' .  $node->getNpub();

        // Create the anchor element
        return new HtmlElement('a', ['href' => $url], '@' . $label);
    }

    private function labelFromKey($npub): string
    {
        $start = substr($npub, 0, 5); // First 5 characters
        $end = substr($npub, -5);       // Last 5 characters
        return $start . '...' . $end;              // Concatenate with ellipsis
    }
}
