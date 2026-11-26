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

namespace HyperfTest\Fixtures\ActionDuplicateRoutes;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * Two constraints that normalize to the same documented route: the
 * generator must fail loudly instead of silently overwriting.
 */
#[Controller]
class DuplicateSavingsActions
{
    #[GetMapping('/v1/savings/{id:[0-9]+}')]
    public function numeric(): string
    {
        return 'numeric';
    }

    #[GetMapping('/v1/savings/{id:(?!products$)[^/]+}')]
    public function lookup(): string
    {
        return 'lookup';
    }
}
