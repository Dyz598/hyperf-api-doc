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

namespace HyperfTest\Fixtures\Resource;

use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;
use HyperfTest\Fixtures\Constant\UserStatus;

#[ApiDocSchema]
class PostResource implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'id' => 'Unique post identifier.',
                'title' => 'Post title.',
                'status' => 'Current publication status.',
            ])
            ->examples([
                'id' => 456,
                'title' => 'Hello',
                'status' => 'published',
            ])
            ->enums([
                'status' => UserStatus::class,
            ]);
    }
}
