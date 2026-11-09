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

namespace HyperfTest\Fixtures\Request;

use Hyperf\Validation\Request\FormRequest;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

class InviteOwnerRequest extends FormRequest implements ApiSchemaDocumented
{
    public function rules(): array
    {
        return [
            'user.name' => ['required', 'string', 'max:150'],
            'user.phone_number' => ['required', 'string'],
            'user.email' => ['nullable', 'email'],
            'business.name' => ['nullable', 'string'],
            'business.photos.*' => ['uuid'],
        ];
    }

    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema->descriptions([
            'user.name' => 'Owner display name.',
            'user.email' => 'Optional contact email.',
            'business.name' => 'Business name.',
            'business.photos.*' => 'Photo file id.',
        ])
            ->fieldExamples(['business.name' => 'Warung Budi'])
            ->require('business.name');
    }
}
