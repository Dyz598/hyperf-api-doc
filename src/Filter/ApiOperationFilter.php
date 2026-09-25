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
 * Decides whether an operation is included in a generated document.
 */
interface ApiOperationFilter
{
    public function matches(ApiOperation $operation): bool;
}
