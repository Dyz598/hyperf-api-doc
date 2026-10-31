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

namespace HyperfTest\Fixtures\ActionRouteParams;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PatchMapping;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\Request\SubmitPostRequest;

/**
 * Fixture whose form request rules all target a route placeholder.
 */
#[Controller]
class SubmitPostAction implements ApiOperationDocumented
{
    #[PatchMapping('/v1/route-posts/{post_id}/submit')]
    public function handle(SubmitPostRequest $request): string
    {
        return 'ok';
    }

    public function documentApi(ApiOperation $api): void
    {
        $api->summary('Submit Post')
            ->description('Submits a post; the id comes from the route.')
            ->response(200, description: 'Submitted.');
    }
}
