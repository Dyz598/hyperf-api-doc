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
class OtpGrantRequest implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->title('OtpGrantRequest')
            ->require('grant_type', 'client_id', 'request_id', 'otp')
            ->example(['grant_type' => 'otp', 'client_id' => 'mobile-app'])
            ->descriptions([
                'grant_type' => 'The grant to use.',
                'client_id' => 'The client id or code.',
                'request_id' => 'The id returned by the OTP request endpoint.',
                'otp' => 'The one-time password sent to the phone number.',
            ])
            ->fieldExamples([
                'grant_type' => 'otp',
                'client_id' => 'mobile-app',
                'request_id' => 'b7e6a1f2-3c4d-4e5f-8a9b-0c1d2e3f4a5b',
                'otp' => '123456',
            ])
            ->fields([
                'grant_type' => ['type' => 'string', 'enum' => ['otp']],
                'client_id' => ['type' => 'string'],
                'request_id' => ['type' => 'string', 'format' => 'uuid'],
                'otp' => ['type' => 'string'],
            ]);
    }
}
