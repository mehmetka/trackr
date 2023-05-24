<?php

namespace App\enum;

enum BookStatus: int
{
    case NEW = 0;
    case STARTED = 1;
    case DONE = 2;
    case LIST_OUT = 3;
    case PRIORITIZED = 4;
}