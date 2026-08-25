<?php

declare(strict_types=1);

/**
 * Baseline Theme Manifest for theme id `default` (ADR-002).
 *
 * Trusted developer artifact — not browser-editable, not publicly routable.
 * Content field shapes follow DOC-05 §11–13 and ADR-004 validator field keys.
 *
 * ADR-002 / DOC-05 require template `custom-page`.
 * ADR-015 requires template `custom-post` with baseline field `body` (RICH_TEXT).
 * DOC-09 Phase 3 requires enough schema on custom-page to exercise scalar types,
 * optional fields, and one Repeatable Block. All fields are optional so draft `{}`
 * remains valid; DOC-04 required-at-publish enforcement is deferred with publishing.
 *
 * Image Profiles (DOC-05 §8 / DOC-06 §11 / ADR-018 §12): baseline catalog declares
 * `cms_default`. An empty catalog still falls back to the same built-in profile.
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
    'id'      => 'default',
    'name'    => 'Default',
    'version' => '1.0.0',
    'author'  => 'SMITE CMS',

    // ADR-018 §12 — Theme-authored baseline Image Profile catalog.
    'media_profiles' => [
        'cms_default' => [
            // No minimum_width / minimum_height (ADR-018: none for library upload).
            'maximum_width'     => 2560,
            'maximum_height'    => 2560,
            'maximum_file_size' => 5_242_880, // 5 MiB
            'allowed_formats'   => ['jpeg', 'jpg', 'png', 'webp', 'gif'],
            // No aspect_ratio / crop — resize down preserving aspect (ADR-018).
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
                // ADR-015 baseline: body RICH_TEXT, optional at draft.
                'body' => [
                    'label'    => 'Body',
                    'type'     => 'RICH_TEXT',
                    'required' => false,
                ],
            ],
        ],
    ],
];
