<?php

namespace App\enum;

enum LogTypes: string
{
    case ERROR = 'error';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case INFO = 'info';
}