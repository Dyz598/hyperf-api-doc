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

namespace HyperfTest\Fixtures\Schema;

use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

#[ApiDocSchema]
class RefreshTokenGrantRequest implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->title('RefreshTokenGrantRequest')
            ->require('grant_type', 'client_id')
            ->descriptions([
                'grant_type' => 'The grant to use.',
                'client_id' => 'The client id or code.',
                'refresh_token' => 'Omitted when the client sends the refresh cookie instead.',
            ])
            ->fieldExamples([
                'grant_type' => 'refresh_token',
                'client_id' => 'mobile-app',
                'refresh_token' => 'def50200a3f1c8e9...',
            ])
            ->fields([
                'grant_type' => ['type' => 'string', 'enum' => ['refresh_token']],
                'client_id' => ['type' => 'string'],
                'refresh_token' => ['type' => 'string'],
            ]);
    }
}
