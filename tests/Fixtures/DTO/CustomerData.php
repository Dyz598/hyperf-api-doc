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

namespace HyperfTest\Fixtures\DTO;

use HyperfApiDoc\Attribute\ApiDocSchema;

#[ApiDocSchema]
final class CustomerData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone = null,
    ) {}
}
