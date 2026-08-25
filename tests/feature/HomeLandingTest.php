<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * First-run public root landing (GET /) — no Page seed required.
 *
 * @internal
 */
final class HomeLandingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    public function testRootLandingRendersWithoutPublishedPages(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Website is ready.', $body);
        $this->assertStringContainsString('/cp', $body);
        $this->assertStringNotContainsString('Welcome to CodeIgniter', $body);
        $this->assertSame(0, db_connect()->table('pages')->countAllResults());
    }
}
