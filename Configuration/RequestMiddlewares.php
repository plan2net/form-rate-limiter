<?php

declare(strict_types=1);

return [
    'frontend' => [
        'plan2net/form-rate-limiter' => [
            'target' => \Plan2net\FormRateLimiter\Middleware\FormRateLimitMiddleware::class,
            'after' => [
                'typo3/cms-frontend/authentication',
                'typo3/cms-frontend/base-redirect-resolver',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
    ],
];
