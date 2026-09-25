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

use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

/**
 * The fields menumbing/resource adds to every JsonResource envelope: the
 * request meta object. Swap the class in MenumbingJsonResourceStrategy's
 * constructor to customize.
 */
class MenumbingResourceFields implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema->property('meta')->ref(RequestMeta::class);
    }
}
