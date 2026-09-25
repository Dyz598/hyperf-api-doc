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

namespace HyperfApiDoc\Generator;

use DateTimeInterface;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Contract\RuleDetector;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Scanner\ValidationRuleParser;
use HyperfApiDoc\Support\BackedEnums;
use HyperfApiDoc\Support\ConfigInstances;
use HyperfApiDoc\Support\HyperfClasses;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

use function Hyperf\Support\class_basename;

/**
 * Materializes an ApiSchema from a class-string.
 *
 * Resolution order per class:
 *  1. ApiSchemaDocumented::documentApiSchema() (standalone definition)
 *  2. FormRequest -> ValidationRuleParser + documentApiSchema overlay
 *  3. DTO / plain class -> constructor promotion & public property inference
 *     + documentApiSchema overlay
 *  4. JsonResource without documentation -> empty object schema
 */
class SchemaResolver
{
    /** Safety guard for nested object recursion. */
    private const MAX_NESTED_DEPTH = 5;

    /** @var array<class-string, ApiSchema> */
    protected array $cache = [];

    /** @var array<class-string, true> classes currently being resolved (cycle guard) */
    protected array $resolving = [];

    protected ValidationRuleParser $parser;

    public function __construct(protected array $config = [])
    {
        $this->parser = new ValidationRuleParser($this->customDetectors());
    }

    public function resolve(string $class): ApiSchema
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $this->resolving[$class] = true;

        try {
            return $this->cache[$class] = $this->doResolve($class);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    /**
     * Transitive schema classes referenced (as nested objects) within the
     * resolved schema of the given class — candidates for components.
     *
     * @param class-string $class
     * @return array<int, class-string>
     */
    public function dependencies(string $class): array
    {
        $schema = $this->cache[$class] ?? null;

        if ($schema === null) {
            return [];
        }

        $found = [];
        $this->collectRefClasses($schema, $found);

        return array_keys($found);
    }

    /**
     * @return array<int, RuleDetector>
     */
    private function customDetectors(): array
    {
        return ConfigInstances::resolve(
            (array) ($this->config['rule_detectors'] ?? []),
            RuleDetector::class,
            'Rule detector'
        );
    }

    private function doResolve(string $class): ApiSchema
    {
        if (enum_exists($class)) {
            return $this->enumSchema($class);
        }

        if (! class_exists($class)) {
            throw new InvalidConfigurationException(sprintf(
                'Schema class [%s] does not exist.',
                $class
            ));
        }

        $reflection = new ReflectionClass($class);

        $isFormRequest = is_subclass_of($class, HyperfClasses::FORM_REQUEST);
        $isResource = is_subclass_of($class, HyperfClasses::JSON_RESOURCE);

        if ($isFormRequest) {
            $schema = $this->parseFormRequestRules($reflection);
        } elseif ($isResource) {
            // JsonResource bodies cannot be inferred statically; the schema
            // comes from ApiSchemaDocumented or stays an empty object.
            $schema = new ApiSchema();
        } else {
            $schema = $this->inferFromClass($reflection);
        }

        if ($reflection->implementsInterface(ApiSchemaDocumented::class)) {
            try {
                $reflection->newInstanceWithoutConstructor()->documentApiSchema($schema);
            } catch (Throwable $exception) {
                throw new InvalidConfigurationException(sprintf(
                    'Failed to document schema [%s]: %s',
                    $class,
                    $exception->getMessage()
                ), 0, $exception);
            }
        }

        // Only undocumented, empty resource schemas get an explanatory note;
        // FormRequest/DTO schemas speak for themselves.
        if ($schema->description === null && $schema->properties === [] && $isResource) {
            $schema->description = sprintf(
                'Undocumented %s body; implement ApiSchemaDocumented to document its fields.',
                class_basename($class)
            );
        }

        return $schema;
    }

    private function enumSchema(string $class): ApiSchema
    {
        $definition = BackedEnums::definition($class);

        if ($definition === null) {
            throw new InvalidConfigurationException(sprintf(
                'Unit enum [%s] cannot be documented as a schema; use a backed enum.',
                $class
            ));
        }

        $schema = new ApiSchema();
        $schema->description = sprintf('One of the available %s values.', class_basename($class));
        $schema->property('value')->enum($definition[0]);

        return $schema;
    }

    private function parseFormRequestRules(ReflectionClass $reflection): ApiSchema
    {
        try {
            /** @var array<string, mixed> $rules */
            $rules = $reflection->newInstanceWithoutConstructor()->rules();
        } catch (Throwable $exception) {
            throw new InvalidConfigurationException(sprintf(
                'Failed to read rules() from FormRequest [%s]: %s',
                $reflection->getName(),
                $exception->getMessage()
            ), 0, $exception);
        }

        return $this->parser->parse((array) $rules);
    }

    /**
     * Infer a schema from constructor-promoted parameters and public
     * properties (DTO / plain response classes).
     */
    private function inferFromClass(ReflectionClass $reflection): ApiSchema
    {
        $schema = new ApiSchema();
        $seen = [];

        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if (! $parameter->isPromoted()) {
                    continue;
                }

                $property = $this->propertyFromParameter($parameter);

                if ($parameter->isDefaultValueAvailable()) {
                    $property->default($parameter->getDefaultValue());
                } else {
                    $schema->require($parameter->getName());
                }

                if ($this->isReadonlyProperty($reflection, $parameter->getName())) {
                    $property->readOnly(true);
                }

                $schema->property($parameter->getName())->merge($property);
                $seen[$parameter->getName()] = true;
            }
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()
                || isset($seen[$property->getName()])
                || $property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $schema->property($property->getName())->merge($this->propertyFromType($property->getType()));
        }

