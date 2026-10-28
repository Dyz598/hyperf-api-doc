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

use HyperfApiDoc\Model\Concerns\HasExamples;
use HyperfApiDoc\Model\Concerns\HasExtensions;
use HyperfApiDoc\Model\Concerns\HasSchemaOverlay;

/**
 * The request body of an operation. The body schema is referenced by
 * class-string or a oneOf/anyOf/allOf composition, materialized by the
 * SchemaResolver at generation time.
 */
class ApiRequestBody
{
    use HasExtensions;
    use HasSchemaOverlay;
    use HasExamples;

    public ?string $description = null;

    /** @var null|class-string */
    public ?string $schemaClass = null;

    public ?ApiComposition $composition = null;

    public string $contentType = 'application/json';

    /** Whether contentType() was called; explicit choices disable inference. */
    public bool $contentTypeExplicit = false;

    public bool $required = true;

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * The single body schema class or a composition; the last call wins.
     */
    public function schema(ApiComposition|string $schema): static
    {
        if ($schema instanceof ApiComposition) {
            $this->schemaClass = null;
            $this->composition = $schema;
        } else {
            $this->composition = null;
            $this->schemaClass = $schema;
        }

        return $this;
    }

    public function oneOf(ApiSchema|string ...$members): static
    {
        return $this->schema(ApiComposition::oneOf(...$members));
    }

    public function anyOf(ApiSchema|string ...$members): static
    {
        return $this->schema(ApiComposition::anyOf(...$members));
    }

    public function allOf(ApiSchema|string ...$members): static
    {
        return $this->schema(ApiComposition::allOf(...$members));
    }

    public function contentType(string $contentType): static
    {
        $this->contentType = $contentType;
        $this->contentTypeExplicit = true;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }
}
