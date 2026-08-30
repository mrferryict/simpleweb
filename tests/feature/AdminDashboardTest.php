<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Control Panel dashboard HTTP boundary (TH-006).
 *
 * @internal
 */
final class AdminDashboardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    public function testAdminDashboardRequiresAuthentication(): void
    {
        $result = $this->get('admin');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testHtmxUnauthenticatedDashboardReturnsHxRedirectToCp(): void
    {
        $result   = $this->withHeaders(['HX-Request' => 'true'])->get('admin');
        $response = $result->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
        $this->assertSame('', $response->getBody());
    }
}
