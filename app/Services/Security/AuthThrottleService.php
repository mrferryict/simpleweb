<?php

declare(strict_types=1);

namespace App\Services\Security;

use Config\AuthThrottle;
use CodeIgniter\Throttle\Throttler;

/**
 * Auth-surface throttling via CI4 Throttler (ADR-026 / DOC-03 §13).
 *
 * CI4 Throttler::check() requires explicit capacity and seconds — it has no
 * framework default rate. When operational Config for a surface is absent or
 * invalid, this service fails closed (deny) so required surfaces stay protected
 * without inventing product numeric policy.
 */
final class AuthThrottleService
{
    public function __construct(
        private readonly Throttler $throttler,
        private readonly AuthThrottle $config,
    ) {
    }

    /**
     * @param 'login'|'password_reset_request'|'password_reset_verify'|'admin_recovery' $surface
     */
    public function allow(string $surface, string $ipAddress): bool
    {
        $limits = match ($surface) {
            'login'                  => $this->config->login,
            'password_reset_request' => $this->config->passwordResetRequest,
            'password_reset_verify'  => $this->config->passwordResetVerify,
            'admin_recovery'         => $this->config->adminRecovery,
            default                  => null,
        };

        if (! $this->isConfigured($limits)) {
            // Fail closed: surface must be throttled; numbers are UNDEFINED in product sources.
            log_message('warning', 'Auth throttle operational limits are not configured for surface; denying request.');

            return false;
        }

        /** @var array{capacity: int, seconds: int} $limits */
        $key = $surface . '_' . $ipAddress;

        return $this->throttler->check(
            $key,
            $limits['capacity'],
            $limits['seconds'],
        );
    }

    /**
     * @param array{capacity?: mixed, seconds?: mixed}|null $limits
     *
     * @phpstan-assert-if-true array{capacity: int, seconds: int} $limits
     */
    private function isConfigured(?array $limits): bool
    {
        if ($limits === null) {
            return false;
        }

        if (! isset($limits['capacity'], $limits['seconds'])) {
            return false;
        }

        $capacity = $limits['capacity'];
        $seconds  = $limits['seconds'];

        return is_int($capacity) && is_int($seconds) && $capacity >= 1 && $seconds >= 1;
    }
}
