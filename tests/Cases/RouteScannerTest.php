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
use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceFields;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\RouteScanner;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfApiDoc\Schema\PaginationMeta;
use HyperfApiDoc\Schema\PaginationSchema;
use HyperfTest\Fixtures\Action\Admin\AdminStatsAction;
use HyperfTest\Fixtures\Action\Comment\GetCommentAction;
use HyperfTest\Fixtures\Action\Comment\ListCommentsAction;
use HyperfTest\Fixtures\Action\Muted\MutedAction;
use HyperfTest\Fixtures\Action\Post\CreatePostAction;
use HyperfTest\Fixtures\Action\Post\GetPostAction;
use HyperfTest\Fixtures\Action\Post\ListPostsAction;
use HyperfTest\Fixtures\Action\Report\ReportAction;
use HyperfTest\Fixtures\Action\Shared\StatusAction;
use HyperfTest\Fixtures\Action\User\CreateUserAction;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Naming\ControllerStyleNamer;
use HyperfTest\Fixtures\Request\CreateUserRequest;
use HyperfTest\Fixtures\Resource\ErrorResource;
use HyperfTest\Fixtures\Resource\PostResource;
use HyperfTest\Fixtures\Resource\UserResource;
use HyperfTest\Fixtures\Resource\WrappedResource;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
class RouteScannerTest extends AbstractTestCase
{
    public function testScansAllFixtureOperations()
    {
        $operations = $this->scan();

        $this->assertArrayHasKey(CreateUserAction::class . '::handle', $operations);
        $this->assertArrayHasKey(GetPostAction::class . '::handle', $operations);
        $this->assertArrayHasKey(CreatePostAction::class . '::handle', $operations);
        $this->assertArrayHasKey(ListPostsAction::class . '::handle', $operations);
        $this->assertArrayHasKey(ListCommentsAction::class . '::handle', $operations);
        $this->assertArrayHasKey(GetCommentAction::class . '::handle', $operations);
        $this->assertArrayHasKey(AdminStatsAction::class . '::handle', $operations);
        $this->assertArrayHasKey(ReportAction::class . '::handle', $operations);
        $this->assertArrayHasKey(StatusAction::class . '::handle', $operations);

        $this->assertCount(9, $operations);
    }

    public function testGroupAttributeIsDetected()
    {
        $operation = $this->scan()[AdminStatsAction::class . '::handle'];

        $this->assertSame(['admin'], $operation->groups);
        $this->assertSame([], $this->scan()[CreateUserAction::class . '::handle']->groups);
    }

    public function testMultipleGroupsAreAssigned()
    {
        $operation = $this->scan()[StatusAction::class . '::handle'];

        $this->assertSame(['public', 'internal'], $operation->groups);
    }

    public function testApiDocTagsReplaceInferredOnes()
    {
        $operation = $this->scan()[ReportAction::class . '::handle'];

        // The namer would infer ["Reports"]; the attribute's tags win.
        $this->assertSame(['Reports', 'Internal'], $operation->tags);
    }

    public function testTierOneOperationIsFullyInferred()
    {
        $operation = $this->scan()[CreateUserAction::class . '::handle'];

        $this->assertSame('POST', $operation->httpMethod);
        $this->assertSame('/v1/users', $operation->path);
        $this->assertSame('Create user', $operation->summary);
        $this->assertSame(['Users'], $operation->tags);
        $this->assertSame('CreateUserAction.handle', $operation->operationId);
        $this->assertSame(CreateUserRequest::class, $operation->formRequest);
        $this->assertSame('oauth2_client', $operation->authGuard);

        // Return-type inference documented the 200 response.
        $response = $operation->findResponse(200);
        $this->assertNotNull($response);
        $this->assertSame(UserResource::class, $response->schema);
    }

    public function testTierTwoAppliesInterfaceDocumentation()
    {
        $operation = $this->scan()[GetPostAction::class . '::handle'];

        $this->assertSame('GET', $operation->httpMethod);
        $this->assertSame('/v1/posts/{postId}', $operation->path);
        $this->assertSame('Get post', $operation->summary);
        $this->assertSame('Retrieve a single post by ID.', $operation->description);
        $this->assertSame(['Posts'], $operation->tags);

        $notFound = $operation->findResponse(404);
        $this->assertNotNull($notFound);
        $this->assertSame(ErrorResource::class, $notFound->schema);
        $this->assertSame('Post not found.', $notFound->description);

        // Path parameter typed from the method signature.
        $this->assertArrayHasKey('postId', $operation->parameters);
        $this->assertSame('integer', $operation->parameters['postId']->type);
        $this->assertSame('path', $operation->parameters['postId']->in);
    }

    public function testTierThreeAppliesExternalDefinition()
    {
        $operation = $this->scan()[CreatePostAction::class . '::handle'];

        $this->assertSame('Create post', $operation->summary);
        $this->assertSame(['Posts'], $operation->tags);
        $this->assertSame('authentik', $operation->authGuard);

        $created = $operation->findResponse(201);
        $this->assertNotNull($created);
        $this->assertSame(PostResource::class, $created->schema);

        // Explicit responses suppress return-type inference.
        $this->assertNull($operation->findResponse(200));
        $this->assertCount(2, $operation->responses);

        // Path parameter defaults to string for untyped-hint params.
        $this->assertSame('string', $operation->parameters['userId']->type);
    }

    public function testWithResourceAttributeDetection()
    {
        $operation = $this->scan()[ListPostsAction::class . '::handle'];

        $response = $operation->findResponse(200);
        $this->assertNotNull($response);
        $this->assertSame(PostResource::class, $response->schema);
        $this->assertNull($operation->authGuard);
    }

