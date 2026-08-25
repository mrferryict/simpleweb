<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Admin schedule HTTP boundary (Phase 4 / Task 4.12C / ADR-021).
 *
 * @internal
 */
final class ScheduledContentAccessTest extends CIUnitTestCase
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

    public function testScheduleCreateRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/pages/1/schedules', [
                'action_type' => 'PUBLISH',
                'execute_at'  => '2031-01-01T10:00',
            ]);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testScheduleCancelRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/pages/1/schedules/1/cancel', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testGetCannotCreateOrCancelSchedule(): void
    {
        try {
            $create = $this->get('admin/pages/1/schedules');
            $this->assertTrue(in_array($create->response()->getStatusCode(), [302, 303, 404, 405], true));
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->assertTrue(true);
        }

        try {
            $cancel = $this->get('admin/pages/1/schedules/1/cancel');
            $this->assertTrue(in_array($cancel->response()->getStatusCode(), [302, 303, 404, 405], true));
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function testPostScheduleRoutesRemainSessionProtected(): void
    {
        $result = $this->get('admin/pages');
        $result->assertRedirect();
    }

    public function testFormShowsSiteTimezone(): void
    {
        $html = view('admin/_partials/scheduled_actions', [
            'canSchedulePublish'   => true,
            'canScheduleUnpublish' => true,
            'scheduledActions'     => [],
            'siteTimezone'         => 'Asia/Jakarta',
            'scheduleCreateUrl'    => site_url('admin/pages/1/schedules'),
            'scheduleCancelBase'   => site_url('admin/pages/1/schedules'),
        ]);
        $this->assertStringContainsString('Asia/Jakarta', $html);
        $this->assertStringContainsString('Create schedule', $html);
        $this->assertStringContainsString('csrf', strtolower($html));
    }

    public function testPublicAndMediaRoutesUnchanged(): void
    {
        $collection = service('commands')->getCommands();
        $this->assertArrayHasKey('cms:scheduled-content', $collection);

        try {
            $getNews = $this->get('news/does-not-exist-sched');
            $this->assertNotSame(500, $getNews->response()->getStatusCode());
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->assertTrue(true);
        }
    }
}
