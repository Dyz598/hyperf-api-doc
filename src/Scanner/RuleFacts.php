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

namespace HyperfApiDoc\Scanner;

use BackedEnum;

/**
 * OpenAPI facts a RuleDetector reports for one validation rule.
 */
final class RuleFacts
{
    /**
     * @param null|class-string<BackedEnum> $enumClass enum class backing the rule
     * @param null|array<int, mixed> $enum explicit enum values (when no class is known)
     * @param null|string $type OpenAPI type
     * @param null|string $format OpenAPI format
     */
    public function __construct(
        public readonly ?string $enumClass = null,
        public readonly ?array $enum = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
    ) {}

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    public static function enumClass(string $enumClass): self
    {
        return new self(enumClass: $enumClass);
    }

    /**
     * @param array<int, mixed> $values
     */
    public static function enumValues(array $values): self
    {
        return new self(enum: array_values($values));
    }
}
