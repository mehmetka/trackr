<?php

namespace App\enum;

enum StatusColors: string
{
    case NEW = 'bg-secondary';
    case STARTED = 'bg-warning-dark';
    case DONE = 'bg-success';
    case LIST_OUT = 'bg-danger';
    case PRIORITIZED = 'bg-info';
}