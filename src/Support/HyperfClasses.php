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

namespace HyperfApiDoc\Support;

/**
 * Framework class names referenced by name so integrations stay optional.
 */
final class HyperfClasses
{
    public const FORM_REQUEST = 'Hyperf\Validation\Request\FormRequest';

    public const JSON_RESOURCE = 'Hyperf\Resource\Json\JsonResource';

    public const RESOURCE_COLLECTION = 'Hyperf\Resource\Json\ResourceCollection';
}
