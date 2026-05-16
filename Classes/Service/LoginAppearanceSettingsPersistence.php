<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Ideative\T3BeLogin\Service;

use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Persists EXT:id_be_login values to LocalConfiguration.
 *
 * ExtensionConfiguration::get() is the public read API; ::set() is marked @internal in core but is the
 * documented write path for runtime updates (see core method docblock). This class keeps that call in one place.
 *
 * Shared login appearance keys are mirrored to EXT:backend so Site Settings and this module stay aligned.
 */
final readonly class LoginAppearanceSettingsPersistence
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @param array<string, string> $settings
     */
    public function write(array $settings): void
    {
        $this->extensionConfiguration->set('id_be_login', $settings);
    }

    /**
     * Mirror fields that also exist under EXT:backend (Install Tool / Settings → backend extension).
     *
     * Maps id_be_login `loginButtonColor` to backend `loginHighlightColor` (core option name).
     *
     * @param array<string, string> $settings Sanitized id_be_login settings (same shape as passed to {@see write()}).
     */
    public function syncBackendExtensionSharedLoginAppearance(array $settings): void
    {
        try {
            $backend = $this->extensionConfiguration->get('backend');
        } catch (Throwable) {
            $backend = [];
        }
        if (!is_array($backend)) {
            $backend = [];
        }

        $backend['loginBackgroundImage'] = $settings['loginBackgroundImage'] ?? '';
        $backend['loginLogo'] = $settings['loginLogo'] ?? '';
        $backend['loginHighlightColor'] = $settings['loginButtonColor'] ?? '';

        $this->extensionConfiguration->set('backend', $backend);
    }
}