        return $schema;
    }

    private function propertyFromParameter(ReflectionParameter $parameter): ApiProperty
    {
        return $this->propertyFromType($parameter->getType());
    }

    private function propertyFromType(?ReflectionType $type): ApiProperty
    {
        $property = new ApiProperty();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            $property->nullable = $type->allowsNull() ? true : null;

            if ($name === 'mixed') {
                return $property;
            }

            if (! $type->isBuiltin() && is_a($name, DateTimeInterface::class, true)) {
                $property->type = 'string';
                $property->format = 'date-time';

                return $property;
            }

            if (! $type->isBuiltin() && enum_exists($name)) {
                $this->applyEnum($property, $name);

                return $property;
            }

            if (! $type->isBuiltin() && $this->expandNestedObject($property, $name)) {
                return $property;
            }

            $property->type = $this->mapType($name);
        } elseif ($type instanceof ReflectionUnionType) {
            // Unions cannot be expressed in OpenAPI 3.0; leave untyped.
            $property->nullable = true;
        }

        return $property;
    }

    private function applyEnum(ApiProperty $property, string $enum): void
    {
        $definition = BackedEnums::definition($enum);

        if ($definition === null) {
            // Unit enums cannot be typed without an explicit convention.
            return;
        }

        [$values, $type] = $definition;

        $property->type = $type;
        $property->enum = $values;
        $property->enumClass = $enum;
    }

    private function mapType(string $type): ?string
    {
        return match ($type) {
            'int' => 'integer',
            'float' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'array', 'iterable' => 'array',
            default => null,
        };
    }

    /**
     * Expand a nested documented object (DTO / value object) into the
     * property: stores the nested properties for inline rendering plus the
     * class name so the renderer can emit a $ref when the class is reused.
     *
     * Returns false (leaving the property untyped) for infrastructure
     * classes, cycles, and over-deep graphs.
     */
    private function expandNestedObject(ApiProperty $property, string $class): bool
    {
        if (! class_exists($class)
            || is_subclass_of($class, HyperfClasses::FORM_REQUEST)
            || is_subclass_of($class, HyperfClasses::JSON_RESOURCE)) {
            return false;
        }

        // Cycle or depth guard: document the property without a type
        // rather than recursing forever.
        if (isset($this->resolving[$class]) || count($this->resolving) >= self::MAX_NESTED_DEPTH) {
            return false;
        }

        $nested = $this->resolve($class);

        $property->refClass = $class;
        $property->type = 'object';

        if ($nested->properties !== []) {
            $property->properties = $nested->properties;

            if ($nested->required !== []) {
                $property->required = $nested->required;
            }
        }

        return true;
    }

    /**
     * @param array<class-string, true> $found
     */
    private function collectRefClasses(ApiSchema $schema, array &$found): void
    {
        foreach ($schema->properties as $property) {
            $this->collectFromProperty($property, $found);
        }
    }

    /**
     * @param array<class-string, true> $found
     */
    private function collectFromProperty(ApiProperty $property, array &$found): void
    {
        if ($property->refClass !== null && ! isset($found[$property->refClass])) {
            $found[$property->refClass] = true;

            $nested = $this->cache[$property->refClass] ?? null;

            if ($nested !== null) {
                $this->collectRefClasses($nested, $found);
            }
        }

        if ($property->items !== null) {
            $this->collectFromProperty($property->items, $found);
        }

        if ($property->properties !== null) {
            foreach ($property->properties as $child) {
                $this->collectFromProperty($child, $found);
            }
        }
    }

    private function isReadonlyProperty(ReflectionClass $reflection, string $name): bool
    {
        if (! $reflection->hasProperty($name)) {
            return false;
        }

        return $reflection->getProperty($name)->isReadOnly();
    }
}
