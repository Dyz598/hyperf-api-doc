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

use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiSchema;
use ReflectionClass;

use function Hyperf\Support\class_basename;

/**
 * Tracks registered schemas. Classes marked #[ApiDocSchema] always render
 * as reusable components/schemas $refs; unmarked classes render inline.
 */
class SchemaRegistry
{
    /** @var array<class-string, ApiSchema> */
    protected array $schemas = [];

    /** @var array<class-string, string> */
    protected array $names = [];

    /** @var array<string, class-string> */
    protected array $usedNames = [];

    /** @var array<class-string, null|false|string> false = unmarked, null = marked, string = explicit name */
    protected array $mark = [];

    public function register(string $class, ApiSchema $schema): void
    {
        if (! isset($this->schemas[$class])) {
            $this->schemas[$class] = $schema;
            $this->names[$class] = $this->assignName($class);
        }
    }

    public function schema(string $class): ?ApiSchema
    {
        return $this->schemas[$class] ?? null;
    }

    /**
     * Component reference when the class is marked, null to render inline.
     */
    public function ref(string $class): ?array
    {
        if (! isset($this->schemas[$class]) || $this->schemaMark($class) === false) {
            return null;
        }

        return ['$ref' => '#/components/schemas/' . $this->names[$class]];
    }

    /**
     * Component name assigned to a registered class.
     */
    public function name(string $class): ?string
    {
        return $this->names[$class] ?? null;
    }

    /**
     * @return array<class-string, ApiSchema> class => schema for marked classes
     */
    public function reusable(): array
    {
        $components = [];

        foreach ($this->names as $class => $name) {
            if ($this->schemaMark($class) !== false) {
                $components[$class] = $this->schemas[$class];
            }
        }

        return $components;
    }

    /**
     * The #[ApiDocSchema] state of a class: false = unmarked, null =
     * marked without an explicit name, string = marked with one.
     */
    private function schemaMark(string $class): false|string|null
    {
        if (! array_key_exists($class, $this->mark)) {
            $attributes = class_exists($class) || interface_exists($class)
                ? (new ReflectionClass($class))->getAttributes(ApiDocSchema::class)
                : [];

            $this->mark[$class] = $attributes === []
                ? false
                : $attributes[0]->newInstance()->name;
        }

        return $this->mark[$class];
    }

    private function assignName(string $class): string
    {
        $explicit = $this->schemaMark($class);

        if (is_string($explicit)) {
            if (isset($this->usedNames[$explicit]) && $this->usedNames[$explicit] !== $class) {
                throw new InvalidConfigurationException(sprintf(
                    'Duplicate schema component name [%s].',
                    $explicit
                ));
            }

            $this->usedNames[$explicit] = $class;

            return $explicit;
        }

        $base = class_basename($class);
        $name = $base;
        $suffix = 2;

        while (isset($this->usedNames[$name]) && $this->usedNames[$name] !== $class) {
            $name = $base . '_' . $suffix++;
        }

        $this->usedNames[$name] = $class;

        return $name;
    }
}
