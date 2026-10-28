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
class PasswordGrantRequest implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->title('PasswordGrantRequest')
            ->require('grant_type', 'client_id', 'username', 'password')
            ->descriptions([
                'grant_type' => 'The grant to use.',
                'client_id' => 'The client id or code.',
                'username' => 'The account email.',
                'password' => 'The account password.',
            ])
            ->fieldExamples([
                'grant_type' => 'password',
                'client_id' => 'backoffice',
                'username' => 'ops@example.com',
                'password' => 'secret',
            ])
            ->fields([
                'grant_type' => ['type' => 'string', 'enum' => ['password']],
                'client_id' => ['type' => 'string'],
                'username' => ['type' => 'string', 'format' => 'email'],
                'password' => ['type' => 'string'],
            ]);
    }
}
