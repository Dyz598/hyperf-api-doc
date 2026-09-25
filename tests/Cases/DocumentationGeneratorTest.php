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
use HyperfApiDoc\Generator\DocumentationGenerator;
use HyperfApiDoc\Generator\SchemaRegistry;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfApiDoc\Scanner\EnumRuleDetector;
use HyperfApiDoc\Scanner\RouteScanner;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfTest\Fixtures\ApiDoc\ApplicationSecurity;
use HyperfTest\Fixtures\ApiDoc\SingleSchemeSecurity;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Constant\Gender;
use HyperfTest\Fixtures\DTO\CreateLoanData;
use HyperfTest\Fixtures\Resource\ErrorResource;
use HyperfTest\Fixtures\Resource\MetaResponseSchema;
use HyperfTest\Fixtures\Resource\UserResource;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
class DocumentationGeneratorTest extends AbstractTestCase
{
    public function testGeneratesCompleteSpec()
    {
        $spec = $this->generate();

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('Fixture API', $spec['info']['title']);
        $this->assertSame('2.0.0', $spec['info']['version']);

        $this->assertArrayHasKey('/v1/users', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/v1/users']);
        $this->assertArrayHasKey('/v1/posts/{postId}', $spec['paths']);
        $this->assertArrayHasKey('/v1/posts', $spec['paths']);

        $this->assertContains(['name' => 'Users'], $spec['tags']);
        $this->assertContains(['name' => 'Posts'], $spec['tags']);
    }

    public function testInferredOperationWithRequestAndAutoResponses()
    {
        $spec = $this->generate();
        $operation = $spec['paths']['/v1/users']['post'];

        $this->assertSame('Create user', $operation['summary']);
        $this->assertSame('CreateUserAction.handle', $operation['operationId']);
        $this->assertSame(['Users'], $operation['tags']);
        $this->assertSame([['oauth2' => []]], $operation['security']);

        // Request body parsed from the FormRequest rules. The request class
        // is marked #[ApiDocSchema] and is therefore a reusable component.
        $bodyRef = $operation['requestBody']['content']['application/json']['schema'];
        $this->assertSame(['$ref' => '#/components/schemas/CreateUserRequest'], $bodyRef);

        $bodySchema = $spec['components']['schemas']['CreateUserRequest'];
        $this->assertSame('string', $bodySchema['properties']['name']['type']);
        $this->assertSame(100, $bodySchema['properties']['name']['maxLength']);
        $this->assertSame('email', $bodySchema['properties']['email']['format']);
        $this->assertSame(['male', 'female'], $bodySchema['properties']['gender']['enum']);
        $this->assertSame('object', $bodySchema['properties']['address']['type']);
        $this->assertSame('array', $bodySchema['properties']['schedules']['type']);
        $this->assertContains('name', $bodySchema['required']);

        // Inferred 200 response plus auto 422/401.
        $this->assertArrayHasKey('200', $operation['responses']);
        $this->assertArrayHasKey('422', $operation['responses']);
        $this->assertArrayHasKey('401', $operation['responses']);
        $this->assertSame('Unprocessable Entity', $operation['responses']['422']['description']);
        $this->assertSame('Unauthorized', $operation['responses']['401']['description']);
    }

    public function testMarkedSchemasBecomeComponents()
    {
        $spec = $this->generate();

        // PostResource is marked #[ApiDocSchema] -> component + $ref.
        $this->assertArrayHasKey('PostResource', $spec['components']['schemas']);
        $postSchema = $spec['components']['schemas']['PostResource'];
        $this->assertSame('Unique post identifier.', $postSchema['properties']['id']['description']);

        $getPost = $spec['paths']['/v1/posts/{postId}']['get'];
        $okSchema = $getPost['responses']['200']['content']['application/json']['schema'];
        $this->assertSame(['$ref' => '#/components/schemas/PostResource'], $okSchema);

        // ErrorResource is marked #[ApiDocSchema] -> component.
        $this->assertArrayHasKey('ErrorResource', $spec['components']['schemas']);

        // Security schemes render under components.
        $this->assertSame('bearer', $spec['components']['securitySchemes']['oauth2']['scheme']);
        $this->assertSame('X-Authentik-Token', $spec['components']['securitySchemes']['authentikAuth']['name']);
    }

    public function testGuardMappingProducesPerOperationSecurity()
    {
        $spec = $this->generate();

        $this->assertSame(
            [['authentikAuth' => []]],
            $spec['paths']['/v1/users/{userId}/posts']['post']['security']
        );

        // Operations without an auth guard carry no security requirement.
        $this->assertArrayNotHasKey('security', $spec['paths']['/v1/posts']['get']);
    }

    public function testCollectionResponseRendersArrayOfSchema()
    {
        $spec = $this->generate();

        $schema = $spec['paths']['/v1/posts']['get']['responses']['200']['content']['application/json']['schema'];

        // Plain (non-JsonResource) fixtures are not wrapped: the body is a
        // plain array of the reusable PostResource component.
        $this->assertSame('array', $schema['type']);
        $this->assertSame(['$ref' => '#/components/schemas/PostResource'], $schema['items']);
    }

    public function testDefaultSchemeCoversUnguardedOperations()
    {
        $config = $this->config();
        $config['security']['publicAuth'] = [
            'default' => true,
            'type' => 'http',
            'scheme' => 'basic',
        ];

        $spec = $this->generate($config);

        // Guarded operations keep their mapped scheme.
        $this->assertSame(
            [['oauth2' => []]],
            $spec['paths']['/v1/users']['post']['security']
        );

        // Unguarded operations fall back to the default scheme.
        $this->assertSame(
            [['publicAuth' => []]],
            $spec['paths']['/v1/posts']['get']['security']
        );
    }

    public function testMultipleDefaultSchemesAreRejected()
    {
        $config = $this->config();
        $config['security']['publicAuth'] = ['default' => true, 'type' => 'http', 'scheme' => 'basic'];
        $config['security']['partnerAuth'] = ['default' => true, 'type' => 'http', 'scheme' => 'bearer'];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Only one security scheme may be marked as default');

        $this->generate($config);
    }

    public function testUndefinedSecuritySchemeIsRejected()
    {
        $config = $this->config();
        $config['scan']['paths'] = [__DIR__ . '/../Fixtures/ActionUndefined'];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('undefined security scheme [nonexistent]');

        $this->generate($config);
    }

    public function testSecurityDefinitionClassRegistersSchemes()
    {
        $config = $this->config();
        $config['security'][] = ApplicationSecurity::class;

        $spec = $this->generate($config);
        $schemes = $spec['components']['securitySchemes'];

        $this->assertArrayHasKey('partnerBearer', $schemes);
        $this->assertSame('http', $schemes['partnerBearer']['type']);
        $this->assertSame('bearer', $schemes['partnerBearer']['scheme']);
        $this->assertSame('JWT', $schemes['partnerBearer']['bearerFormat']);

        $this->assertArrayHasKey('internalKey', $schemes);
        $this->assertSame('apiKey', $schemes['internalKey']['type']);
        $this->assertSame('X-Internal-Key', $schemes['internalKey']['name']);
    }

    public function testKeyedSecurityDefinitionNamesItsSingleScheme()
    {
        $config = $this->config();
        $config['security']['partner'] = SingleSchemeSecurity::class;

        $spec = $this->generate($config);
        $schemes = $spec['components']['securitySchemes'];

        $this->assertArrayHasKey('partner', $schemes);
        $this->assertArrayNotHasKey('legacyToken', $schemes);
        $this->assertSame('bearer', $schemes['partner']['scheme']);
        $this->assertSame('Partner bearer authentication.', $schemes['partner']['description']);
    }

    public function testKeyedMultiSchemeSecurityDefinitionIsRejected()
    {
        $config = $this->config();
        $config['security']['app'] = ApplicationSecurity::class;

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must register exactly one');

        $this->generate($config);
    }

    public function testDuplicateSecuritySchemeNameIsRejected()
    {
        $config = $this->config();
        // The array entry collides with a scheme the earlier definition
        // registered.
        $config['security'][] = ApplicationSecurity::class;
        $config['security']['partnerBearer'] = ['type' => 'apiKey', 'parameterName' => 'X-Key'];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate security scheme name [partnerBearer]');

        $this->generate($config);
    }

    public function testInvalidSecurityDefinitionClassIsRejected()
    {
        $config = $this->config();
        $config['security'][] = stdClass::class;

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        $this->generate($config);
    }

    public function testNestedDependencyBecomesReusableComponent()
    {
        $resolver = new SchemaResolver([]);
        $registry = new SchemaRegistry();

        // Registering the marked root schema also registers its nested
        // dependency; both render as component refs.
        $registry->register(CreateLoanData::class, $resolver->resolve(CreateLoanData::class));
        foreach ($resolver->dependencies(CreateLoanData::class) as $dependency) {
            $registry->register($dependency, $resolver->resolve($dependency));
        }

        $document = new ApiDocument();
        $document->operations[] = $this->loanOperation('/v1/loans-a');
        $document->operations[] = $this->loanOperation('/v1/loans-b');

        $spec = (new OpenApiRenderer($registry, [], $resolver))->render($document);

        $created = $spec['paths']['/v1/loans-a']['post']['responses']['201']['content']['application/json']['schema'];
        $this->assertSame(['$ref' => '#/components/schemas/CreateLoanData'], $created);

        $loan = $spec['components']['schemas']['CreateLoanData'];
        $this->assertSame(['$ref' => '#/components/schemas/CustomerData'], $loan['properties']['customer']);
        $this->assertArrayHasKey('CustomerData', $spec['components']['schemas']);
    }

    public function testDuplicateResponseStatusIsRejected()
    {
        $operation = new ApiOperation();
        $operation->response(200);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Duplicate response status [200]');

        $operation->response(200);
    }

    public function testBuiltEnvelopeRendersWithCustomMeta()
    {
        $schemaResolver = new SchemaResolver();
        $registry = new SchemaRegistry();
        $registry->register(UserResource::class, $schemaResolver->resolve(UserResource::class));

        $envelope = new JsonResourceSchema(UserResource::class, collection: true);
        $envelope->property('meta')->ref(MetaResponseSchema::class);

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/manual';
        $operation->response(200, $envelope);

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = (new OpenApiRenderer($registry, [], $schemaResolver))->render($document);
        $schema = $spec['paths']['/v1/manual']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertSame('Current page number.', $schema['properties']['meta']['properties']['page']['description']);
    }

    public function testWithoutDefaultResponsesOptsOut()
    {
        $config = $this->config();
        $config['scan']['paths'] = [__DIR__ . '/../Fixtures/ActionOptOut'];

        // HealthAction has both a FormRequest and an auth guard, yet opts
        // out of the auto responses.
        $spec = $this->generate($config);
        $responses = $spec['paths']['/v1/health']['post']['responses'];

        $this->assertArrayNotHasKey('422', $responses);
        $this->assertArrayNotHasKey('401', $responses);
    }

    public function testAutoResponseStatusOverride()
    {
        $config = $this->config();
        $config['responses']['validation'] = ['status' => 400, 'schema' => ErrorResource::class];

        $spec = $this->generate($config);

        $this->assertArrayHasKey('400', $spec['paths']['/v1/users']['post']['responses']);
    }

    private function loanOperation(string $path): ApiOperation
    {
        $operation = new ApiOperation();
        $operation->httpMethod = 'POST';
        $operation->path = $path;
        $operation->response(201, CreateLoanData::class);

        return $operation;
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
            'rule_detectors' => [new EnumRuleDetector(['in_gender' => Gender::class])],
            'security' => [
                'oauth2' => [
                    'guards' => ['oauth2_client'],
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
                'authentikAuth' => [
                    'guards' => ['authentik'],
                    'type' => 'apiKey',
                    'in' => 'header',
                    'parameterName' => 'X-Authentik-Token',
                ],
            ],
            'responses' => [
                'validation' => ErrorResource::class,
                'unauthorized' => ErrorResource::class,
            ],
            'info' => ['title' => 'Fixture API', 'version' => '2.0.0'],
        ];
    }

    private function generate(array $config = []): array
    {
        $config = $config ?: $this->config();

        $scanner = new RouteScanner($config);
        $resolver = new SchemaResolver($config);
        $generator = new DocumentationGenerator($scanner, $resolver, $config);
        $document = $generator->generate();

        $renderer = new OpenApiRenderer($generator->registry(), $config, $resolver);

        return $renderer->render($document);
    }
}
