<?php

declare(strict_types=1);

return [
    'backend' => [
        'id-be-login/color-scheme-cookie' => [
            'target' => \Ideative\T3BeLogin\Middleware\BackendColorSchemeCookieMiddleware::class,
            'after' => [
                'typo3/cms-backend/response-headers',
            ],
            'before' => [
                'typo3/cms-core/response-propagation',
            ],
        ],
    ],
];
