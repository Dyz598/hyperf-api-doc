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

namespace HyperfTest\Fixtures\ActionConstrainedBody;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfTest\Fixtures\Request\ConstrainedIdRequest;

/**
 * POST on a constrained path with a form request that also rules on the
 * route placeholder: the body must omit it and its required entry.
 */
#[Controller]
class ConstrainedBodyAction
{
    #[PostMapping('/v1/savings/{id:(?!products$)[^/]+}')]
    public function handle(ConstrainedIdRequest $request): string
    {
        return 'ok';
    }
}
