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

namespace HyperfApiDoc\Attribute;

use Attribute;

/**
 * Marks a class as a reusable schema component: it always renders as a
 * #/components/schemas $ref. Unmarked classes always render inline.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiDocSchema
{
    public function __construct(public readonly ?string $name = null) {}
}
