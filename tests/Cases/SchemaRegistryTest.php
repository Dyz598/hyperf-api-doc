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

namespace HyperfTest\Cases;

use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Generator\SchemaRegistry;
use HyperfApiDoc\Model\ApiSchema;
use HyperfTest\Fixtures\DTO\AliasedCustomerData;
use HyperfTest\Fixtures\DTO\ClashingCustomerData;
use HyperfTest\Fixtures\Resource\MetaResponseSchema;
use HyperfTest\Fixtures\Resource\UserResource;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class SchemaRegistryTest extends TestCase
{
    public function testMarkedClassRefsAfterSingleRegistration()
    {
        $registry = new SchemaRegistry();
        $registry->register(UserResource::class, new ApiSchema());

        $this->assertSame(
            ['$ref' => '#/components/schemas/UserResource'],
            $registry->ref(UserResource::class)
        );
        $this->assertArrayHasKey(UserResource::class, $registry->reusable());
    }

    public function testUnmarkedClassStaysInlineHoweverOftenRegistered()
    {
        $registry = new SchemaRegistry();
        $registry->register(MetaResponseSchema::class, new ApiSchema());
        $registry->register(MetaResponseSchema::class, new ApiSchema());

        $this->assertNull($registry->ref(MetaResponseSchema::class));
        $this->assertSame([], $registry->reusable());
    }

    public function testExplicitComponentNameIsUsed()
    {
        $registry = new SchemaRegistry();
        $registry->register(AliasedCustomerData::class, new ApiSchema());

        $this->assertSame(
            ['$ref' => '#/components/schemas/LoanCustomer'],
            $registry->ref(AliasedCustomerData::class)
        );
        $this->assertSame('LoanCustomer', $registry->name(AliasedCustomerData::class));
    }

    public function testDuplicateExplicitNamesAreRejected()
    {
        $registry = new SchemaRegistry();
        $registry->register(AliasedCustomerData::class, new ApiSchema());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate schema component name [LoanCustomer]');

        $registry->register(ClashingCustomerData::class, new ApiSchema());
    }
}
