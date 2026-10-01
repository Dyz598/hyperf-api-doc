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

use HyperfApiDoc\Generator\DocumentationGenerator;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceFields;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\RouteScanner;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Constant\AccountStatus;
use HyperfTest\Fixtures\DTO\MediaUploadedData;
use HyperfTest\Fixtures\Resource\ErrorResource;

/**
 * Pins the inference contract: default responses (including forbidden),
 * enum class-string parameters, WithResource envelopes for plain DTO
 * bodies, and request-body detection (multipart, GET skip).
 *
 * @internal
 * @coversNothing
 */
class InferenceContractTest extends AbstractTestCase
{
    public function testDefaultResponsesIncludeForbidden()
    {
        $operations = $this->generatedOperations();

        $upload = $operations['/v1/media/{code}/upload'];
        $this->assertNotNull($upload->findResponse(401), 'guarded endpoints get the 401 default');
        $this->assertNotNull($upload->findResponse(422), 'form request endpoints get the 422 default');
        $this->assertNotNull($upload->findResponse(403), 'operations marked ->forbidden() get the 403 default');

        $this->assertNull(
            $operations['/v1/media']->findResponse(403),
            'unmarked operations do not get the 403 default'
        );

        $this->assertNull(
            $operations['/v1/media/ingest']->findResponse(401),
            'unguarded endpoints do not get the 401 default'
        );
    }

    public function testEnumClassStringPathParameter()
    {
        $code = $this->generatedOperations()['/v1/media/{code}/upload']->parameters['code'];

        $this->assertSame(['active', 'suspended'], $code->enum, 'enum class-strings expand to their case values');
        $this->assertSame(AccountStatus::class, $code->enumClass, 'enum class-strings keep the class for spec extensions');
        $this->assertSame('active', $code->example, 'the first case value seeds the example');
    }

    public function testWithResourceWrapsPlainClassStringBodies()
    {
        $operations = $this->generatedOperations();

        $uploaded = $operations['/v1/media/{code}/upload']->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $uploaded);
        $this->assertSame(MediaUploadedData::class, $uploaded->properties['data']->refClass);
        $this->assertContains(MenumbingResourceFields::class, $uploaded->additionalFieldClasses);

        $listed = $operations['/v1/media']->findResponse(200)->schema;
        $this->assertInstanceOf(JsonResourceSchema::class, $listed);
        $this->assertSame('array', $listed->properties['data']->type);
        $this->assertSame(MediaUploadedData::class, $listed->properties['data']->items->refClass);

        $this->assertSame(
            MediaUploadedData::class,
            $operations['/v1/media/ingest']->findResponse(200)->schema,
            'bodies without the WithResource attribute stay raw class-strings'
        );
    }

    public function testRequestBodyInference()
    {
        $operations = $this->generatedOperations();

        $this->assertSame(
            'multipart/form-data',
            $operations['/v1/media/{code}/upload']->requestBody->contentType,
            'file rules force multipart encoding'
        );

        $this->assertNull(
            $operations['/v1/media']->requestBody,
            'GET operations never get an auto-derived body'
        );
    }

    /**
     * @return array<string, ApiOperation> path => operation
     */
    private function generatedOperations(): array
    {
        $config = [
            'scan' => ['paths' => [__DIR__ . '/../Fixtures/ActionInference']],
            'strategies' => [
                new MenumbingAuthStrategy(TestAuthAttribute::class),
                new MenumbingResourceStrategy(TestWithResourceAttribute::class),
                new MenumbingJsonResourceStrategy(withResourceAttribute: TestWithResourceAttribute::class),
            ],
            'security' => [
                'oauth2' => [
                    'guards' => ['oauth2_client'],
                    'type' => 'http',
                    'scheme' => 'bearer',
                ],
            ],
            'responses' => [
                'validation' => ErrorResource::class,
                'unauthorized' => ErrorResource::class,
                'forbidden' => ErrorResource::class,
            ],
            'info' => ['title' => 'Inference API', 'version' => '1.0.0'],
        ];

        $document = (new DocumentationGenerator(
            new RouteScanner($config),
            new SchemaResolver($config),
            $config,
        ))->generate();

        $operations = [];
        foreach ($document->operations as $operation) {
            $operations[$operation->path] = $operation;
        }

        return $operations;
    }
}
