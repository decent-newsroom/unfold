<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Class NostrSchemeData
 * NIP-19 bech32-encoded entities
 *
 * @package App\Util\CommonMark
 */
class NostrSchemeData  extends AbstractInline
{
    private $type;
    private $special;
    private $relays;
    private $author;
    private $kind;

    public function __construct($type, $special, $relays, $author, $kind)
    {
        parent::__construct();

        $this->type = $type;
        $this->special = $special;
        $this->relays = $relays;
        $this->author = $author;
        $this->kind = $kind;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getSpecial()
    {
        return $this->special;
    }

    public function getRelays()
    {
        return $this->relays;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function getKind()
    {
        return $this->kind;
    }
}
