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

use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\Concerns\HasExtensions;
use HyperfApiDoc\Support\BackedEnums;
use UnitEnum;

/**
 * An object schema with named properties.
 *
 * Fluent customization methods upsert properties: they create the property
 * when absent and patch only the provided facets otherwise. This makes the
 * same class usable for both inferred schemas (patching) and standalone
 * schema definitions (creating).
 */
class ApiSchema
{
    use HasExtensions;

    public ?string $title = null;

    public ?string $description = null;

    /** @var array<string, ApiProperty> */
    public array $properties = [];

    /** @var array<int, string> */
    public array $required = [];

    /** @var array<int, class-string> additional field classes, merged at render */
    public array $additionalFieldClasses = [];

    public function title(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get or create a property by name.
     */
    public function property(string $name): ApiProperty
    {
        return $this->properties[$name] ??= new ApiProperty();
    }

    /**
     * @param array<string, string> $descriptions
     */
    public function descriptions(array $descriptions): static
    {
        foreach ($descriptions as $name => $description) {
            $this->property((string) $name)->description((string) $description);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $examples
     */
    public function examples(array $examples): static
    {
        foreach ($examples as $name => $example) {
            $this->property((string) $name)->example($example);
        }

        return $this;
    }

    /**
     * @param array<string, array|class-string<UnitEnum>> $enums enum class or explicit values per field
     */
    public function enums(array $enums): static
    {
        foreach ($enums as $name => $enum) {
            $this->applyEnum($this->property((string) $name), $enum, (string) $name);
        }

        return $this;
    }

    /**
     * Raw fallback for every supported constraint.
     *
     * @param array<string, array<string, mixed>> $fields
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $name => $definitions) {
            $property = $this->property((string) $name);

            foreach ($definitions as $key => $value) {
                match ($key) {
                    'type' => $property->type((string) $value),
                    'format' => $property->format($value === null ? null : (string) $value),
                    'description' => $property->description($value === null ? null : (string) $value),
                    'example' => $property->example($value),
                    'default' => $property->default($value),
                    'enum' => $this->applyEnum($property, $value, (string) $name),
                    'nullable' => $property->nullable((bool) $value),
                    'minimum' => $property->minimum($value),
                    'maximum' => $property->maximum($value),
                    'minLength' => $property->minLength($value === null ? null : (int) $value),
                    'maxLength' => $property->maxLength($value === null ? null : (int) $value),
                    'minItems' => $property->minItems($value === null ? null : (int) $value),
                    'maxItems' => $property->maxItems($value === null ? null : (int) $value),
                    'pattern' => $property->pattern($value === null ? null : (string) $value),
                    'deprecated' => $property->deprecated((bool) $value),
                    'readOnly' => $property->readOnly((bool) $value),
                    'writeOnly' => $property->writeOnly((bool) $value),
                    default => throw new InvalidConfigurationException(sprintf(
                        'Unknown field constraint [%s] for property [%s].',
                        $key,
                        $name
                    )),
                };
            }
        }

        return $this;
    }

    /**
     * Mark properties as required.
     */
    public function require(string ...$names): static
    {
        $this->required = array_values(array_unique([...$this->required, ...$names]));
        return $this;
    }

    /**
     * Overlay another schema onto this one; explicit values win and
     * properties are merged recursively.
     */
    public function merge(self $other): static
    {
        $this->title ??= $other->title;
        $this->description ??= $other->description;

        foreach ($other->properties as $name => $property) {
            $this->properties[$name] = isset($this->properties[$name])
                ? $this->properties[$name]->merge($property)
                : $property;
        }

        if ($other->required !== []) {
            $this->required = array_values(array_unique([...$this->required, ...$other->required]));
        }

        return $this;
    }

    /**
     * Merge the fields of documented schema classes into this one at
     * generation time; colliding properties union recursively.
     */
    public function additionalFields(string ...$classes): static
    {
        foreach ($classes as $class) {
            if (! in_array($class, $this->additionalFieldClasses, true)) {
                $this->additionalFieldClasses[] = $class;
            }
        }

        return $this;
    }

    /**
     * Classes referenced by this schema: property refs, array item
     * refs, and additional field classes.
     *
     * @return array<int, class-string>
     */
    public function referencedClasses(): array
    {
        $classes = $this->additionalFieldClasses;

        foreach ($this->properties as $property) {
            $classes = [...$classes, ...$this->propertyRefs($property)];
        }

        return array_values(array_unique($classes));
    }

    protected function resolveEnum(mixed $enum, string $field): array
    {
        if (is_string($enum) && enum_exists($enum)) {
            $definition = BackedEnums::definition($enum);

            if ($definition === null) {
                throw new InvalidConfigurationException(sprintf(
                    'Unit enum [%s] used for field [%s] has no backed values; configure explicit values instead.',
                    $enum,
                    $field
                ));
            }

            return $definition[0];
        }

        if (is_array($enum)) {
            return array_values($enum);
        }

        throw new InvalidConfigurationException(sprintf(
            'Invalid enum definition for field [%s]: expected enum class-string or array of values, got %s.',
            $field,
            get_debug_type($enum)
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function propertyRefs(ApiProperty $property): array
    {
        $classes = $property->refClass !== null ? [$property->refClass] : [];

        if ($property->items !== null) {
            $classes = [...$classes, ...$this->propertyRefs($property->items)];
        }

        foreach ($property->properties ?? [] as $child) {
            $classes = [...$classes, ...$this->propertyRefs($child)];
        }

        return $classes;
    }

    private function applyEnum(ApiProperty $property, mixed $enum, string $field): void
    {
        $property->enum($this->resolveEnum($enum, $field));

        if (is_string($enum) && enum_exists($enum)) {
            $property->enumClass($enum);
        }
    }
}
