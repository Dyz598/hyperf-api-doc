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

namespace HyperfTest\Fixtures\Action\User;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\UserResource;

/**
 * Tier 1 fixture: zero documentation code; everything is inferred.
 */
#[Controller]
class CreateUserAction
{
    #[PostMapping('/v1/users')]
    #[TestAuthAttribute('oauth2_client')]
    public function handle(CreateUserRequest $request): UserResource
    {
        return new UserResource();
    }
}
