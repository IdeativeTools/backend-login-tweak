<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Ideative\T3BeLogin;

/**
 * Stored in extension configuration as loginBackgroundSource.
 *
 * @see locallang_be.xlf settings.loginBackgroundSource.{labelSuffix}
 */
final class LoginBackgroundSource
{
    public const string LOCAL = 'local';

    /** Random raster image from a FAL folder (direct children only; JPG, JPEG, PNG, WebP). */
    public const string FAL_FOLDER = 'fal_folder';

    public const string REMOTE = 'remote';

    /** @var array<string, string> */
    private const array VALUE_TO_LABEL_SUFFIX = [
        self::LOCAL => 'local',
        self::FAL_FOLDER => 'falFolder',
        self::REMOTE => 'remote',
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
