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
 * The meta object of paginated collection responses. Extend and override
 * documentApiSchema() (calling parent) to add fields.
 */
#[ApiDocSchema]
class PaginationMeta implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'current_page' => 'Current page number (1-based).',
                'from' => 'Index of the first item on the page; null when empty.',
                'last_page' => 'Last page number.',
                'path' => 'Base URL of the paginated endpoint.',
                'per_page' => 'Number of items per page.',
                'to' => 'Index of the last item on the page; null when empty.',
                'total' => 'Total number of items across all pages.',
            ])
            ->examples([
                'current_page' => 1,
                'from' => 1,
                'last_page' => 3,
                'path' => 'https://api.example.com/v1/posts',
                'per_page' => 15,
                'to' => 15,
                'total' => 42,
            ])
            ->fields([
                'current_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'last_page' => ['type' => 'integer'],
                'path' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
            ]);
    }
}
