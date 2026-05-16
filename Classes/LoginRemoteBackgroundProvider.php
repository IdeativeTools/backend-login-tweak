<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin;

/**
 * Stored as loginBackgroundRemoteProvider when loginBackgroundSource is remote.
 *
 * @see locallang_be.xlf settings.loginBackgroundRemoteProvider.{labelSuffix}
 */
final class LoginRemoteBackgroundProvider
{
    public const string PICSUM = 'picsum';

    public const string DANIELPETRICA = 'danielpetrica';

    /** @var array<string, string> */
    private const array VALUE_TO_LABEL_SUFFIX = [
        self::PICSUM => 'picsum',
        self::DANIELPETRICA => 'danielpetrica',
    ];

    public static function isValid(string $value): bool
    {
        return isset(self::VALUE_TO_LABEL_SUFFIX[$value]);
    }

    /**
     * @return list<array{value: string, labelSuffix: string}>
     */
    public static function optionsForForm(): array
    {
        $options = [];
        foreach (self::VALUE_TO_LABEL_SUFFIX as $value => $labelSuffix) {
            $options[] = ['value' => $value, 'labelSuffix' => $labelSuffix];
        }

        return $options;
    }
}
