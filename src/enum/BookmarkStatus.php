<?php

namespace App\enum;

enum BookmarkStatus: int
{
    case NEW = 0;
    case STARTED = 1;
    case DONE = 2;
    case ABANDONED = 3;
    case PRIORITIZED = 4;
}