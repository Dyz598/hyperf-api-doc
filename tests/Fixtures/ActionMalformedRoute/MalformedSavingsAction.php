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

namespace HyperfTest\Fixtures\ActionMalformedRoute;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * Unbalanced optional segment: FastRoute rejects this at runtime, so the
 * scanner must reject it with route context instead of a bare parse error.
 */
#[Controller]
class MalformedSavingsAction
{
    #[GetMapping('/v1/savings[/{id}')]
    public function handle(int $id): string
    {
        return 'ok';
    }
}
