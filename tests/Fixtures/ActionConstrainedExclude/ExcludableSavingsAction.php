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

namespace HyperfTest\Fixtures\ActionConstrainedExclude;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * Exclude-pattern fixture: discovery config matches the documented
 * (normalized) path, not the raw constraint.
 */
#[Controller]
class ExcludableSavingsAction
{
    #[GetMapping('/v1/savings/{id:(?!products$)[^/]+}')]
    public function handle(string $id): string
    {
        return 'ok';
    }
}
