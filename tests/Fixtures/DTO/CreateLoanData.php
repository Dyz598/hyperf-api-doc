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
use HyperfTest\Fixtures\Constant\UserStatus;

#[ApiDocSchema]
final class CreateLoanData
{
    public function __construct(
        public readonly CustomerData $customer,
        public readonly UserStatus $status,
        public readonly float $amount,
    ) {}
}
