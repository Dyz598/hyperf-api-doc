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

namespace HyperfTest\Fixtures\Attribute;

use Attribute;

/**
 * Local stand-in for a stack-specific auth attribute (guards property),
 * used to test configurable attribute inference without the real package.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class TestAuthAttribute
{
    public function __construct(public readonly array|string|null $guards = null) {}
}
