<?php

declare(strict_types=1);

/**
 * Theme Manifest for theme id `2026` — SMITE 2026 (ADR-002 / TH-003).
 *
 * Field schema mirrors the baseline `default` theme for CMS compatibility.
 *
 * @return array{
 *     id: string,
 *     name: string,
 *     version: string,
 *     author: string,
 *     media_profiles: array<string, array<string, mixed>>,
 *     templates: array<string, array{label: string, fields: array<string, array<string, mixed>>}>
 * }
 */
return [
    'id'      => '2026',
    'name'    => 'SMITE 2026',
    'version' => '1.0.0',
    'author'  => 'SMITE CMS',

    'media_profiles' => [
        'cms_default' => [
            'maximum_width'     => 2560,
            'maximum_height'    => 2560,
            'maximum_file_size' => 5_242_880,
            'allowed_formats'   => ['jpeg', 'jpg', 'png', 'webp', 'gif'],
        ],
    ],

    'templates' => [
        'custom-page' => [
            'label'  => 'Custom Page',
            'fields' => [
                'hero_title' => [
                    'label'      => 'Hero Title',
                    'type'       => 'TEXT',
                    'required'   => false,
                    'validation' => ['max_length' => 150],
                ],
                'hero_description' => [
                    'label'    => 'Hero Description',
                    'type'     => 'TEXTAREA',
                    'required' => false,
                ],
                'body' => [
                    'label'    => 'Body',
                    'type'     => 'RICH_TEXT',
                    'required' => false,
                ],
                'hero_image' => [
                    'label'         => 'Hero Image',
                    'type'          => 'IMAGE',
                    'required'      => false,
                    'media_profile' => 'cms_default',
                ],
                'video_url' => [
                    'label'    => 'Video URL',
                    'type'     => 'YOUTUBE_URL',
                    'required' => false,
                ],
                'cta_url' => [
                    'label'    => 'CTA URL',
                    'type'     => 'URL',
                    'required' => false,
                ],
                'attachment' => [
                    'label'    => 'Attachment',
                    'type'     => 'DOCUMENT',
                    'required' => false,
                ],
                'hero_slides' => [
                    'label'         => 'Hero Slides',
                    'type'          => 'REPEATABLE',
                    'required'      => false,
                    'minimum_items' => 0,
                    'maximum_items' => 5,
                    'fields'        => [
                        'title' => [
                            'label'      => 'Slide Title',
                            'type'       => 'TEXT',
                            'required'   => true,
                            'validation' => ['max_length' => 120],
                        ],
                        'url' => [
                            'label'    => 'Slide URL',
                            'type'     => 'URL',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ],
        'custom-post' => [
            'label'  => 'Custom Post',
            'fields' => [
                'body' => [
                    'label'    => 'Body',
                    'type'     => 'RICH_TEXT',
                    'required' => false,
                ],
            ],
        ],
    ],
];
