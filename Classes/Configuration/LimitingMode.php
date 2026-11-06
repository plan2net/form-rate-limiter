<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Configuration;

enum LimitingMode: string
{
    case PerForm = 'per_form';
    case Global = 'global';
}
