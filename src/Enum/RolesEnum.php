<?php

namespace App\Enum;

enum RolesEnum: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
    case EDITOR = 'ROLE_EDITOR';
}
