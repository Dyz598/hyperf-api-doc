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

/**
 * Documents the HTTP semantics of an API operation (summary, responses,
 * security, parameters). Implement this directly on an action class, or
 * point to a definition class via #[ApiDoc].
 *
 * The method is applied to every routed method of the implementing class,
 * which is the natural fit for single-action controllers.
 */
interface ApiOperationDocumented
{
    public function documentApi(ApiOperation $api): void;
}
