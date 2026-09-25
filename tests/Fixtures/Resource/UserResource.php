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

#[ApiDocSchema]
class UserResource implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'id' => 'Unique user identifier.',
                'name' => 'User display name.',
                'email' => 'User email address.',
            ])
            ->examples([
                'id' => 123,
                'name' => 'Aldi',
                'email' => 'aldi@example.com',
            ]);
    }
}
