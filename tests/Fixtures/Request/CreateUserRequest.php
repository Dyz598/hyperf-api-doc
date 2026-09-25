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
use Hyperf\Validation\Rules\Enum;
use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfTest\Fixtures\Constant\UserStatus;

#[ApiDocSchema]
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'status' => ['required', new Enum(UserStatus::class)],
            'age' => 'required|integer|min:18|max:120',
            'gender' => 'required|in_gender',
            'role' => 'required|in:admin,staff',
            'address.city' => 'required|string',
            'address.postal_code' => 'required|string|size:5',
            'tags.*' => 'string|max:20',
            'schedules.*.due_date' => 'required|date_format:Y-m-d',
        ];
    }
}
