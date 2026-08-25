<?php

declare(strict_types=1);

/**
 * Minimal second Theme for lifecycle tests (ADR-022).
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
    'id'      => 'classic',
    'name'    => 'Classic',
    'version' => '1.0.0',
    'author'  => 'SMITE CMS',

    'media_profiles' => [],

    'templates' => [
        'custom-page' => [
            'label'  => 'Custom Page',
            'fields' => [
                'body' => [
                    'type' => 'RICH_TEXT',
                ],
            ],
        ],
        'custom-post' => [
            'label'  => 'Custom Post',
            'fields' => [
                'body' => [
                    'type' => 'RICH_TEXT',
                ],
            ],
        ],
    ],
];
