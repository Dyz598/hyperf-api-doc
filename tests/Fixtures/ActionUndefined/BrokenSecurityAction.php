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

namespace HyperfTest\Fixtures\ActionUndefined;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;

/**
 * Fixture referencing an undefined security scheme.
 */
#[Controller]
class BrokenSecurityAction implements ApiOperationDocumented
{
    #[GetMapping('/v1/broken')]
    public function handle(): string
    {
        return 'ok';
    }

    public function documentApi(ApiOperation $api): void
    {
        $api->security('nonexistent');
    }
}
