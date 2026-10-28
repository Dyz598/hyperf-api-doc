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
use HyperfApiDoc\Model\Concerns\HasExamples;
use HyperfApiDoc\Model\Concerns\HasExtensions;
use HyperfApiDoc\Model\Concerns\HasSchemaOverlay;

/**
 * The HTTP semantics of one operation response: status code, description,
 * headers, content type, and the body schema — a documented class-string,
 * a built ApiSchema (envelopes), or a composition.
 */
class ApiResponse
{
    use HasExtensions;
    use HasSchemaOverlay;
    use HasExamples;

    private const HEADER_KEYS = ['description', 'required', 'deprecated', 'schema', 'example', 'examples', 'content', '$ref'];

    public ?string $description = null;

    /** Body schema: a documented class-string or a built ApiSchema. */
    public ApiSchema|string|null $schema = null;

    public ?ApiComposition $composition = null;

    public string $contentType = 'application/json';

    /** true when the body is an array of the referenced schema. */
    public ?bool $arrayOf = null;

    /** @var array<int, class-string> union members merged into the primary schema */
    public array $mergeClasses = [];

    /** @var array<string, array<string, mixed>> header name => definition */
    public array $headers = [];

    public function __construct(
        public readonly int $status,
    ) {}

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * The body schema or a composition; the last call wins.
     */
    public function schema(ApiComposition|ApiSchema|string $schema): static
    {
        if ($schema instanceof ApiComposition) {
            $this->schema = null;
            $this->composition = $schema;
        } else {
            $this->composition = null;
            $this->schema = $schema;
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
        return $this;
    }

    /**
     * Additional schema classes (union return types) whose properties
     * merge into the primary schema at generation time.
     */
    public function merge(string ...$classes): static
    {
        $this->mergeClasses = [...$this->mergeClasses, ...$classes];
        return $this;
    }

    /**
     * Document the body as an array of the referenced schema
     * (list endpoints).
     */
    public function collection(bool $collection = true): static
    {
        $this->arrayOf = $collection;
        return $this;
    }

    /**
     * Document response headers.
     *
     * @param array<string, array{description?: string, required?: bool, deprecated?: bool, schema?: array<string, mixed>, example?: mixed, examples?: array, content?: array, '$ref'?: string}> $headers
     */
    public function headers(array $headers): static
    {
        foreach ($headers as $name => $header) {
            foreach (array_keys((array) $header) as $key) {
                if (! in_array((string) $key, self::HEADER_KEYS, true)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Unknown header option [%s] for header [%s].',
                        $key,
                        $name
                    ));
                }
            }

            $this->headers[(string) $name] = (array) $header;
        }

        return $this;
    }
}
