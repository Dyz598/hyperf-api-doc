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

namespace HyperfApiDoc\Menumbing;

use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

/**
 * The request meta menumbing/resource adds to every response:
 * hostname and client IP.
 */
#[ApiDocSchema]
class RequestMeta implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'hostname' => 'Hostname of the server that handled the request.',
                'client_ip' => 'Client IP address the request originated from.',
            ])
            ->examples([
                'hostname' => 'api.example.com',
                'client_ip' => '203.0.113.10',
            ])
            ->fields([
                'hostname' => ['type' => 'string'],
                'client_ip' => ['type' => 'string', 'format' => 'ipv4'],
            ]);
    }
}
