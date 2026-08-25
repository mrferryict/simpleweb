<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP boundary checks for Menu CRUD (Phase 2 / Task 2.2).
 *
 * @internal
 */
final class MenuAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * Settings table required by SessionAuth setting() lookups.
     *
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    public function testGetAdminMenusRequiresAuthentication(): void
    {
        $result = $this->get('admin/menus');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testPostAdminMenusRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/menus', [
                'location'      => 'PRIMARY',
                'label'         => 'X',
                'destination'   => '/',
                'display_order' => '0',
                'is_active'     => '1',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }
}
