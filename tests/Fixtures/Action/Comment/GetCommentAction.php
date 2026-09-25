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
use HyperfTest\Fixtures\Resource\WrappedResource;

/**
 * JsonResource fixture: the schema comes only from core return-type
 * inference, so the wrap decoration must run after it.
 */
#[Controller]
class GetCommentAction
{
    #[GetMapping('/v1/comments/{commentId}')]
    public function handle(int $commentId): WrappedResource
    {
        return new WrappedResource([]);
    }
}
