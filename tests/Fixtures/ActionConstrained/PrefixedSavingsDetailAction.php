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
 * Prefix-constraint fixture: the constraint lives on the controller, not
 * the action.
 */
#[Controller(prefix: '/v1/savings/{id:[0-9]+}')]
class PrefixedSavingsDetailAction
{
    #[GetMapping('/detail')]
    public function handle(int $id): string
    {
        return 'ok';
    }
}
