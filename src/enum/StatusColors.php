<?php

namespace App\enum;

enum StatusColors: string
{
    case NEW = 'bg-secondary';
    case STARTED = 'bg-warning-dark';
    case DONE = 'bg-success';
    case ABANDONED = 'bg-danger';
    case PRIORITIZED = 'bg-info';
}