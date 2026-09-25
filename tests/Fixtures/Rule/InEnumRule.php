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

namespace HyperfTest\Fixtures\Rule;

use HyperfTest\Fixtures\Constant\Gender;

/**
 * Custom rule object shaped like real-world enum rules (new InEnum(Gender::class)).
 */
final class InEnumRule
{
    public function __construct(
        public readonly string $enum = Gender::class,
    ) {}
}
