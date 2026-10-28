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

use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;

/**
 * Builds a sample payload from a schema: property examples and defaults
 * first, then enum values, then placeholders.
 */
class SchemaSampler
{
    public function __construct(
        protected SchemaRegistry $registry,
        protected SchemaResolver $resolver,
    ) {}

    /**
     * @param array<int, string> $visited ref classes already sampled
     * @return array<string, mixed>
     */
    public function sample(ApiSchema $schema, array $visited = []): array
    {
        $sample = [];

        foreach ($schema->properties as $name => $property) {
            $sample[$name] = $this->sampleProperty($property, $visited);
        }

        return $sample;
    }

    private function sampleProperty(ApiProperty $property, array $visited): mixed
    {
        if ($property->hasExample) {
            return $property->example;
        }

        if ($property->hasDefault) {
            return $property->default;
        }

        if ($property->enum !== null && $property->enum !== []) {
            return $property->enum[0];
        }

        if ($property->refClass !== null) {
            if (in_array($property->refClass, $visited, true)) {
                return null;
            }

            return $this->sample(
                $this->schemaFor($property->refClass),
                [...$visited, $property->refClass]
            );
        }

        if ($property->properties !== null) {
            $nested = [];

            foreach ($property->properties as $name => $child) {
                $nested[$name] = $this->sampleProperty($child, $visited);
            }

            return $nested;
        }

        if ($property->items !== null) {
            return [$this->sampleProperty($property->items, $visited)];
        }

        return match ($property->type) {
            'integer', 'number' => 0,
            'boolean' => true,
            'string' => '',
            default => null,
        };
    }

    private function schemaFor(string $class): ApiSchema
    {
        return $this->registry->schema($class) ?? $this->resolver->resolve($class);
    }
}
