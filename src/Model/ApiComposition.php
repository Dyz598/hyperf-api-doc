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

/**
 * A oneOf/anyOf/allOf composition of body schemas. Members are schema
 * class-strings (documented classes) or built ApiSchema instances.
 */
final class ApiComposition
{
    public ?string $discriminatorProperty = null;

    /** @var array<string, class-string> property value => member class */
    public array $discriminatorMapping = [];

    /**
     * @param array<int, ApiSchema|class-string> $members
     */
    private function __construct(
        public readonly CompositionKeyword $keyword,
        public readonly array $members,
    ) {
        if ($members === []) {
            throw new InvalidConfigurationException('Compositions require at least one member.');
        }

        foreach ($members as $member) {
            if (! is_string($member) && ! $member instanceof ApiSchema) {
                throw new InvalidConfigurationException(sprintf(
                    'Composition members must be schema class-strings or ApiSchema instances, got [%s].',
                    get_debug_type($member)
                ));
            }
        }
    }

    public static function oneOf(ApiSchema|string ...$members): static
    {
        return new self(CompositionKeyword::OneOf, array_values($members));
    }

    public static function anyOf(ApiSchema|string ...$members): static
    {
        return new self(CompositionKeyword::AnyOf, array_values($members));
    }

    public static function allOf(ApiSchema|string ...$members): static
    {
        return new self(CompositionKeyword::AllOf, array_values($members));
    }

    /**
     * Discriminate members by a property value; mapping keys map the
     * property value to a member class, resolved to component refs at
     * render time. Mapping targets must be reusable #[ApiDocSchema]
     * components.
     *
     * @param array<string, class-string> $mapping
     */
    public function discriminator(string $property, array $mapping = []): static
    {
        foreach ($mapping as $value => $class) {
            if (! is_string($class) || ! in_array($class, $this->members, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Discriminator mapping [%s => %s] must reference a composition member class.',
                    $value,
                    is_string($class) ? $class : get_debug_type($class)
                ));
            }
        }

        $this->discriminatorProperty = $property;
        $this->discriminatorMapping = $mapping;

        return $this;
    }
}
