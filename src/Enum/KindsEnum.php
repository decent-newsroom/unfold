<?php

namespace App\Enum;

enum KindsEnum: int
{
    case METADATA = 0; // metadata, NIP-01
    case TEXT_NOTE = 1; // text note, NIP-01
    case REPOST = 6; // Only wraps kind 1, NIP-18
    case GENERIC_REPOST = 16; // Generic repost, original kind signalled in a "k" tag, NIP-18
    case PINNED_LONGFORM = 10023; // Special purpose curation set, NIP-51, seems deprecated?
    case HTTP_AUTH = 27235; // NIP-98, HTTP Auth
    case CURATION_SET = 30004; // NIP-51
    case LONGFORM = 30023; // NIP-23
    case LONGFORM_DRAFT = 30024; // NIP-23
    case CONTENT_SEARCH = 5302;
    case CONTENT_INDEX = 5312;
    case CONTENT_SEARCH_RESULT = 6302;
    case CONTENT_INDEX_RESULT = 6312;
    case HIGHLIGHTS = 9802;
    case APP_DATA = 30078; // NIP-78, Arbitrary custom app data
}
