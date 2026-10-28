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
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfTest\Fixtures\Schema\OtpGrantRequest;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * OpenAPI 3.1 output: nullability type arrays and schema payload examples.
 *
 * @internal
 * @coversNothing
 */
class OpenApi31Test extends AbstractTestCase
{
    public function testVersionIs31(): void
    {
        $this->assertSame('3.1.2', $this->render($this->operation())['openapi']);
    }

    public function testNullablePropertyRendersAsTypeArray(): void
    {
        $schema = (new ApiSchema())
            ->fields([
                'nickname' => ['type' => 'string', 'nullable' => true],
                'address' => ['nullable' => true, 'description' => 'Untyped, nullable.'],
                'name' => ['type' => 'string'],
            ]);

        $operation = $this->operation();
        $operation->response(200, configure: static fn ($response) => $response->schema($schema));

        $properties = $this->properties($operation);

        $this->assertSame(['string', 'null'], $properties['nickname']['type']);
        // No declared type accepts null already; nothing to widen
        $this->assertArrayNotHasKey('type', $properties['address']);
        $this->assertSame('string', $properties['name']['type']);
    }

    public function testSchemaExampleAndExamplesRender(): void
    {
        $schema = (new ApiSchema())
            ->require('grant_type')
            ->example(['grant_type' => 'otp'])
            ->examples([
                ['grant_type' => 'otp', 'otp' => '123456'],
                ['grant_type' => 'password', 'username' => 'ops@example.com'],
            ]);

        $operation = $this->operation();
        $operation->response(200, configure: static fn ($response) => $response->schema($schema));

        $schema = $this->bodySchema($operation);

        $this->assertSame(['grant_type' => 'otp'], $schema['example']);
        $this->assertSame([
            ['grant_type' => 'otp', 'otp' => '123456'],
            ['grant_type' => 'password', 'username' => 'ops@example.com'],
        ], $schema['examples']);
    }

    #[TestDox('Payload examples ride into reusable components')]
    public function testSchemaExamplesRenderInsideComponents(): void
    {
        $registry = new SchemaRegistry();
        $registry->register(OtpGrantRequest::class, (new SchemaResolver())->resolve(OtpGrantRequest::class));

        $operation = $this->operation();
        $operation->request(OtpGrantRequest::class);

        $component = $this->render($operation, $registry)['components']['schemas']['OtpGrantRequest'];

        $this->assertSame(['grant_type' => 'otp', 'client_id' => 'mobile-app'], $component['example']);
    }

    public function testFieldExamplesStillPatchPropertyFacets(): void
    {
        $schema = (new ApiSchema())->fieldExamples(['grant_type' => 'otp']);

        $operation = $this->operation();
        $operation->response(200, configure: static fn ($response) => $response->schema($schema));

        $this->assertSame('otp', $this->properties($operation)['grant_type']['example']);
        $this->assertArrayNotHasKey('example', $this->bodySchema($operation));
    }

    private function operation(): ApiOperation
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/things';

        return $operation;
    }

    private function render(ApiOperation $operation, ?SchemaRegistry $registry = null): array
    {
        $document = new ApiDocument();
        $document->operation($operation);

        return (new OpenApiRenderer($registry ?? new SchemaRegistry()))->render($document);
    }

    private function bodySchema(ApiOperation $operation): array
    {
        return $this->render($operation)['paths']['/things']['post']['responses']['200']['content']['application/json']['schema'];
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(ApiOperation $operation): array
    {
        return $this->bodySchema($operation)['properties'];
    }
}
