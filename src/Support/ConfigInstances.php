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

namespace HyperfApiDoc\Support;

use HyperfApiDoc\Exception\InvalidConfigurationException;

/**
 * Instantiates config entries — class-strings or ready instances — and
 * validates them against a contract. Shared by the strategies, namer,
 * rule detector, spec extension, and writer config keys.
 */
final class ConfigInstances
{
    /**
     * @template T of object
     *
     * @param array<int, class-string<T>|T> $entries
     * @param class-string<T> $contract entries must implement this
     * @param string $label entry label used in error messages
     * @return array<int, T>
     */
    public static function resolve(array $entries, string $contract, string $label): array
    {
        $instances = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                if (! class_exists($entry)) {
                    throw new InvalidConfigurationException(sprintf(
                        '%s class [%s] does not exist.',
                        $label,
                        $entry
                    ));
                }

                $entry = new $entry();
            }

            if (! $entry instanceof $contract) {
                throw new InvalidConfigurationException(sprintf(
                    '%s [%s] must implement [%s].',
                    $label,
                    is_object($entry) ? $entry::class : gettype($entry),
                    $contract
                ));
            }

            $instances[] = $entry;
        }

        return $instances;
    }
}
