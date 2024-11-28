<?php

namespace App\Enum;

enum EventStatusEnum: int
{
    case PREVIEW = 0;
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3; // when newer edits exist, this is no longer the latest version
}
