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

namespace HyperfApiDoc\Schema;

use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

/**
 * The links object of paginated responses. Extend and override
 * documentApiSchema() (calling parent) to add fields.
 */
#[ApiDocSchema]
class PaginationLinks implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'first' => 'URL of the first page.',
                'last' => 'URL of the last page.',
                'prev' => 'URL of the previous page; null on the first page.',
                'next' => 'URL of the next page; null on the last page.',
            ])
            ->fields([
                'first' => ['type' => 'string'],
                'last' => ['type' => 'string'],
                'prev' => ['type' => 'string', 'nullable' => true],
                'next' => ['type' => 'string', 'nullable' => true],
            ]);
    }
}
