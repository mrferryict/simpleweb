<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\MenuItem;
use App\Enums\MenuTargetType;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Menus and Settings admin presentation polish (TH-009).
 *
 * @internal
 */
final class AdminMenusSettingsUiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    public function testMenuListEmptyStateRendersCreateAction(): void
    {
        $html = view('admin/menus/index', [
            'grouped' => ['PRIMARY' => [], 'FOOTER' => []],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('admin-empty-state', $html);
        $this->assertStringContainsString('No menu items yet', $html);
        $this->assertStringContainsString('Add menu item', $html);
        $this->assertStringContainsString('admin/menus/new', $html);
        $this->assertStringContainsString('admin-toolbar', $html);
    }

    public function testMenuListRendersHierarchyAndActions(): void
    {
        $parent = new MenuItem([
            'id'            => 1,
            'location'      => 'PRIMARY',
            'parent_id'     => null,
            'label'         => 'About',
            'target_type'   => MenuTargetType::Page->value,
            'target_id'     => 5,
            'destination'   => '',
            'display_order' => 0,
            'is_active'     => true,
        ]);
        $child = new MenuItem([
            'id'            => 2,
            'location'      => 'PRIMARY',
            'parent_id'     => 1,
            'label'         => 'Team',
            'target_type'   => MenuTargetType::ExternalUrl->value,
            'target_id'     => null,
            'destination'   => 'https://example.com/team',
            'display_order' => 1,
            'is_active'     => false,
        ]);

        $html = view('admin/menus/index', [
            'grouped' => [
                'PRIMARY' => [['item' => $parent, 'children' => [$child]]],
                'FOOTER'  => [],
            ],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('admin-menu-tree', $html);
        $this->assertStringContainsString('admin-menu-tree__children', $html);
        $this->assertStringContainsString('admin-menu-tree__label', $html);
        $this->assertStringContainsString('About', $html);
        $this->assertStringContainsString('Child:', $html);
        $this->assertStringContainsString('Team', $html);
        $this->assertStringContainsString('Page #5', $html);
        $this->assertStringContainsString('https://example.com/team', $html);
        $this->assertStringContainsString('status-badge--active', $html);
        $this->assertStringContainsString('status-badge--inactive', $html);
        $this->assertStringContainsString('admin/menus/1/edit', $html);
        $this->assertStringContainsString('admin/menus/2/delete', $html);
        $this->assertStringContainsString('name="csrf_', $html);
    }

    public function testMenuFormPreservesDestinationFieldsAndCsrf(): void
    {
        $html = view('admin/menus/form', [
            'mode'        => 'create',
            'item'        => [
                'location'      => 'PRIMARY',
                'parent_id'     => null,
                'label'         => '',
                'target_type'   => MenuTargetType::ExternalUrl->value,
                'target_id'     => null,
                'external_url'  => '',
                'display_order' => 0,
                'is_active'     => true,
            ],
            'locations'   => ['PRIMARY', 'FOOTER'],
            'targetTypes' => MenuTargetType::cases(),
            'parents'     => [],
            'errors'      => [],
            'formAction'  => site_url('admin/menus'),
        ]);

        $this->assertStringContainsString('admin-form-section', $html);
        $this->assertStringContainsString('<legend>Destination</legend>', $html);
        $this->assertStringContainsString('name="target_type"', $html);
        $this->assertStringContainsString('name="target_id"', $html);
        $this->assertStringContainsString('name="external_url"', $html);
        $this->assertStringContainsString('name="display_order"', $html);
        $this->assertStringContainsString('Create menu item', $html);
        $this->assertStringContainsString('csrf_', $html);
    }

    public function testMenuEditFormPopulatesExistingValues(): void
    {
        $html = view('admin/menus/form', [
            'mode'        => 'edit',
            'item'        => [
                'id'            => 9,
                'location'      => 'FOOTER',
                'parent_id'     => null,
                'label'         => 'Contact',
                'target_type'   => MenuTargetType::ExternalUrl->value,
                'target_id'     => null,
                'external_url'  => 'https://example.com/contact',
                'display_order' => 3,
                'is_active'     => true,
            ],
            'locations'   => ['PRIMARY', 'FOOTER'],
            'targetTypes' => MenuTargetType::cases(),
            'parents'     => [],
            'errors'      => [],
            'formAction'  => site_url('admin/menus/9'),
        ]);

        $this->assertStringContainsString('value="Contact"', $html);
        $this->assertStringContainsString('example.com&#x2F;contact', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertStringContainsString('Update menu item', $html);
    }

    public function testSettingsPageRendersConfigurationSections(): void
    {
        $html = view('admin/settings/index', [
            'settings' => [
                'site_name'               => 'SMITE Demo',
                'site_description'        => 'A demo site',
                'default_locale'          => 'id',
                'primary_locale'          => 'id',
                'secondary_locale'        => '',
                'timezone'                => 'Asia/Jakarta',
                'contact_email'           => 'hello@example.com',
                'seo_meta_title_id'       => 'Judul ID',
                'seo_meta_title_en'       => 'Title EN',
                'seo_meta_description_id' => 'Deskripsi ID',
                'seo_meta_description_en' => 'Description EN',
            ],
            'errors'  => [],
            'success' => null,
        ]);

        $this->assertStringContainsString('admin-settings', $html);
        $this->assertStringContainsString('Site identity', $html);
        $this->assertStringContainsString('Localization', $html);
        $this->assertStringContainsString('<legend>SEO defaults</legend>', $html);
        $this->assertStringContainsString('name="site_name"', $html);
        $this->assertStringContainsString('value="SMITE&#x20;Demo"', $html);
        $this->assertStringContainsString('name="primary_locale"', $html);
        $this->assertStringContainsString('name="secondary_locale"', $html);
        $this->assertStringContainsString('name="timezone"', $html);
        $this->assertStringContainsString('value="Asia&#x2F;Jakarta"', $html);
        $this->assertStringContainsString('hello&#x40;example.com', $html);
        $this->assertStringContainsString('Judul&#x20;ID', $html);
        $this->assertStringContainsString('Save settings', $html);
        $this->assertStringContainsString('admin/menus', $html);
        $this->assertStringNotContainsString('name="template', $html);
    }

    public function testSettingsPageShowsValidationErrors(): void
    {
        $html = view('admin/settings/index', [
            'settings' => [
                'site_name'               => '',
                'site_description'        => '',
                'default_locale'          => 'id',
                'primary_locale'          => 'id',
                'secondary_locale'        => '',
                'timezone'                => '',
                'contact_email'           => 'bad',
                'seo_meta_title_id'       => '',
                'seo_meta_title_en'       => '',
                'seo_meta_description_id' => '',
                'seo_meta_description_en' => '',
            ],
            'errors'  => [
                'site_name'     => 'Site name is required.',
                'contact_email' => 'Contact email is invalid.',
            ],
            'success' => null,
        ]);

        $this->assertStringContainsString('admin-field-error', $html);
        $this->assertStringContainsString('Site name is required.', $html);
        $this->assertStringContainsString('Contact email is invalid.', $html);
        $this->assertStringContainsString('admin-alert--error', $html);
    }
}
