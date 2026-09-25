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

namespace HyperfTest\Fixtures\DTO;

/**
 * Self-referencing DTO: proves the resolver's cycle guard terminates.
 */
final class TreeNode
{
    public function __construct(
        public readonly string $label,
        public readonly ?TreeNode $parent = null,
    ) {}
}
