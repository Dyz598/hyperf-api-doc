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

namespace HyperfTest\Fixtures\ApiDoc;

use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\Resource\ErrorResource;
use HyperfTest\Fixtures\Resource\PostResource;

class CreatePostApi implements ApiOperationDocumented
{
    public function documentApi(ApiOperation $api): void
    {
        $api
            ->summary('Create post')
            ->description('Create a new post owned by the given user.')
            ->tags('Posts')
            ->response(201, PostResource::class, description: 'Post created.')
            ->response(404, ErrorResource::class, description: 'User not found.');
    }
}
