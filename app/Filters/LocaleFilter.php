<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Localization\LocaleContext;
use App\Services\SettingService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route-driven locale context only — no content lookup (ADR-003 / ADR-024).
 */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        $settingService = service('settingService');
        /** @var LocaleContext $localeContext */
        $localeContext = service('localeContext');

        $primary   = $settingService->primaryLocale();
        $secondary = $settingService->secondaryLocale();

        $first = $segments[0] ?? '';

        if ($first === 'id') {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($secondary !== null && $first === $secondary) {
            if (! $settingService->isSecondaryEnabled()) {
                throw PageNotFoundException::forPageNotFound();
            }

            $localeContext->set($secondary, true);

            return null;
        }

        $localeContext->set($primary, false);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
