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

#[ApiDocSchema(name: 'LoanCustomer')]
final class AliasedCustomerData
{
    public function __construct(
        public readonly string $name,
    ) {}
}

#[ApiDocSchema(name: 'LoanCustomer')]
final class ClashingCustomerData
{
    public function __construct(
        public readonly string $name,
    ) {}
}
