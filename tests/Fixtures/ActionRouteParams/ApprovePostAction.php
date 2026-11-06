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
use HyperfTest\Fixtures\Request\ApprovePostRequest;

/**
 * Fixture whose form request mixes a route placeholder with body fields.
 */
#[Controller]
class ApprovePostAction implements ApiOperationDocumented
{
    #[PatchMapping('/v1/route-posts/{post_id}/approve')]
    public function handle(ApprovePostRequest $request): string
    {
        return 'ok';
    }

    public function documentApi(ApiOperation $api): void
    {
        $api->summary('Approve Post')
            ->description('Approves a post; the id comes from the route, the note from the body.')
            ->response(200, description: 'Approved.');
    }
}
