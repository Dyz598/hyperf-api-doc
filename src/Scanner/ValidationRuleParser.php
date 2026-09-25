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

use HyperfApiDoc\Contract\RuleDetector;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Support\BackedEnums;

/**
 * Converts Hyperf/Laravel validation rules into an ApiSchema tree: dot
 * notation ("customer.name"), array wildcards ("foo.*.bar"), "in:" lists,
 * and enums reported by the RuleDetector chain.
 */
class ValidationRuleParser
{
    /** Pseudo rules carrying detector results through the build passes. */
    private const ENUM_RULE = '@enum';

    private const ENUM_VALUES_RULE = '@enumValues';

    private const TYPE_RULE = '@type';

    private const FORMAT_RULE = '@format';

    /** @var array<string, array{0: string, 1: string}> rule name => [type, format] */
    private const FORMAT_TYPES = [
        'date' => ['string', 'date'],
        'datetime' => ['string', 'date-time'],
        'email' => ['string', 'email'],
        'uuid' => ['string', 'uuid'],
        'ulid' => ['string', 'uuid'],
        'url' => ['string', 'uri'],
    ];

    /** @var array<int, RuleDetector> */
    protected array $detectors;

    /**
     * @param array<int, class-string<RuleDetector>|RuleDetector> $detectors custom detectors, after the built-in enum detector
     */
    public function __construct(array $detectors = [])
    {
        $this->detectors = [new EnumRuleDetector(), ...$detectors];
    }

    public function parse(array $rules): ApiSchema
    {
        $schema = new ApiSchema();

        foreach ($rules as $field => $rule) {
            $field = (string) $field;

            if ($field === '') {
                continue;
            }

            $this->insert($schema->properties, $schema->required, explode('.', $field), $this->normalize($rule, $field));
        }

        $schema->required = array_values(array_unique($schema->required));

        return $schema;
    }

    /**
     * Normalize a rule definition into a list of [name, param] pairs.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function normalize(array|string $rules, string $field): array
    {
        $list = is_array($rules) ? $rules : explode('|', $rules);
        $parts = [];

        foreach ($list as $rule) {
            if (is_object($rule)) {
                $parts = [...$parts, ...$this->normalizeObjectRule($rule)];
                continue;
            }

            if (! is_string($rule) || $rule === '') {
                continue;
            }

            if (str_contains($rule, ':')) {
                $parts[] = explode(':', $rule, 2);
            } else {
                $parts[] = [$rule, null];
            }
        }

        return $parts;
    }

    /**
     * Translate a rule object through the detector chain.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function normalizeObjectRule(object $rule): array
    {
        $facts = $this->detectFacts($rule);

        if ($facts === null) {
            return [];
        }

        $parts = [];

        if ($facts->enumClass !== null) {
            $parts[] = [self::ENUM_RULE, $facts->enumClass];
        }

        if ($facts->enum !== null) {
            $parts[] = [self::ENUM_VALUES_RULE, $facts->enum];
        }

        if ($facts->type !== null) {
            $parts[] = [self::TYPE_RULE, $facts->type];
        }

        if ($facts->format !== null) {
            $parts[] = [self::FORMAT_RULE, $facts->format];
        }

        return $parts;
    }

    private function detectFacts(object|string $rule): ?RuleFacts
    {
        foreach ($this->detectors as $detector) {
            $facts = $detector->detect($rule);

            if ($facts !== null) {
                return $facts;
            }
        }

        return null;
    }

    /**
     * Insert one field into the nested property tree, handling wildcards.
     *
     * @param array<string, ApiProperty> $properties
     * @param array<int, string> $required
     * @param array<int, string> $segments
     * @param array<int, array{0: string, 1: mixed}> $parts
     */
    private function insert(array &$properties, array &$required, array $segments, array $parts): void
    {
        $segment = array_shift($segments);

        if ($segments === []) {
            $this->apply($properties, $required, $segment, $parts);
            return;
        }

        $next = $segments[0];

        if ($next === '*') {
            array_shift($segments);

            $property = $properties[$segment] ??= new ApiProperty();
            $property->type = 'array';
            $property->items ??= new ApiProperty();

            if ($segments === []) {
                // "foo.*" — the rules describe the array items.
                [$item] = $this->buildProperty($parts);
                $property->items = $property->items->properties === null || $property->items->properties === []
                    ? $item
                    : $property->items->merge($item);
            } else {
                // "foo.*.bar" — items are objects; keep descending.
                $property->items->type = 'object';
                $property->items->properties ??= [];
                $property->items->required ??= [];
                $this->insert($property->items->properties, $property->items->required, $segments, $parts);
            }

            return;
        }

        $property = $properties[$segment] ??= new ApiProperty();
        $property->type = 'object';
        $property->properties ??= [];
        $property->required ??= [];
        $this->insert($property->properties, $property->required, $segments, $parts);
    }

    /**
     * Apply parsed rules as a leaf property.
     *
     * @param array<string, ApiProperty> $properties
     * @param array<int, string> $required
     * @param array<int, array{0: string, 1: mixed}> $parts
     */
    private function apply(array &$properties, array &$required, string $name, array $parts): void
    {
        [$property, $isRequired] = $this->buildProperty($parts);

        $properties[$name] = isset($properties[$name])
            ? $properties[$name]->merge($property)
            : $property;

        if ($isRequired && ! in_array($name, $required, true)) {
            $required[] = $name;
        }
    }

