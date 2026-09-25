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

use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceFields;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfApiDoc\Schema\PaginationLinks;
use HyperfApiDoc\Schema\PaginationMeta;
use HyperfApiDoc\Schema\PaginationSchema;
use HyperfApiDoc\Strategy\FormRequestStrategy;
use HyperfApiDoc\Strategy\JsonResourceStrategy;
use HyperfApiDoc\Strategy\ReturnTypeStrategy;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\MetaResponseSchema;
use HyperfTest\Fixtures\Resource\ResultWrappedResource;
use HyperfTest\Fixtures\Resource\UnwrappedResource;
use HyperfTest\Fixtures\Resource\UserResource;
use HyperfTest\Fixtures\Resource\WrappedResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 * @coversNothing
 */
class StrategyTest extends TestCase
{
    public function testAuthStrategyReadsMethodAttribute(): void
    {
        $operation = $this->build(new MenumbingAuthStrategy(TestAuthAttribute::class), 'GuardedAction', 'methodGuard');

        $this->assertSame('oauth2_client', $operation->authGuard);
    }

    public function testAuthStrategyFallsBackToClassAttribute(): void
    {
        $operation = $this->build(new MenumbingAuthStrategy(TestAuthAttribute::class), 'GuardedAction', 'classGuard');

        $this->assertSame('authentik', $operation->authGuard);
    }

    public function testAuthStrategyTakesFirstOfArrayGuards(): void
    {
        $operation = $this->build(new MenumbingAuthStrategy(TestAuthAttribute::class), 'GuardedAction', 'arrayGuard');

        $this->assertSame('first', $operation->authGuard);
    }

    public function testAuthStrategyMatchesNothingWithoutAttribute(): void
    {
        $operation = $this->build(new MenumbingAuthStrategy(TestAuthAttribute::class), 'UnguardedAction', 'handle');

        $this->assertNull($operation->authGuard);
    }

    public function testAuthStrategyIsInertWhenAttributeClassIsMissing(): void
    {
        $operation = $this->build(new MenumbingAuthStrategy('Vendor\Missing\Auth'), 'GuardedAction', 'methodGuard');

        $this->assertNull($operation->authGuard);
    }

    public function testAuthStrategyKeepsExistingGuard(): void
    {
        $operation = new ApiOperation();
        $operation->authGuard = 'preset';
        $operation = $this->build(new MenumbingAuthStrategy(TestAuthAttribute::class), 'GuardedAction', 'methodGuard', $operation);

        $this->assertSame('preset', $operation->authGuard);
    }

    public function testResourceStrategyDocumentsResponseWithStatus(): void
    {
        $operation = $this->build(new MenumbingResourceStrategy(TestWithResourceAttribute::class), 'ResourcedAction', 'explicit');

        $response = $operation->findResponse(201);
        $this->assertNotNull($response);
        $this->assertSame(UserResource::class, $response->schema);
        $this->assertCount(1, $operation->responses);
    }

    public function testResourceStrategyMarksCollections(): void
    {
        $context = $this->context('ResourcedAction', 'explicit', returnsCollection: true);
        $operation = new ApiOperation();

        (new MenumbingResourceStrategy(TestWithResourceAttribute::class))->build($operation, $context);

        $this->assertTrue($operation->findResponse(201)->arrayOf);
    }

    public function testResourceStrategyIgnoresBareAttribute(): void
    {
        $operation = $this->build(new MenumbingResourceStrategy(TestWithResourceAttribute::class), 'ResourcedAction', 'bare');

        $this->assertSame([], $operation->responses);
    }

    public function testResourceStrategyIsInertWhenAttributeClassIsMissing(): void
    {
        $operation = $this->build(new MenumbingResourceStrategy('Vendor\Missing\WithResource'), 'ResourcedAction', 'explicit');

        $this->assertSame([], $operation->responses);
    }

