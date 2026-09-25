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

namespace HyperfTest\Fixtures\Action\Comment;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Resource\WrappedResource;

/**
 * JsonResource fixture: a paginated collection whose schema is a real
 * JsonResource, so the wrap decoration applies to it.
 */
#[Controller]
class ListCommentsAction
{
    #[GetMapping('/v1/comments')]
    #[TestWithResourceAttribute(resource: WrappedResource::class, statusCode: 200)]
    #[ApiDoc(paginated: true)]
    public function handle(): mixed
    {
        return WrappedResource::collection([]);
    }
}
