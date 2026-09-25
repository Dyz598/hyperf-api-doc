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
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfTest\Fixtures\ApiDoc\CreatePostApi;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\PostResource;

/**
 * Tier 3 fixture: documentation lives in an external definition class.
 */
#[Controller]
class CreatePostAction
{
    #[PostMapping('/v1/users/{userId}/posts')]
    #[TestAuthAttribute('authentik')]
    #[ApiDoc(CreatePostApi::class)]
    public function handle(string $userId, CreateUserRequest $request): mixed
    {
        return new PostResource();
    }
}
