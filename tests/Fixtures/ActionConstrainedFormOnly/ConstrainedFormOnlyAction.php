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

namespace HyperfTest\Fixtures\ActionConstrainedFormOnly;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfTest\Fixtures\Request\ConstrainedIdOnlyRequest;

/**
 * GET on a constrained path whose form request rules only cover the route
 * placeholder: no request body describes a GET, and the placeholder is not
 * a body field anyway.
 */
#[Controller]
class ConstrainedFormOnlyAction
{
    #[GetMapping('/v1/savings/{id:(?!products$)[^/]+}')]
    public function handle(ConstrainedIdOnlyRequest $request): string
    {
        return 'ok';
    }
}
