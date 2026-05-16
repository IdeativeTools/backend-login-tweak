<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin;

/**
 * Login panel placement: stored value and matching locallang_be.xlf key suffix
 * (settings.loginBoxPosition.{labelSuffix}).
 */
final class LoginBoxPosition
{
    /**
     * @var array<string, string>
     */
    private const array VALUE_TO_LABEL_SUFFIX = [
        'center' => 'center',
        'top-left' => 'topLeft',
        'top-center' => 'topCenter',
        'top-right' => 'topRight',
        'middle-left' => 'middleLeft',
        'middle-right' => 'middleRight',
        'bottom-left' => 'bottomLeft',
        'bottom-center' => 'bottomCenter',
        'bottom-right' => 'bottomRight',
    ];

    public static function isValid(string $value): bool
    {
        return isset(self::VALUE_TO_LABEL_SUFFIX[$value]);
    }

    /**
     * Position values in 3×3 grid order (rows left-to-right, top-to-bottom), matching the backend UI.
     *
     * @return list<string>
     */
    public static function gridOrder(): array
    {
        return [
            'top-left',
            'top-center',
            'top-right',
            'middle-left',
            'center',
            'middle-right',
            'bottom-left',
            'bottom-center',
            'bottom-right',
        ];
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
