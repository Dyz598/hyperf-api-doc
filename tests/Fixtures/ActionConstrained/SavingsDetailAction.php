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

namespace HyperfTest\Fixtures\ActionConstrained;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * Constrained route fixture: the lookahead keeps static siblings routable
 * at runtime, and the documented path must still be a clean {id}.
 */
#[Controller]
class SavingsDetailAction
{
    #[GetMapping('/v1/savings/{id:(?!products$|name-templates$)[^/]+}')]
    public function handle(int $id): string
    {
        return 'ok';
    }
}
