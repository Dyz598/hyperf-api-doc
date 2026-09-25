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

namespace HyperfTest\Fixtures\Constant;

use HyperfApiDoc\Contract\ApiEnumDocumented;

enum AccountStatus: string implements ApiEnumDocumented
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function apiDescription(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'Account is in good standing.',
            self::SUSPENDED => null,
        };
    }
}
