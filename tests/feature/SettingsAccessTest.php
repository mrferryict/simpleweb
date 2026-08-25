<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP boundary checks for Site Settings (Phase 2 / Task 2.1).
 *
 * @internal
 */
final class SettingsAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * Settings package table is required because SessionAuth reads via setting().
     *
     * @var string
     */
    protected $namespace = 'CodeIgniter\Settings';

    protected $migrate = true;
    protected $refresh = true;

    public function testGetAdminSettingsRequiresAuthentication(): void
    {
        $result = $this->get('admin/settings');

        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testPostAdminSettingsRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/settings', [
                'site_name'        => 'X',
                'site_description' => '',
                'default_locale'   => 'id',
                'timezone'         => 'UTC',
                'contact_email'    => 'a@b.co',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }
}
