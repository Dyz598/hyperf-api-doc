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

use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

class MetaResponseSchema implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'page' => 'Current page number.',
                'per_page' => 'Number of items per page.',
            ])
            ->examples([
                'page' => 1,
                'per_page' => 15,
            ]);
    }
}
