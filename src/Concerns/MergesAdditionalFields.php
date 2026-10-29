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

namespace HyperfApiDoc\Concerns;

use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;

/**
 * Merges a schema's additional field classes into it. The using class must
 * provide a SchemaResolver as $resolver. Apply before field overlays so the
 * rendered body and any sampled example see the same shape.
 */
trait MergesAdditionalFields
{
    protected SchemaResolver $resolver;

    /**
     * Resolve and merge the schema's additional field classes. Colliding
     * fields expand their refs first — the merged shape is no longer the
     * component's, so it renders inline.
     */
    private function withAdditionalFields(ApiSchema $schema): ApiSchema
    {
        if ($schema->additionalFieldClasses === []) {
            return $schema;
        }

        $schema = $this->cloneSchema($schema);

        foreach ($schema->additionalFieldClasses as $class) {
            $incoming = $this->cloneSchema($this->resolver->resolve($class));

            foreach ($incoming->properties as $name => $property) {
                $existing = $schema->properties[$name] ?? null;

                if ($existing === null) {
                    continue;
                }

                if ($existing->refClass !== null) {
                    $this->expandRefInto($schema->property($name));
                }

                if ($property->refClass !== null) {
                    $this->expandRefInto($property);
                }
            }

            $schema->merge($incoming);
        }

        return $schema;
    }

    /**
     * A schema copy whose properties are copied too, so merges never
     * touch the resolver's cached instances.
     */
    private function cloneSchema(ApiSchema $schema): ApiSchema
    {
        $schema = clone $schema;
        $schema->properties = array_map(
            static fn (ApiProperty $property): ApiProperty => clone $property,
            $schema->properties
        );

        return $schema;
    }

    /**
     * Replace a property's ref with the referenced schema's fields.
     */
    private function expandRefInto(ApiProperty $property): void
    {
        $resolved = $this->resolver->resolve((string) $property->refClass);
        $property->refClass = null;
        $property->properties ??= [];

        foreach ($resolved->properties as $name => $field) {
            $property->properties[$name] = clone $field;
        }
    }
}
