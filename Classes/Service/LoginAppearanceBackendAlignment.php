<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin\Service;

use Ideative\T3BeLogin\LoginBackgroundSource;
use Ideative\T3BeLogin\LoginHexColor;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Keeps EXT:backend login-related extension settings and id_be_login in agreement for shared fields.
 */
final readonly class LoginAppearanceBackendAlignment
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * When EXT:backend has a value for a shared option, it overrides id_be_login for display and for login rendering.
     *
     * @param array<string, string> $settings
     * @return array<string, string>
     */
    public function mergeBackendIntoModuleSettings(array $settings): array
    {
        try {
            $backend = $this->extensionConfiguration->get('backend');
        } catch (Throwable) {
            return $settings;
        }
        if (!is_array($backend)) {
            return $settings;
        }

        $source = $settings['loginBackgroundSource'] ?? LoginBackgroundSource::LOCAL;
        $skipLocalBackgroundFromBackend = $source === LoginBackgroundSource::REMOTE
            || $source === LoginBackgroundSource::FAL_FOLDER;

        if (!$skipLocalBackgroundFromBackend) {
            $path = trim((string)($backend['loginBackgroundImage'] ?? ''));
            if ($path !== '') {
                $settings['loginBackgroundImage'] = $path;
            }
        }

        $logo = trim((string)($backend['loginLogo'] ?? ''));
        if ($logo !== '') {
            $settings['loginLogo'] = $logo;
        }

        $highlight = trim((string)($backend['loginHighlightColor'] ?? ''));
        if ($highlight !== '' && LoginHexColor::isValid($highlight)) {
            $settings['loginButtonColor'] = $highlight;
        }

        foreach (['loginLogoAlt' => 'loginLogoAlt', 'loginFootnote' => 'loginFootnote'] as $ourKey => $backendKey) {
            if (($settings[$ourKey] ?? '') !== '') {
                continue;
            }
            if (!array_key_exists($backendKey, $backend)) {
                continue;
            }
            $value = trim((string)$backend[$backendKey]);
            if ($value !== '') {
                $settings[$ourKey] = $value;
            }
        }

        return $settings;
    }
}
