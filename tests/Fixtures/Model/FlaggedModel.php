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

namespace HyperfTest\Fixtures\Model;

use Hyperf\Database\Model\Model;
use HyperfApiDoc\Attribute\ApiDocSchema;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;
use HyperfTest\Fixtures\Constant\UserStatus;

#[ApiDocSchema]
class FlaggedModel extends Model implements ApiSchemaDocumented
{
    // Framework flags models redeclare when the defaults change.
    public bool $incrementing = false;

    public bool $timestamps = false;

    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema->enums([
            'status' => UserStatus::class,
        ]);
    }
}