    public function testCollectionReturnIsDetected()
    {
        $operation = $this->scan()[ListPostsAction::class . '::handle'];

        $this->assertTrue($operation->findResponse(200)->arrayOf);
        $this->assertNull($this->scan()[GetPostAction::class . '::handle']->findResponse(200)->arrayOf);
    }

    public function testPaginatedFlagIsReadFromAttribute()
    {
        $operation = $this->scan()[ListPostsAction::class . '::handle'];

        $this->assertTrue($operation->paginated);
        $this->assertNull($this->scan()[GetPostAction::class . '::handle']->paginated);
    }

    public function testCoreInferenceRunsWithoutConfiguredStrategies()
    {
        $config = $this->config();
        $config['strategies'] = [];

        $operation = $this->scanWith($config)[CreateUserAction::class . '::handle'];

        // Core inference always runs: FormRequest parameter and return type.
        $this->assertSame(CreateUserRequest::class, $operation->formRequest);
        $this->assertSame(UserResource::class, $operation->findResponse(200)->schema);

        // The integration strategies are gone; no guard detection.
        $this->assertNull($operation->authGuard);
    }

    public function testStrategyListOrderDoesNotChangePrecedence()
    {
        $config = $this->config();
        $config['strategies'] = [
            new MenumbingJsonResourceStrategy(),
            new MenumbingAuthStrategy(TestAuthAttribute::class),
            new MenumbingResourceStrategy(TestWithResourceAttribute::class),
        ];

        $operations = $this->scanWith($config);

        // Detection still ran, the pagination envelope was built, and the
        // menumbing fields were added despite being listed first.
        $schema = $operations[ListCommentsAction::class . '::handle']->findResponse(200)->schema;
        $this->assertInstanceOf(PaginationSchema::class, $schema);
        $this->assertSame(PaginationMeta::class, $schema->property('meta')->refClass);
        $this->assertSame([MenumbingResourceFields::class], $schema->additionalFieldClasses);

        // Core return-type inference still ran before the decoration: the
        // response it created got the plain envelope and menumbing fields.
        $single = $operations[GetCommentAction::class . '::handle']->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $single);
        $this->assertSame(WrappedResource::class, $single->property('data')->refClass);
        $this->assertSame([MenumbingResourceFields::class], $single->additionalFieldClasses);
    }

    public function testExplicitDiscoveryOnlyIncludesMarkedRoutes()
    {
        $config = $this->config();
        $config['discovery']['mode'] = 'explicit';

        $paths = [];
        foreach ((new RouteScanner($config))->scan() as $operation) {
            $paths[] = $operation->path;
        }

        sort($paths);

        // Only the #[ApiDoc]-marked routes: the grouped AdminStatsAction and
        // StatusAction, the tagged ReportAction, the paginated ListPostsAction
        // and ListCommentsAction, and the Tier 3 CreatePostAction.
        $this->assertSame(
            ['/v1/admin/stats', '/v1/comments', '/v1/posts', '/v1/reports/summary', '/v1/status', '/v1/users/{userId}/posts'],
            $paths
        );
    }

    public function testExcludePatternsFilterRoutesByPath()
    {
        $config = $this->config();
        $config['discovery']['exclude'] = ['#^/v1/admin/#', '#/posts$#'];

        $paths = [];
        foreach ((new RouteScanner($config))->scan() as $operation) {
            $paths[] = $operation->path;
        }

        sort($paths);

        $this->assertSame(
            ['/v1/comments', '/v1/comments/{commentId}', '/v1/posts/{postId}', '/v1/reports/summary', '/v1/status', '/v1/users'],
            $paths
        );
    }

    public function testAutoControllerWithoutMethodsYieldsNoOperation()
    {
        $operations = $this->scan();

        // The MutedAction fixture declares defaultMethods: []; none of its
        // methods are routable, so it contributes nothing to the document.
        $this->assertArrayNotHasKey(MutedAction::class . '::handle', $operations);
        $this->assertCount(9, $operations);
    }

    public function testInvalidExcludePatternIsRejected()
    {
        $config = $this->config();
        $config['discovery']['exclude'] = ['/[invalid'];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid discovery exclude pattern');

        (new RouteScanner($config))->scan();
    }

    public function testInvalidNamerIsRejected()
    {
        $config = $this->config();
        $config['discovery']['namer'] = stdClass::class;

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Operation namer [stdClass] must implement');

        new RouteScanner($config);
    }

    public function testCustomNamerLabelsOperations()
    {
        $config = $this->config();
        $config['discovery']['namer'] = ControllerStyleNamer::class;

        $operations = [];
        foreach ((new RouteScanner($config))->scan() as $operation) {
            $operations[$operation->controller . '::' . $operation->controllerMethod] = $operation;
        }

        $operation = $operations[CreateUserAction::class . '::handle'];

        $this->assertSame('Handle', $operation->summary);
        $this->assertSame('CreateUserActions', $operation->tags[0]);
        // operationId is inherited from ActionOperationNamer.
        $this->assertSame('CreateUserAction.handle', $operation->operationId);
    }

    /**
     * @return array<string, ApiOperation>
     */
    private function scan(): array
    {
        return $this->scanWith($this->config());
    }

    /**
     * @return array<string, ApiOperation>
     */
    private function scanWith(array $config): array
    {
        $operations = [];
        foreach ((new RouteScanner($config))->scan() as $operation) {
            $operations[$operation->controller . '::' . $operation->controllerMethod] = $operation;
        }

        return $operations;
    }

    private function config(): array
    {
        return [
            'scan' => ['paths' => [__DIR__ . '/../Fixtures/Action']],
            'strategies' => [
                new MenumbingAuthStrategy(TestAuthAttribute::class),
                new MenumbingResourceStrategy(TestWithResourceAttribute::class),
                new MenumbingJsonResourceStrategy(),
            ],
        ];
    }
}
