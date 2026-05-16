<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Ideative\T3BeLogin\ExtensionConfiguration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders the Installation Tool extension configuration control for {@see ext_conf_template.txt} loginFootnote.
 *
 * The Install Tool parses ext_conf before merging {@see ExtensionConfiguration} values into each field.
 * For {@code type=user} the HTML is generated during that parse step, so {@code fieldValue} is still the
 * template default. We always read the live stored value here so the textarea shows and submits the
 * correct text.
 *
 * @internal
 */
final class LoginFootnoteExtConfField
{
    private const string EXTENSION_KEY = 'id_be_login';

    private const string FIELD_KEY = 'loginFootnote';

    /**
     * @param array{fieldName?: string, fieldValue?: string} $params
     */
    public function render(array $params): string
    {
        $fieldValue = (string)($params['fieldValue'] ?? '');
        try {
            $stored = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(
                self::EXTENSION_KEY,
                self::FIELD_KEY
            );
            if (is_scalar($stored)) {
                $fieldValue = (string)$stored;
            }
        } catch (ExtensionConfigurationPathDoesNotExistException|ExtensionConfigurationExtensionNotConfiguredException) {
            // Keep ext_conf default / empty until LocalConfiguration is synced.
        }

        $id = 'em-' . self::EXTENSION_KEY . '-' . self::FIELD_KEY;
        $escapedValue = htmlspecialchars($fieldValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<textarea class="form-control" id="%s" name="%s" rows="3" maxlength="200" spellcheck="false">%s</textarea>',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars(self::FIELD_KEY, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $escapedValue
        );
    }
}
