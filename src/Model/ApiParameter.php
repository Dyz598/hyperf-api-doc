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
 * A path, query, header, or cookie parameter.
 *
 * Parameters are not schema properties: they carry their own lightweight
 * type information and always render as flat OpenAPI parameter objects.
 */
class ApiParameter
{
    public ?string $description = null;

    public ?string $type = null;

    public ?string $format = null;

    public mixed $example = null;

    public bool $hasExample = false;

    public ?array $enum = null;

    public bool $deprecated = false;

    public function __construct(
        public readonly string $name,
        public readonly string $in = 'query',
        public readonly bool $required = false,
    ) {
        if (! in_array($in, ['path', 'query', 'header', 'cookie'], true)) {
            throw new InvalidConfigurationException(sprintf(
                'Parameter [%s] has invalid location [%s]; expected path, query, header, or cookie.',
                $name,
                $in
            ));
        }
    }

    public static function path(string $name, bool $required = true): self
    {
        return new self($name, 'path', $required);
    }

    public static function query(string $name, bool $required = false): self
    {
        return new self($name, 'query', $required);
    }

    public static function header(string $name, bool $required = false): self
    {
        return new self($name, 'header', $required);
    }

    public static function cookie(string $name, bool $required = false): self
    {
        return new self($name, 'cookie', $required);
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function type(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function format(?string $format): static
    {
        $this->format = $format;
        return $this;
    }

    public function example(mixed $example): static
    {
        $this->example = $example;
        $this->hasExample = true;
        return $this;
    }

    public function enum(?array $enum): static
    {
        $this->enum = $enum;
        return $this;
    }

    public function deprecated(bool $deprecated = true): static
    {
        $this->deprecated = $deprecated;
        return $this;
    }
}
