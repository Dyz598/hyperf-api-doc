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
use HyperfApiDoc\Menumbing\MenumbingResourceFields;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiParameter;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfApiDoc\Schema\PaginationSchema;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\PostResource;
use HyperfTest\Fixtures\Resource\UserResource;

/**
 * @internal
 * @coversNothing
 */
class RendererTest extends AbstractTestCase
{
    public function testComponentsAreScopedToRefsUsedByTheDocument()
    {
        $resolver = new SchemaResolver();
        $registry = $this->populatedRegistry($resolver);

        $postsOnly = (new OpenApiRenderer($registry))->render(
            $this->document('/v1/posts', PostResource::class)
        );

        $this->assertArrayHasKey('PostResource', $postsOnly['components']['schemas']);
        $this->assertArrayNotHasKey('UserResource', $postsOnly['components']['schemas']);

        // A fresh renderer for the second document emits only UserResource.
        $usersOnly = (new OpenApiRenderer($registry))->render(
            $this->document('/v1/users/current', UserResource::class)
        );

        $this->assertArrayHasKey('UserResource', $usersOnly['components']['schemas']);
        $this->assertArrayNotHasKey('PostResource', $usersOnly['components']['schemas']);
    }

    public function testResponseHeadersRender()
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/v1/things';
        $operation->response(201, configure: static function ($response): void {
            $response->headers([
                'X-Request-Id' => ['description' => 'Correlation id.', 'schema' => ['type' => 'string']],
                'Location' => ['description' => 'URL of the created thing.'],
            ]);
        });

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = (new OpenApiRenderer(new SchemaRegistry()))->render($document);
        $response = $spec['paths']['/v1/things']['post']['responses']['201'];

        $this->assertSame('Correlation id.', $response['headers']['X-Request-Id']['description']);
        $this->assertSame(['type' => 'string'], $response['headers']['X-Request-Id']['schema']);
        $this->assertArrayHasKey('Location', $response['headers']);
    }

    public function testOperationAndResponseExtensionsRender()
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/internal';
        $operation->extensions(['x-internal' => true]);
        $operation->response(200, configure: static fn ($response) => $response->extensions(['x-slow' => true]));

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = (new OpenApiRenderer(new SchemaRegistry()))->render($document);
        $rendered = $spec['paths']['/v1/internal']['get'];

        $this->assertTrue($rendered['x-internal']);
        $this->assertTrue($rendered['responses']['200']['x-slow']);
    }

    public function testRequestBodyDescriptionAndExtensionsRender()
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = '/v1/users';
        $operation->request(CreateUserRequest::class, configure: static fn ($body) => $body
            ->description('The user to create.')
            ->extensions(['x-summary' => 'User payload']));

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = (new OpenApiRenderer(new SchemaRegistry()))->render($document);
        $body = $spec['paths']['/v1/users']['post']['requestBody'];

        $this->assertSame('The user to create.', $body['description']);
        $this->assertSame('User payload', $body['x-summary']);
        $this->assertTrue($body['required']);
    }

    public function testEnvelopeMetaRendersAsRefWhenMarked()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [], new SchemaResolver());

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/items';
        $operation->response(
            200,
            (new JsonResourceSchema(PostResource::class, collection: true))
                ->additionalFields(MenumbingResourceFields::class)
        );

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = $renderer->render($document);
        $schema = $spec['paths']['/v1/items']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertSame(['$ref' => '#/components/schemas/PostResource'], $schema['properties']['data']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/RequestMeta'], $schema['properties']['meta']);
        $this->assertArrayHasKey('RequestMeta', $spec['components']['schemas']);
    }

    public function testPaginationEnvelopeMergesAdditionalFields()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [], new SchemaResolver());

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/page';
        $operation->response(
            200,
            (new PaginationSchema(PostResource::class))->additionalFields(MenumbingResourceFields::class)
        );

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = $renderer->render($document);
        $schema = $spec['paths']['/v1/page']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertSame(['$ref' => '#/components/schemas/PaginationLinks'], $schema['properties']['links']);

        // The merged meta is no longer the component's shape: it renders
        // inline with both field sets.
        $meta = $schema['properties']['meta'];
        $this->assertSame('object', $meta['type']);
        $this->assertArrayHasKey('current_page', $meta['properties']);
        $this->assertArrayHasKey('hostname', $meta['properties']);
        $this->assertArrayNotHasKey('PaginationMeta', $spec['components']['schemas'] ?? []);
    }

    public function testUnmergedPaginationMetaStaysARef()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [], new SchemaResolver());

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/page';
        $operation->response(200, new PaginationSchema(PostResource::class));

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = $renderer->render($document);
        $schema = $spec['paths']['/v1/page']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame(['$ref' => '#/components/schemas/PaginationMeta'], $schema['properties']['meta']);
        $this->assertArrayHasKey('PaginationMeta', $spec['components']['schemas']);
    }

    public function testParameterFactoriesRenderWithLocationAndRequiredFlag()
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/things/{thing}';
        $operation->parameter(ApiParameter::path('thing'));
        $operation->parameter(ApiParameter::query('page')->type('integer')->example(1));
        $operation->parameter(ApiParameter::query('per_page', required: true)->type('integer'));
        $operation->parameter(ApiParameter::header('X-Trace-Id')->type('string'));
        $operation->parameter(ApiParameter::cookie('session', required: true)->type('string'));

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = (new OpenApiRenderer(new SchemaRegistry()))->render($document);
        $parameters = $spec['paths']['/v1/things/{thing}']['get']['parameters'];

        $this->assertSame([
            ['name' => 'thing', 'in' => 'path', 'required' => true],
            ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'example' => 1],
            ['name' => 'per_page', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
            ['name' => 'X-Trace-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'session', 'in' => 'cookie', 'required' => true, 'schema' => ['type' => 'string']],
        ], $parameters);
    }

    public function testArrayItemConstraintsDocumentThroughFieldsAndRender()
    {
        $schema = new ApiSchema();
        $schema->fields([
            'tags' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 10],
            'ids' => ['type' => 'array'],
        ]);
        $schema->property('tags')->items((new ApiProperty())->type('string'));

        $rendered = (new OpenApiRenderer(new SchemaRegistry()))->renderSchema($schema)['properties'];

        $this->assertSame(
            ['type' => 'array', 'minItems' => 1, 'maxItems' => 10, 'items' => ['type' => 'string']],
            $rendered['tags']
        );
        $this->assertSame(['type' => 'array'], $rendered['ids']);
    }

    private function document(string $path, string $class): ApiDocument
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = $path;
        $operation->response(200, $class);

        $document = new ApiDocument();
        $document->operation($operation);

        return $document;
    }

    private function populatedRegistry(SchemaResolver $resolver): SchemaRegistry
    {
        $registry = new SchemaRegistry();

        foreach ([PostResource::class, UserResource::class] as $class) {
            $registry->register($class, $resolver->resolve($class));
        }

        return $registry;
    }
}
