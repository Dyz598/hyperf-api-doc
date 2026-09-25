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

namespace HyperfApiDoc\Contract;

/**
 * Encodes a rendered specification into an output format. Register
 * implementations via the "output.writers" config key; the "format"
 * option resolves to the writer whose format() matches.
 */
interface Writer
{
    /** Format name used by --format and the output.format config. */
    public function format(): string;

    /** File extension (without dot) appended to the output file name. */
    public function extension(): string;

    /**
     * @param array<string, mixed> $spec
     */
    public function encode(array $spec, bool $pretty = true): string;
}
