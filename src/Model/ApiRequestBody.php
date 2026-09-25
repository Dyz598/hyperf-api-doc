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

use HyperfApiDoc\Model\Concerns\HasExtensions;
use HyperfApiDoc\Model\Concerns\HasSchemaOverlay;

/**
 * The request body of an operation. The body schema is referenced by
 * class-string and materialized by the SchemaResolver at generation time.
 */
class ApiRequestBody
{
    use HasExtensions;
    use HasSchemaOverlay;

    public ?string $description = null;

    /** @var null|class-string */
    public ?string $schemaClass = null;

    public string $contentType = 'application/json';

    public bool $required = true;

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function schema(string $class): static
    {
        $this->schemaClass = $class;
        return $this;
    }

    public function contentType(string $contentType): static
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }
}
