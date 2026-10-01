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

class UploadMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:1024',
            'caption' => 'nullable|string|max:255',
        ];
    }
}
