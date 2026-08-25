<?php

declare(strict_types=1);

/**
 * DRAFT-only Theme fixture (discovered but not in Config enabled list).
 *
 * @return array{
 *     id: string,
 *     name: string,
 *     version: string,
 *     author: string,
 *     media_profiles: array<string, mixed>,
 *     templates: array<string, array{label: string, fields: array<string, array<string, mixed>>}>
 * }
 */
return [
    'id'      => 'draft-only',
    'name'    => 'Draft Only',
    'version' => '1.0.0',
    'author'  => 'SMITE CMS',

    'media_profiles' => [],

    'templates' => [
        'custom-page' => [
            'label'  => 'Custom Page',
            'fields' => [
                'body' => ['type' => 'RICH_TEXT'],
            ],
        ],
        'custom-post' => [
            'label'  => 'Custom Post',
            'fields' => [
                'body' => ['type' => 'RICH_TEXT'],
            ],
        ],
    ],
];
