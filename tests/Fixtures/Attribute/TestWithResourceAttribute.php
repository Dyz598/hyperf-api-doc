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
 * Local stand-in for Menumbing\Resource\Annotation\WithResource.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class TestWithResourceAttribute
{
    public function __construct(public readonly ?string $resource = null, public readonly int $statusCode = 200) {}
}
