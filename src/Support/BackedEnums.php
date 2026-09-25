<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfApiDoc\Support;

use BackedEnum;
use ReflectionEnum;

/**
 * Shared facts about backed enums: their case values and the OpenAPI
 * type of the backing scalar.
 */
final class BackedEnums
{
    /**
     * Case values and OpenAPI type of a backed enum; null for non-enums
     * and unit enums, which carry no typed values to document.
     *
     * @param class-string $class
     * @return null|array{0: array<int, int|string>, 1: string}
     */
    public static function definition(string $class): ?array
    {
        if (! is_subclass_of($class, BackedEnum::class)) {
            return null;
        }

        $backing = (string) (new ReflectionEnum($class))->getBackingType();

        return [
            array_map(static fn (BackedEnum $case) => $case->value, $class::cases()),
            $backing === 'int' ? 'integer' : 'string',
        ];
    }
}
