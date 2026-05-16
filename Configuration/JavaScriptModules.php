<?php

declare(strict_types=1);

return [
    'dependencies' => [
        'backend',
        'core',
    ],
    'imports' => [
        '@ideative/id-be-login/' => [
            'path' => 'EXT:id_be_login/Resources/Public/JavaScript/',
        ],
    ],
];