    /**
     * Build a property from parsed rules.
     *
     * @param array<int, array{0: string, 1: mixed}> $parts
     * @return array{0: ApiProperty, 1: bool} property and required flag
     */
    private function buildProperty(array $parts): array
    {
        $property = new ApiProperty();
        $required = false;
        $type = null;
        $enumClass = null;

        // Pass 1: type detection so order-independent constraints apply correctly.
        foreach ($parts as [$name, $param]) {
            $type ??= match ($name) {
                'string', 'alpha', 'alpha_num', 'alpha_dash' => 'string',
                'integer', 'int' => 'integer',
                'numeric', 'float', 'decimal' => 'number',
                'boolean', 'bool' => 'boolean',
                'array' => 'array',
                default => null,
            };

            if (isset(self::FORMAT_TYPES[$name])) {
                $type ??= self::FORMAT_TYPES[$name][0];
                $property->format ??= self::FORMAT_TYPES[$name][1];
            }

            // Unknown string rule names may be custom enum rules; ask the detectors.
            if ($enumClass === null) {
                $enumClass = $this->detectFacts($name)?->enumClass;
            }
        }

        // Detector pseudo rules.
        foreach ($parts as [$name, $param]) {
            if ($name === self::ENUM_RULE && is_string($param)) {
                $enumClass = $param;
            } elseif ($name === self::ENUM_VALUES_RULE && is_array($param)) {
                $property->enum ??= $param;
            } elseif ($name === self::TYPE_RULE && is_string($param)) {
                $type ??= $param;
            } elseif ($name === self::FORMAT_RULE && is_string($param)) {
                $property->format ??= $param;
            }
        }

        if ($enumClass !== null) {
            [$values, $enumType] = $this->enumDefinition($enumClass);
            $property->enum = $values;
            $property->enumClass = $enumClass;
            $type ??= $enumType;
        }

        // Pass 2: constraints.
        foreach ($parts as [$name, $param]) {
            switch ($name) {
                case 'required':
                case 'present':
                case 'filled':
                    $required = true;
                    break;
                case 'nullable':
                case 'sometimes':
                    $property->nullable = true;
                    break;
                case 'max':
                case 'min':
                    if (is_numeric($param)) {
                        $value = strpos((string) $param, '.') !== false ? (float) $param : (int) $param;
                        $bound = $name === 'max' ? 'maximum' : 'minimum';
                        $lengthBound = $name === 'max' ? 'maxLength' : 'minLength';
                        $itemsBound = $name === 'max' ? 'maxItems' : 'minItems';

                        match ($type) {
                            'integer', 'number' => $property->{$bound} = $value,
                            'array' => $property->{$itemsBound} = $value,
                            default => $property->{$lengthBound} = $value,
                        };
                    }
                    break;
                case 'size':
                    if (is_numeric($param)) {
                        $value = (int) $param;
                        match ($type) {
                            'integer', 'number' => $property->minimum = $property->maximum = $value,
                            'array' => $property->minItems = $property->maxItems = $value,
                            default => $property->minLength = $property->maxLength = $value,
                        };
                    }
                    break;
                case 'between':
                    if (is_string($param) && str_contains($param, ',')) {
                        [$from, $to] = array_map(
                            static fn ($v) => strpos($v, '.') !== false ? (float) $v : (int) $v,
                            explode(',', $param)
                        );

                        match ($type) {
                            'integer', 'number' => [$property->minimum, $property->maximum] = [$from, $to],
                            'array' => [$property->minItems, $property->maxItems] = [$from, $to],
                            default => [$property->minLength, $property->maxLength] = [$from, $to],
                        };
                    }
                    break;
                case 'in':
                    if (is_string($param)) {
                        $values = explode(',', $param);
                        if (in_array($type, ['integer', 'number'], true)) {
                            $values = array_map(
                                static fn (string $v) => str_contains($v, '.') ? (float) $v : (int) $v,
                                $values
                            );
                        }
                        $property->enum = $values;
                        $type ??= preg_match('/^\d+(,\d+)*$/', $param) ? 'integer' : 'string';
                    }
                    break;
                case 'regex':
                    if (is_string($param)) {
                        $property->pattern = preg_match('#^(/)(.*)\1[a-z]*$#', $param, $m) ? $m[2] : $param;
                    }
                    break;
                case 'date_format':
                    if (is_string($param)) {
                        $property->format = str_contains($param, 'H') ? 'date-time' : 'date';
                        $type ??= 'string';
                    }
                    break;
                case 'file':
                case 'image':
                case 'mimes':
                case 'mimetypes':
                    $type ??= 'string';
                    $property->format = 'binary';
                    break;
            }
        }

        if ($type !== null) {
            $property->type = $type;
        }

        return [$property, $required];
    }

    /**
     * @return array{0: array<int, int|string>, 1: string} values and OpenAPI type
     */
    private function enumDefinition(string $class): array
    {
        if (! enum_exists($class)) {
            throw new InvalidConfigurationException(sprintf(
                '[%s] is not an enum class.',
                $class
            ));
        }

        $definition = BackedEnums::definition($class);

        if ($definition === null) {
            throw new InvalidConfigurationException(sprintf(
                'Unit enum [%s] has no backed values; use a backed enum or explicit "in:" values instead.',
                $class
            ));
        }

        return $definition;
    }
}
