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

namespace HyperfTest\Fixtures\Action\Post;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Resource\ErrorResource;
use HyperfTest\Fixtures\Resource\PostResource;

/**
 * Tier 2 fixture: the action documents itself via the contract.
 */
#[Controller]
class GetPostAction implements ApiOperationDocumented
{
    #[GetMapping('/v1/posts/{postId}')]
    #[TestAuthAttribute('oauth2_client')]
    public function handle(int $postId): PostResource
    {
        return new PostResource();
    }

    public function documentApi(ApiOperation $api): void
    {
        $api
            ->summary('Get post')
            ->description('Retrieve a single post by ID.')
            ->response(200, PostResource::class)
            ->response(404, ErrorResource::class, description: 'Post not found.');
    }
}
