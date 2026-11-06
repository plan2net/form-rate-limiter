<?php

declare(strict_types=1);

$EM_CONF['form_rate_limiter'] = [
    'title' => 'Form rate limiter',
    'description' => 'Limits how often each visitor can complete a TYPO3 form, with allow and block lists for IP addresses.',
    'category' => 'fe',
    'author' => 'Oliver Gassner, Wolfgang Klinger',
    'author_email' => 'og@plan2.net, wk@plan2.net',
    'author_company' => 'plan2net GmbH',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '12.4.0-13.99.99',
            'form' => '12.4.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
