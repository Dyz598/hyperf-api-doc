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
use HyperfApiDoc\Model\ApiComposition;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfTest\Fixtures\Schema\OtpGrantRequest;
use HyperfTest\Fixtures\Schema\PasswordGrantRequest;
use HyperfTest\Fixtures\Schema\RefreshTokenGrantRequest;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Composed request/response schemas and media-type examples.
 *
 * @internal
 * @coversNothing
 */
class CompositionTest extends AbstractTestCase
{
    public function testOneOfRequestBodyRendersMemberRefsAndInlineMembers(): void
    {
        $spec = $this->render($this->tokenOperation());

        $schema = $spec['paths']['/oauth/token']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame(
            ['#/components/schemas/OtpGrantRequest', '#/components/schemas/PasswordGrantRequest'],
            array_column($schema['oneOf'], '$ref')
        );

        $this->assertArrayHasKey('OtpGrantRequest', $spec['components']['schemas']);
        $this->assertArrayHasKey('PasswordGrantRequest', $spec['components']['schemas']);
    }

    public function testAnyOfAndAllOfKeywordsRender(): void
    {
        foreach (['anyOf' => 'anyOf', 'allOf' => 'allOf'] as $factory => $keyword) {
            $operation = new ApiOperation();
            $operation->httpMethod = 'POST';
            $operation->path = '/oauth/token';
            $operation->request(ApiComposition::{$factory}(OtpGrantRequest::class));

            $spec = $this->render($operation);
            $schema = $spec['paths']['/oauth/token']['post']['requestBody']['content']['application/json']['schema'];

            $this->assertArrayHasKey($keyword, $schema);
            $this->assertArrayNotHasKey('oneOf', $schema);
        }
    }

    #[TestDox('ApiSchema instance members render inline')]
    public function testInlineSchemaMembersRenderInline(): void
    {
        $inline = (new ApiSchema())
            ->title('InlineGrant')
            ->require('grant_type')
            ->fields(['grant_type' => ['type' => 'string']]);

        $operation = $this->tokenOperation();
        $operation->requestBody->oneOf(OtpGrantRequest::class, $inline);

        $spec = $this->render($operation);
        $schema = $spec['paths']['/oauth/token']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame('#/components/schemas/OtpGrantRequest', $schema['oneOf'][0]['$ref']);
        $this->assertSame('InlineGrant', $schema['oneOf'][1]['title']);
        $this->assertSame(['grant_type'], $schema['oneOf'][1]['required']);
    }

    public function testDiscriminatorRendersPropertyNameAndMapping(): void
    {
        $operation = $this->tokenOperation();
        $operation->request(ApiComposition::oneOf(
            OtpGrantRequest::class,
            PasswordGrantRequest::class,
            RefreshTokenGrantRequest::class,
        )->discriminator('grant_type', [
            'otp' => OtpGrantRequest::class,
            'password' => PasswordGrantRequest::class,
            'refresh_token' => RefreshTokenGrantRequest::class,
        ]));

        $spec = $this->render($operation);
        $schema = $spec['paths']['/oauth/token']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame('grant_type', $schema['discriminator']['propertyName']);
        $this->assertSame([
            'otp' => '#/components/schemas/OtpGrantRequest',
            'password' => '#/components/schemas/PasswordGrantRequest',
            'refresh_token' => '#/components/schemas/RefreshTokenGrantRequest',
        ], $schema['discriminator']['mapping']);
    }

    public function testRequestExamplesRenderOnTheMediaType(): void
    {
        $operation = $this->tokenOperation();
        $operation
            ->requestExample(['grant_type' => 'otp', 'client_id' => 'mobile-app'])
            ->requestExamples([
                'otp_login' => ['summary' => 'OTP login', 'value' => ['grant_type' => 'otp', 'client_id' => 'mobile-app']],
                'refresh_cookie' => [
                    'summary' => 'Refresh via cookie',
                    'description' => 'The browser sends the refresh cookie automatically.',
                    'value' => ['grant_type' => 'refresh_token', 'client_id' => 'backoffice'],
                ],
            ]);

        $spec = $this->render($operation);
        $media = $spec['paths']['/oauth/token']['post']['requestBody']['content']['application/json'];

        $this->assertSame(['grant_type' => 'otp', 'client_id' => 'mobile-app'], $media['example']);
        $this->assertSame('Refresh via cookie', $media['examples']['refresh_cookie']['summary']);
        $this->assertSame(
            ['grant_type' => 'refresh_token', 'client_id' => 'backoffice'],
            $media['examples']['refresh_cookie']['value']
        );
    }

