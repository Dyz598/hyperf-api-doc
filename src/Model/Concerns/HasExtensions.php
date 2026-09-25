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

use HyperfApiDoc\Exception\InvalidConfigurationException;

/**
 * OpenAPI specification extensions (x- prefixed keys) with validation.
 */
trait HasExtensions
{
    /** @var array<string, mixed> */
    protected array $extensions = [];

    /**
     * @param array<string, mixed> $extensions keys must be x- prefixed
     */
    public function extensions(array $extensions): static
    {
        foreach ($extensions as $key => $value) {
            $key = (string) $key;

            if (! str_starts_with($key, 'x-')) {
                throw new InvalidConfigurationException(sprintf(
                    'OpenAPI extension keys must be x- prefixed, got [%s].',
                    $key
                ));
            }

            $this->extensions[$key] = $value;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function extensionData(): array
    {
        return $this->extensions;
    }
}
