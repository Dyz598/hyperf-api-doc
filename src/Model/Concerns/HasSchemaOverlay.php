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

namespace HyperfApiDoc\Model\Concerns;

use HyperfApiDoc\Model\ApiSchema;

/**
 * A lazily created field-level ApiSchema overlay, merged onto the
 * resolved schema at render time by the shared descriptions(), examples(),
 * enums(), and fields() shortcuts.
 */
trait HasSchemaOverlay
{
    /** Field-level overlay merged onto the resolved schema. */
    protected ?ApiSchema $overlay = null;

    /**
     * @param array<string, string> $descriptions
     */
    public function descriptions(array $descriptions): static
    {
        $this->overlay()->descriptions($descriptions);
        return $this;
    }

    /**
     * @param array<string, mixed> $examples
     */
    public function examples(array $examples): static
    {
        $this->overlay()->examples($examples);
        return $this;
    }

    /**
     * @param array<string, array|class-string> $enums
     */
    public function enums(array $enums): static
    {
        $this->overlay()->enums($enums);
        return $this;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    public function fields(array $fields): static
    {
        $this->overlay()->fields($fields);
        return $this;
    }

    public function overlay(): ApiSchema
    {
        return $this->overlay ??= new ApiSchema();
    }

    public function hasOverlay(): bool
    {
        return $this->overlay !== null;
    }
}
