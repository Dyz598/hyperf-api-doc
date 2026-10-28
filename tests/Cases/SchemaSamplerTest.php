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

use HyperfApiDoc\Generator\SchemaRegistry;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Generator\SchemaSampler;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;
use HyperfTest\Fixtures\Schema\OtpGrantRequest;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Samples payloads from schemas: examples and defaults first, then enum
 * values and placeholders.
 *
 * @internal
 * @coversNothing
 */
class SchemaSamplerTest extends AbstractTestCase
{
    public function testSamplePrefersExamplesDefaultsAndEnums(): void
    {
        $schema = (new ApiSchema())->fields([
            'with_example' => ['type' => 'string', 'example' => 'otp'],
            'with_default' => ['type' => 'integer', 'default' => 7],
            'with_enum' => ['type' => 'string', 'enum' => ['b', 'a']],
            'bare_string' => ['type' => 'string'],
            'bare_integer' => ['type' => 'integer'],
            'bare_boolean' => ['type' => 'boolean'],
        ]);

        $this->assertSame([
            'with_example' => 'otp',
            'with_default' => 7,
            'with_enum' => 'b',
            'bare_string' => '',
            'bare_integer' => 0,
            'bare_boolean' => true,
        ], $this->sampler()->sample($schema));
    }

    public function testSampleResolvesRefsAndGuardsCycles(): void
    {
        $registry = new SchemaRegistry();

        $a = new ApiSchema();
        $a->property('b')->refClass = 'B';
        $b = new ApiSchema();
        $b->property('a')->refClass = 'A';
        $registry->register('A', $a);
        $registry->register('B', $b);

        $sampler = new SchemaSampler($registry, new SchemaResolver());

        // A -> B -> A -> B stops at the repeated ref; the root's own key
        // is unknown to sample(), so the cycle unwinds one level first
        $this->assertSame(['b' => ['a' => ['b' => null]]], $sampler->sample($registry->schema('A')));
    }

    #[TestDox('Nested objects and arrays sample recursively')]
    public function testSampleNestedStructures(): void
    {
        $schema = (new ApiSchema())->fields([
            'tags' => ['type' => 'array', 'example' => ['a', 'b']],
        ]);
        $schema->property('address')->properties = [
            'city' => (new ApiProperty())->type('string')->example('Jakarta'),
        ];

        $this->assertSame([
            'tags' => ['a', 'b'],
            'address' => ['city' => 'Jakarta'],
        ], $this->sampler()->sample($schema));
    }

    public function testSampleDocumentedSchemaClass(): void
    {
        $registry = new SchemaRegistry();
        $registry->register(OtpGrantRequest::class, (new SchemaResolver())->resolve(OtpGrantRequest::class));

        $sample = (new SchemaSampler($registry, new SchemaResolver()))->sample($registry->schema(OtpGrantRequest::class));

        $this->assertSame('otp', $sample['grant_type']);
        $this->assertSame('mobile-app', $sample['client_id']);
        $this->assertSame('123456', $sample['otp']);
    }

    private function sampler(): SchemaSampler
    {
        return new SchemaSampler(new SchemaRegistry(), new SchemaResolver());
    }
}
