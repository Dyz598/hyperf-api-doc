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

namespace HyperfApiDoc\Filter;

use HyperfApiDoc\Model\ApiOperation;

/**
 * Matches operations carrying at least one of the given tags.
 */
class TagFilter implements ApiOperationFilter
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(protected array $tags) {}

    public function matches(ApiOperation $operation): bool
    {
        return array_intersect($this->tags, $operation->tags) !== [];
    }
}
