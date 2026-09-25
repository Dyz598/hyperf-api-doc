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

use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Scanner\EnumRuleDetector;
use HyperfTest\Fixtures\Constant\Gender;
use HyperfTest\Fixtures\Constant\UserStatus;
use HyperfTest\Fixtures\Constant\UserType;
use HyperfTest\Fixtures\DTO\CreateLoanData;
use HyperfTest\Fixtures\DTO\CreatePostData;
use HyperfTest\Fixtures\DTO\CustomerData;
use HyperfTest\Fixtures\DTO\TreeNode;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\PostResource;
use HyperfTest\Fixtures\Resource\UserResource;

/**
 * @internal
 * @coversNothing
 */
class SchemaResolverTest extends AbstractTestCase
{
    public function testResolvesFormRequestFromRules()
    {
        $resolver = new SchemaResolver(['rule_detectors' => [new EnumRuleDetector(['in_gender' => Gender::class])]]);
        $schema = $resolver->resolve(CreateUserRequest::class);

        $this->assertSame('string', $schema->properties['name']->type);
        $this->assertSame(100, $schema->properties['name']->maxLength);
        $this->assertSame('email', $schema->properties['email']->format);
        $this->assertSame(
            array_map(static fn (UserStatus $status) => $status->value, UserStatus::cases()),
            $schema->properties['status']->enum
        );
        $this->assertSame('object', $schema->properties['address']->type);
        $this->assertSame('array', $schema->properties['schedules']->type);
        $this->assertContains('name', $schema->required);
    }

    public function testResolvesDtoFromConstructorPromotion()
    {
        $schema = (new SchemaResolver())->resolve(CreatePostData::class);

        $this->assertSame('string', $schema->properties['title']->type);
        $this->assertTrue($schema->properties['title']->readOnly);

        $type = $schema->properties['type'];
        $this->assertSame('integer', $type->type);
        $this->assertSame(
            array_map(static fn (UserType $userType) => $userType->value, UserType::cases()),
            $type->enum
        );
        $this->assertSame(UserType::class, $type->enumClass);

        // Defaulted parameters are optional and carry their default.
        $this->assertContains('title', $schema->required);
        $this->assertNotContains('summary', $schema->required);
        $this->assertTrue($schema->properties['summary']->nullable);
        $this->assertTrue($schema->properties['published']->hasDefault);
        $this->assertFalse($schema->properties['published']->default);
    }

    public function testResolvesResourceSchemaFromContract()
    {
        $schema = (new SchemaResolver())->resolve(UserResource::class);

        $this->assertSame('Unique user identifier.', $schema->properties['id']->description);
        $this->assertSame(123, $schema->properties['id']->example);
        $this->assertTrue($schema->properties['id']->hasExample);
    }

    public function testResolvesEnumSchema()
    {
        $schema = (new SchemaResolver())->resolve(UserStatus::class);

        $this->assertSame(
            array_map(static fn (UserStatus $status) => $status->value, UserStatus::cases()),
            $schema->properties['value']->enum
        );
    }

    public function testResourceSchemaCarriesEnumDocumentation()
    {
        $schema = (new SchemaResolver())->resolve(PostResource::class);

        $this->assertSame(
            array_map(static fn (UserStatus $status) => $status->value, UserStatus::cases()),
            $schema->properties['status']->enum
        );
        $this->assertSame('Current publication status.', $schema->properties['status']->description);
    }

    public function testResolutionIsCached()
    {
        $resolver = new SchemaResolver();

        $this->assertSame($resolver->resolve(UserResource::class), $resolver->resolve(UserResource::class));
    }

    public function testResolvesNestedDtoObjects()
    {
        $schema = (new SchemaResolver())->resolve(CreateLoanData::class);

        $customer = $schema->properties['customer'];
        $this->assertSame('object', $customer->type);
        $this->assertSame(CustomerData::class, $customer->refClass);
        $this->assertSame('string', $customer->properties['name']->type);
        $this->assertTrue($customer->properties['phone']->nullable);
        $this->assertSame(['name'], $customer->required);

        $this->assertSame('number', $schema->properties['amount']->type);
        $this->assertSame(
            array_map(static fn (UserStatus $status) => $status->value, UserStatus::cases()),
            $schema->properties['status']->enum
        );
    }

    public function testNestedCycleTerminatesSafely()
    {
        $schema = (new SchemaResolver())->resolve(TreeNode::class);

        $this->assertSame('string', $schema->properties['label']->type);

        $parent = $schema->properties['parent'];
        $this->assertTrue($parent->nullable);
        // The self-reference is documented without a type instead of recursing.
        $this->assertNull($parent->refClass);
    }

    public function testDependenciesReturnsTransitiveClasses()
    {
        $resolver = new SchemaResolver();
        $resolver->resolve(CreateLoanData::class);

        $this->assertSame([CustomerData::class], $resolver->dependencies(CreateLoanData::class));
        $this->assertSame([], $resolver->dependencies(TreeNode::class));
    }
}