    public function testResourceStrategySkipsWhenResponsesAreSet(): void
    {
        $operation = new ApiOperation();
        $operation->response(200);
        $operation = $this->build(new MenumbingResourceStrategy(TestWithResourceAttribute::class), 'ResourcedAction', 'explicit', $operation);

        // Explicit documentation wins over the attribute.
        $this->assertCount(1, $operation->responses);
        $this->assertNull($operation->findResponse(201));
    }

    public function testReturnTypeStrategyDocumentsFirstCandidate(): void
    {
        $context = $this->context('UnguardedAction', 'handle', returnTypes: [WrappedResource::class]);
        $operation = new ApiOperation();

        (new ReturnTypeStrategy())->build($operation, $context);

        $this->assertSame(WrappedResource::class, $operation->findResponse(200)->schema);
    }

    public function testReturnTypeStrategyMergesUnionMembers(): void
    {
        $context = $this->context('UnguardedAction', 'handle', returnTypes: [WrappedResource::class, UserResource::class]);
        $operation = new ApiOperation();

        (new ReturnTypeStrategy())->build($operation, $context);

        $response = $operation->findResponse(200);
        $this->assertSame(WrappedResource::class, $response->schema);
        $this->assertSame([UserResource::class], $response->mergeClasses);
    }

    public function testReturnTypeStrategyMarksCollections(): void
    {
        $context = $this->context('UnguardedAction', 'handle', returnTypes: [UserResource::class], returnsCollection: true);
        $operation = new ApiOperation();

        (new ReturnTypeStrategy())->build($operation, $context);

        $this->assertTrue($operation->findResponse(200)->arrayOf);
    }

    public function testReturnTypeStrategySkipsWhenResponsesAreSet(): void
    {
        $context = $this->context('UnguardedAction', 'handle', returnTypes: [UserResource::class]);
        $operation = new ApiOperation();
        $operation->response(201, UserResource::class);

        (new ReturnTypeStrategy())->build($operation, $context);

        $this->assertCount(1, $operation->responses);
    }

    public function testFormRequestStrategyDetectsParameter(): void
    {
        $context = $this->context('FormRequestAction', 'handle');
        $operation = new ApiOperation();

        (new FormRequestStrategy())->build($operation, $context);

        $this->assertSame(CreateUserRequest::class, $operation->formRequest);
    }

    public function testJsonResourceStrategyBuildsTheEnvelope(): void
    {
        $operation = $this->resourceOperation(WrappedResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $schema = $operation->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $schema);
        $this->assertSame(WrappedResource::class, $schema->property('data')->refClass);
    }

    public function testJsonResourceStrategyUsesTheResourceWrapKey(): void
    {
        $operation = $this->resourceOperation(ResultWrappedResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $schema = $operation->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $schema);
        $this->assertSame(ResultWrappedResource::class, $schema->property('result')->refClass);
        $this->assertArrayNotHasKey('data', $schema->properties);
    }

