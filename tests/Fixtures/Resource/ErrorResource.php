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
class ErrorResource implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'error' => 'Machine-readable error code.',
                'error_description' => 'Human-readable error message.',
                'code' => 'HTTP status code of the error.',
            ])
            ->examples([
                'error' => 'invalid_request',
                'error_description' => 'The request is invalid.',
                'code' => 422,
            ]);
    }
}
