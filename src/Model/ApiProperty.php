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

namespace HyperfApiDoc\Model;

/**
 * A single (possibly nested) schema property.
 *
 * Scalar constraints are nullable so that "not set" can be distinguished
 * from an explicit override when merging inferred and explicit values.
 */
class ApiProperty
{
    public ?string $type = null;

    public ?string $format = null;

    public ?string $description = null;

    public mixed $example = null;

    public bool $hasExample = false;

    public ?array $enum = null;

    /** Backing enum class when $enum came from an enum. */
    public ?string $enumClass = null;

    public ?bool $nullable = null;

    public float|int|null $minimum = null;

    public float|int|null $maximum = null;

    public ?int $minLength = null;

    public ?int $maxLength = null;

    public ?int $minItems = null;

    public ?int $maxItems = null;

    public ?string $pattern = null;

    public mixed $default = null;

    public bool $hasDefault = false;

    public ?bool $deprecated = null;

    public ?bool $readOnly = null;

    public ?bool $writeOnly = null;

    /** Array item schema when type is array. */
    public ?ApiProperty $items = null;

    /** Nested schema class when the property holds another documented object. */
    public ?string $refClass = null;

    /** @var null|array<string, ApiProperty> nested properties when type is object */
    public ?array $properties = null;

    /** @var null|array<int, string> required names for nested objects */
    public ?array $required = null;

    public function type(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function format(?string $format): static
    {
        $this->format = $format;
        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function example(mixed $example): static
    {
        $this->example = $example;
        $this->hasExample = true;
        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;
        $this->hasDefault = true;
        return $this;
    }

    public function enum(?array $enum): static
    {
        $this->enum = $enum;
        return $this;
    }

    public function enumClass(?string $enumClass): static
    {
        $this->enumClass = $enumClass;
        return $this;
    }

    public function nullable(?bool $nullable): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function minimum(float|int|null $minimum): static
    {
        $this->minimum = $minimum;
        return $this;
    }

    public function maximum(float|int|null $maximum): static
    {
        $this->maximum = $maximum;
        return $this;
    }

    public function minLength(?int $minLength): static
    {
        $this->minLength = $minLength;
        return $this;
    }

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function minItems(?int $minItems): static
    {
        $this->minItems = $minItems;
        return $this;
    }

    public function maxItems(?int $maxItems): static
    {
        $this->maxItems = $maxItems;
        return $this;
    }

    public function pattern(?string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function deprecated(?bool $deprecated): static
    {
        $this->deprecated = $deprecated;
        return $this;
    }

    public function readOnly(?bool $readOnly): static
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    public function writeOnly(?bool $writeOnly): static
    {
        $this->writeOnly = $writeOnly;
        return $this;
    }

    /**
     * Reference another documented class as this property's schema.
     */
    public function ref(string $class): static
    {
        $this->refClass = $class;
        return $this;
    }

    /**
     * Set the item schema of an array property.
     */
    public function items(self $items): static
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Overlay another property onto this one. Set values from the other
     * property win; nested structures are merged recursively.
     */
    public function merge(self $other): static
    {
        foreach (get_object_vars($other) as $key => $value) {
            if ($value === null) {
                continue;
            }

            switch ($key) {
                case 'hasExample':
                case 'hasDefault':
                    if ($value === true) {
                        $this->{$key} = true;
                    }
                    break;
                case 'properties':
                    $this->properties ??= [];
                    foreach ($value as $name => $property) {
                        $this->properties[$name] = isset($this->properties[$name])
                            ? $this->properties[$name]->merge($property)
                            : $property;
                    }
                    break;
                case 'required':
                    $this->required = array_values(array_unique([
                        ...($this->required ?? []),
                        ...$value,
                    ]));
                    break;
                case 'items':
                    $this->items = $this->items !== null
                        ? $this->items->merge($value)
                        : $value;
                    break;
                default:
                    $this->{$key} = $value;
                    break;
            }
        }

        return $this;
    }
}