    public function testJsonResourceStrategyMarksCollections(): void
    {
        $operation = new ApiOperation();
        $operation->response(200, WrappedResource::class, configure: static fn ($response) => $response->collection());

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $schema = $operation->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $schema);
        $this->assertSame('array', $schema->property('data')->type);
        $this->assertSame(WrappedResource::class, $schema->property('data')->items->refClass);
        // The collection shape moved into the envelope.
        $this->assertNull($operation->findResponse(200)->arrayOf);
    }

    public function testJsonResourceStrategyPaginates(): void
    {
        $operation = new ApiOperation();
        $operation->paginated = true;
        $operation->response(200, WrappedResource::class, configure: static fn ($response) => $response->collection());

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $schema = $operation->findResponse(200)->schema;
        $this->assertInstanceOf(PaginationSchema::class, $schema);
        $this->assertSame('array', $schema->property('data')->type);
        $this->assertSame(PaginationLinks::class, $schema->property('links')->refClass);
        $this->assertSame(PaginationMeta::class, $schema->property('meta')->refClass);
    }

    public function testJsonResourceStrategyUsesAttributeMetaClass(): void
    {
        $operation = new ApiOperation();
        $operation->paginated = MetaResponseSchema::class;
        $operation->response(200, WrappedResource::class, configure: static fn ($response) => $response->collection());

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $this->assertSame(MetaResponseSchema::class, $operation->findResponse(200)->schema->property('meta')->refClass);
    }

    public function testJsonResourceStrategyHonorsNullWrapOptOut(): void
    {
        $operation = $this->resourceOperation(UnwrappedResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        // The declared $wrap = null opts out; the class-string stays.
        $this->assertSame(UnwrappedResource::class, $operation->findResponse(200)->schema);
    }

    public function testJsonResourceStrategyIgnoresNonResources(): void
    {
        $operation = $this->resourceOperation(UserResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $this->assertSame(UserResource::class, $operation->findResponse(200)->schema);
    }

    public function testJsonResourceStrategyKeepsBuiltSchemas(): void
    {
        $envelope = new JsonResourceSchema(WrappedResource::class);
        $operation = new ApiOperation();
        $operation->response(200, $envelope);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $this->assertSame($envelope, $operation->findResponse(200)->schema);
    }

    public function testMenumbingStrategyAddsAdditionalFields(): void
    {
        $operation = $this->resourceOperation(WrappedResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));
        (new MenumbingJsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $schema = $operation->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $schema);
        $this->assertSame([MenumbingResourceFields::class], $schema->additionalFieldClasses);
    }

    public function testMenumbingStrategyAcceptsCustomFields(): void
    {
        $operation = $this->resourceOperation(WrappedResource::class);

        (new JsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));
        (new MenumbingJsonResourceStrategy(MetaResponseSchema::class))->build($operation, $this->context('UnguardedAction', 'handle'));

        $this->assertSame([MetaResponseSchema::class], $operation->findResponse(200)->schema->additionalFieldClasses);
    }

    public function testMenumbingStrategyIgnoresOtherResponses(): void
    {
        $operation = $this->resourceOperation(UserResource::class);

        (new MenumbingJsonResourceStrategy())->build($operation, $this->context('UnguardedAction', 'handle'));

        $this->assertSame(UserResource::class, $operation->findResponse(200)->schema);
    }

    private function resourceOperation(string $resource): ApiOperation
    {
        $operation = new ApiOperation();
        $operation->response(200, $resource);

        return $operation;
    }

    private function build(MenumbingAuthStrategy|MenumbingResourceStrategy $strategy, string $class, string $method, ?ApiOperation $operation = null): ApiOperation
    {
        $operation ??= new ApiOperation();
        $strategy->build($operation, $this->context($class, $method));

        return $operation;
    }

    private function context(
        string $class,
        string $method,
        array $returnTypes = [],
        bool $returnsCollection = false
    ): ApiHandlerContext {
        return new ApiHandlerContext(
            path: '/v1/test',
            httpMethod: 'GET',
            controller: new ReflectionClass(__NAMESPACE__ . '\\' . $class),
            method: new ReflectionMethod(__NAMESPACE__ . '\\' . $class, $method),
            returnTypes: $returnTypes,
            returnsCollection: $returnsCollection,
        );
    }
}

#[TestAuthAttribute('authentik')]
class GuardedAction
{
    #[TestAuthAttribute('oauth2_client')]
    public function methodGuard(): void {}

    public function classGuard(): void {}

    #[TestAuthAttribute(['first', 'second'])]
    public function arrayGuard(): void {}

    public function unGuarded(): void {}
}

class UnguardedAction
{
    public function handle(): void {}
}

class ResourcedAction
{
    #[TestWithResourceAttribute(resource: UserResource::class, statusCode: 201)]
    public function explicit(): void {}

    #[TestWithResourceAttribute]
    public function bare(): void {}
}

class FormRequestAction
{
    public function handle(CreateUserRequest $request): void {}
}
