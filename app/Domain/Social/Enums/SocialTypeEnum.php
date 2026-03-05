<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum SocialTypeEnum: int
{
    case SOCIAL_TYPE_ACCOUNT = 1;
    case SOCIAL_TYPE_EMAIL = 2;
    case SOCIAL_TYPE_CELL_PHONE = 3;
    case SOCIAL_TYPE_MAC_ADDRESS = 4;
    case SOCIAL_TYPE_GOOGLE = 5;
    case SOCIAL_TYPE_FACEBOOK = 6;
    case SOCIAL_TYPE_LINKED_IN = 7;
    case SOCIAL_TYPE_TWITTER = 8;
    case SOCIAL_TYPE_LINE = 9;
    case SOCIAL_TYPE_LINE_AT = 10;
    case SOCIAL_TYPE_WECHAT = 11;
    case SOCIAL_TYPE_YAHOO = 12;
    case SOCIAL_TYPE_OTHER = 13;
}
