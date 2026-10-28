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
 * Media-type level examples: one single example or named examples
 * rendered next to the body schema.
 */
trait HasExamples
{
    private const EXAMPLE_KEYS = ['summary', 'description', 'value', 'externalValue'];

    public mixed $example = null;

    public bool $hasExample = false;

    /** @var array<string, array<string, mixed>> name => definition */
    public array $examples = [];

    public function example(mixed $value): static
    {
        $this->example = $value;
        $this->hasExample = true;

        return $this;
    }

    /**
     * @param array<string, array{summary?: string, description?: string, value?: mixed, externalValue?: string}> $examples
     */
    public function examples(array $examples): static
    {
        foreach ($examples as $name => $example) {
            foreach (array_keys((array) $example) as $key) {
                if (! in_array((string) $key, self::EXAMPLE_KEYS, true)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Unknown example option [%s] for example [%s].',
                        $key,
                        $name
                    ));
                }
            }

            $this->examples[(string) $name] = (array) $example;
        }

        return $this;
    }
}
