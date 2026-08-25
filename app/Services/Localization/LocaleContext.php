<?php

declare(strict_types=1);

namespace App\Services\Localization;

/**
 * Request-scoped locale context set by LocaleFilter (ADR-003 / ADR-024).
 */
final class LocaleContext
{
    private ?string $requestedLocale = null;

    private bool $secondaryPrefix = false;

    public function set(string $requestedLocale, bool $secondaryPrefix): void
    {
        $this->requestedLocale = strtolower(trim($requestedLocale));
        $this->secondaryPrefix   = $secondaryPrefix;
    }

    public function requestedLocale(): ?string
    {
        return $this->requestedLocale;
    }

    public function hasSecondaryPrefix(): bool
    {
        return $this->secondaryPrefix;
    }

    public function reset(): void
    {
        $this->requestedLocale = null;
        $this->secondaryPrefix = false;
    }
}
