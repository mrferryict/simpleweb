<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Operational auth throttling (ADR-026 / DOC-03 §13).
 *
 * Product sources leave capacity/window UNDEFINED. This Config holds only
 * explicitly supplied operational values (env). There are no invented
 * numeric application defaults. Unconfigured surfaces fail closed in
 * AuthThrottleService.
 *
 * Env keys (both required per surface or that surface is unconfigured):
 * - auth.throttle.login.capacity / auth.throttle.login.seconds
 * - auth.throttle.password_reset_request.capacity / .seconds
 * - auth.throttle.password_reset_verify.capacity / .seconds
 * - auth.throttle.admin_recovery.capacity / .seconds
 */
class AuthThrottle extends BaseConfig
{
    /**
     * @var array{capacity: int, seconds: int}|null
     */
    public ?array $login = null;

    /**
     * @var array{capacity: int, seconds: int}|null
     */
    public ?array $passwordResetRequest = null;

    /**
     * @var array{capacity: int, seconds: int}|null
     */
    public ?array $passwordResetVerify = null;

    /**
     * @var array{capacity: int, seconds: int}|null
     */
    public ?array $adminRecovery = null;

    public function __construct()
    {
        parent::__construct();

        $this->login                  = $this->resolveSurface('auth.throttle.login');
        $this->passwordResetRequest   = $this->resolveSurface('auth.throttle.password_reset_request');
        $this->passwordResetVerify    = $this->resolveSurface('auth.throttle.password_reset_verify');
        $this->adminRecovery          = $this->resolveSurface('auth.throttle.admin_recovery');
    }

    /**
     * @return array{capacity: int, seconds: int}|null
     */
    private function resolveSurface(string $prefix): ?array
    {
        $capacityRaw = env($prefix . '.capacity', null);
        $secondsRaw  = env($prefix . '.seconds', null);

        if (! is_string($capacityRaw) && ! is_int($capacityRaw)) {
            return null;
        }
        if (! is_string($secondsRaw) && ! is_int($secondsRaw)) {
            return null;
        }

        $capacityRaw = trim((string) $capacityRaw);
        $secondsRaw  = trim((string) $secondsRaw);
        if ($capacityRaw === '' || $secondsRaw === '') {
            return null;
        }

        $capacity = filter_var($capacityRaw, FILTER_VALIDATE_INT);
        $seconds  = filter_var($secondsRaw, FILTER_VALIDATE_INT);
        if ($capacity === false || $seconds === false || $capacity < 1 || $seconds < 1) {
            return null;
        }

        return [
            'capacity' => $capacity,
            'seconds'  => $seconds,
        ];
    }
}
