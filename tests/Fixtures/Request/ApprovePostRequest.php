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

class ApprovePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'post_id' => ['required', 'uuid'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
