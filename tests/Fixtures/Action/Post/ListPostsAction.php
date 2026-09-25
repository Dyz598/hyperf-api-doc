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
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Resource\PostResource;

/**
 * Mixed return type + attribute fixture: the resource class is detected
 * from the WithResource-style attribute, and the status code from it too.
 */
#[Controller]
class ListPostsAction
{
    #[GetMapping('/v1/posts')]
    #[TestWithResourceAttribute(resource: PostResource::class, statusCode: 200)]
    #[ApiDoc(paginated: true)]
    public function handle(): mixed
    {
        return PostResource::collection([]);
    }
}
