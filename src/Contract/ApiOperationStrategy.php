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

use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;

/**
 * Builds an ApiOperation from endpoint metadata. Register implementations
 * via the "strategies" config; detection strategies run after Tier 2/3
 * definitions and before the core inference strategies, and each sees the
 * work of the previous ones. See ResponseDecoratorStrategy for the later
 * decoration phase.
 */
interface ApiOperationStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void;
}
