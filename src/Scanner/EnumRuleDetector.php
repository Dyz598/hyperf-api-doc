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
use HyperfApiDoc\Contract\RuleDetector;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Detects enum facts from validation rules:
 *
 *  - Rule::enum() objects (Hyperf or Illuminate validation)
 *  - backed-enum instances used directly as rules
 *  - custom rule objects holding a backed enum — class-string, instance,
 *    or list of cases (e.g. new InEnum(Gender::class)) — zero config
 *  - string rule names mapped via the constructor
 *
 * Always active with an empty map; custom detectors registered via the
 * "rule_detectors" config run after it.
 *
 * @phpstan-type EnumMap array<string, class-string<BackedEnum>>
 */
class EnumRuleDetector implements RuleDetector
{
    /**
     * @param EnumMap $map string rule name => backing enum class
     */
    public function __construct(protected array $map = []) {}

    public function detect(object|string $rule): ?RuleFacts
    {
        if (is_string($rule)) {
            return isset($this->map[$rule])
                ? RuleFacts::enumClass($this->map[$rule])
                : null;
        }

        if (in_array($rule::class, ['Hyperf\Validation\Rules\Enum', 'Illuminate\Validation\Rules\Enum'], true)) {
            $reflection = new ReflectionClass($rule);

            if ($reflection->hasProperty('type')) {
                $type = (new ReflectionProperty($rule, 'type'))->getValue($rule);

                if (is_string($type) && enum_exists($type)) {
                    return RuleFacts::enumClass($type);
                }
            }

            return null;
        }

        if ($rule instanceof BackedEnum) {
            return RuleFacts::enumClass($rule::class);
        }

        return $this->detectFromProperties($rule);
    }

    private function detectFromProperties(object $rule): ?RuleFacts
    {
        try {
            foreach ((new ReflectionClass($rule))->getProperties() as $property) {
                $property->setAccessible(true);

                try {
                    $value = $property->getValue($rule);
                } catch (Throwable) {
                    continue;
                }

                if (is_string($value) && enum_exists($value) && is_subclass_of($value, BackedEnum::class)) {
                    return RuleFacts::enumClass($value);
                }

                if ($value instanceof BackedEnum) {
                    return RuleFacts::enumClass($value::class);
                }

                if (is_array($value) && $value !== [] && $value[0] instanceof BackedEnum) {
                    return RuleFacts::enumValues(array_map(
                        static fn (BackedEnum $case) => $case->value,
                        $value
                    ));
                }
            }
        } catch (Throwable) {
            // Unreadable rule object; nothing to detect.
        }

        return null;
    }
}
