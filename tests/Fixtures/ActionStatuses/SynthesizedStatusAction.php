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

namespace HyperfTest\Fixtures\ActionStatuses;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\Resource\ErrorResource;

/**
 * Fixture exercising per-status synthesized example keys, including an
 * unmapped status that falls back to its class.
 */
#[Controller]
class SynthesizedStatusAction implements ApiOperationDocumented
{
    #[PostMapping('/v1/statuses')]
    public function handle(): string
    {
        return 'ok';
    }

    public function documentApi(ApiOperation $api): void
    {
        $api->response(201, ErrorResource::class)
            ->response(409, ErrorResource::class)
            ->response(418, ErrorResource::class);
    }
}