    #[TestDox('Body-level fieldExamples() patch property facets')]
    public function testBodyFieldExamplesPatchPropertyFacets(): void
    {
        $schema = (new ApiSchema())->fields(['grant_type' => ['type' => 'string']]);

        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/oauth/token';
        $operation->response(
            200,
            $schema,
            configure: static fn ($response) => $response->fieldExamples(['grant_type' => 'otp']),
        );

        $spec = $this->render($operation);
        $media = $spec['paths']['/oauth/token']['post']['responses']['200']['content']['application/json'];

        $this->assertSame('otp', $media['schema']['properties']['grant_type']['example']);
        $this->assertArrayNotHasKey('example', $media, 'fieldExamples patches facets, not the media example');
    }

    public function testResponseCompositionExamplesAndHeadersTogether(): void
    {
        $operation = $this->tokenOperation();
        $operation
            ->response(200, ApiComposition::oneOf(
                (new ApiSchema())->title('TokenBodyMode')->require('access_token'),
                (new ApiSchema())->title('TokenCookieMode')->require('access_token'),
            ), 'Tokens issued.')
            ->responseHeaders(200, [
                'Set-Cookie' => ['description' => 'Refresh token; cookie clients only.', 'schema' => ['type' => 'string']],
            ])
            ->responseExamples(200, [
                'body_mode' => ['summary' => 'Refresh token in body', 'value' => ['access_token' => 'a', 'refresh_token' => 'r']],
                'cookie_mode' => ['summary' => 'Refresh token in cookie', 'value' => ['access_token' => 'a']],
            ]);

        $spec = $this->render($operation);
        $response = $spec['paths']['/oauth/token']['post']['responses']['200'];

        $this->assertSame('Tokens issued.', $response['description']);
        $this->assertSame('Refresh token; cookie clients only.', $response['headers']['Set-Cookie']['description']);
        $this->assertCount(2, $response['content']['application/json']['schema']['oneOf']);
        $this->assertSame('Refresh token in cookie', $response['content']['application/json']['examples']['cookie_mode']['summary']);
    }

    public function testCollectionWrapsComposedSchema(): void
    {
        $operation = $this->tokenOperation();
        $operation->response(200, configure: static function ($response): void {
            $response->collection()->oneOf(OtpGrantRequest::class);
        });

        $spec = $this->render($operation);
        $schema = $spec['paths']['/oauth/token']['post']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('array', $schema['type']);
        $this->assertSame('#/components/schemas/OtpGrantRequest', $schema['items']['oneOf'][0]['$ref']);
    }

    public function testComposedRequestBodyRejectsFieldOverlays(): void
    {
        $operation = $this->tokenOperation();
        $operation->requestBody->descriptions(['grant_type' => 'Overlay not allowed.']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('do not support field overlays');

        $this->render($operation);
    }

    public function testComposedResponseRejectsMerge(): void
    {
        $operation = $this->tokenOperation();
        $operation->response(200, configure: static function ($response): void {
            $response->oneOf(OtpGrantRequest::class)->merge(PasswordGrantRequest::class);
        });

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('do not support field overlays or merge');

        $this->render($operation);
    }

    public function testUnknownExampleOptionThrows(): void
    {
        $operation = $this->tokenOperation();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown example option [valeu]');

        $operation->requestExamples([
            'typo' => ['valeu' => ['grant_type' => 'otp']],
        ]);
    }

    public function testDiscriminatorMappingMustReferenceMembers(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must reference a composition member');

        ApiComposition::oneOf(OtpGrantRequest::class)
            ->discriminator('grant_type', ['otp' => PasswordGrantRequest::class]);
    }

    public function testEmptyCompositionThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('at least one member');

        ApiComposition::oneOf();
    }

    #[TestDox('Operation example/header helpers fail loudly on misuse')]
    public function testOperationSugarGuards(): void
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/oauth/token';

        try {
            $operation->requestExample(['grant_type' => 'otp']);
            $this->fail('requestExample without request() should throw.');
        } catch (InvalidConfigurationException) {
        }

        $operation->request(OtpGrantRequest::class)->response(200);

        try {
            $operation->responseExamples(404, []);
            $this->fail('responseExamples for an unregistered status should throw.');
        } catch (InvalidConfigurationException) {
        }

        try {
            $operation->responseHeaders(500, []);
            $this->fail('responseHeaders for an unregistered status should throw.');
        } catch (InvalidConfigurationException) {
        }

        $this->addToAssertionCount(1);
    }

    private function tokenOperation(): ApiOperation
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/oauth/token';
        $operation->request(ApiComposition::oneOf(OtpGrantRequest::class, PasswordGrantRequest::class));

        return $operation;
    }

    private function render(ApiOperation $operation): array
    {
        $document = new ApiDocument();
        $document->operation($operation);

        return (new OpenApiRenderer(new SchemaRegistry()))->render($document);
    }
}
