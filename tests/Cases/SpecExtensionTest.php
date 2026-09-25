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

use HyperfApiDoc\Contract\SpecExtension;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Extension\ApidogEnumExtension;
use HyperfApiDoc\Generator\SchemaRegistry;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfTest\Fixtures\Constant\AccountStatus;
use HyperfTest\Fixtures\Constant\UserStatus;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class SpecExtensionTest extends TestCase
{
    public function testApidogEnumExtensionDescribesDocumentedEnums()
    {
        $property = (new ApiSchema())
            ->enums(['status' => AccountStatus::class])
            ->property('status');

        $data = (new ApidogEnumExtension())->extend($property);

        $this->assertSame([
            ['value' => 'active', 'description' => 'Account is in good standing.'],
            ['value' => 'suspended'],
        ], $data['x-apidog-enum']);
    }

    public function testApidogEnumExtensionIgnoresUndocumentedEnumsAndOtherNodes()
    {
        $extension = new ApidogEnumExtension();

        $undocumented = (new ApiSchema())
            ->enums(['status' => UserStatus::class])
            ->property('status');

        $this->assertSame([], $extension->extend($undocumented));
        $this->assertSame([], $extension->extend(new ApiOperation()));
    }

    public function testSchemaEnumsRetainEnumClass()
    {
        $schema = new ApiSchema();
        $schema->enums(['status' => AccountStatus::class, 'role' => ['admin', 'user']]);

        $this->assertSame(AccountStatus::class, $schema->property('status')->enumClass);
        $this->assertNull($schema->property('role')->enumClass);
    }

    public function testRendererMergesApidogEnumExtensionIntoProperties()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [
            'output' => ['extensions' => [ApidogEnumExtension::class]],
        ]);

        $schema = new ApiSchema();
        $schema->enums(['status' => AccountStatus::class]);

        $rendered = $renderer->renderSchema($schema)['properties']['status'];

        $this->assertSame(['active', 'suspended'], $rendered['enum']);
        $this->assertSame(['value' => 'active', 'description' => 'Account is in good standing.'], $rendered['x-apidog-enum'][0]);
    }

    public function testRendererChainAppliesToDocumentsOperationsAndProperties()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [
            'output' => ['extensions' => [new class implements SpecExtension {
                public function extend(object $node): array
                {
                    if ($node instanceof ApiOperation) {
                        return ['x-flag' => 'operation'];
                    }

                    return $node instanceof ApiSchema ? ['x-flag' => 'schema'] : [];
                }
            }]],
        ]);

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/ping';

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = $renderer->render($document);

        $this->assertSame('operation', $spec['paths']['/v1/ping']['get']['x-flag']);
        $this->assertSame('schema', $renderer->renderSchema(new ApiSchema())['x-flag']);
    }

    public function testExplicitExtensionsWinOverTheChain()
    {
        $renderer = new OpenApiRenderer(new SchemaRegistry(), [
            'output' => ['extensions' => [new class implements SpecExtension {
                public function extend(object $node): array
                {
                    return $node instanceof ApiOperation ? ['x-flag' => 'chain'] : [];
                }
            }]],
        ]);

        $operation = new ApiOperation();
        $operation->httpMethod = 'GET';
        $operation->path = '/v1/ping';
        $operation->extensions(['x-flag' => 'explicit']);

        $document = new ApiDocument();
        $document->operation($operation);

        $spec = $renderer->render($document);

        $this->assertSame('explicit', $spec['paths']['/v1/ping']['get']['x-flag']);
    }

    public function testInvalidSpecExtensionConfigIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        (new OpenApiRenderer(new SchemaRegistry(), [
            'output' => ['extensions' => ['DateTime']],
        ]))->render(new ApiDocument());
    }

    public function testNonPrefixedExtensionKeyIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be x- prefixed');

        (new OpenApiRenderer(new SchemaRegistry(), [
            'output' => ['extensions' => [new class implements SpecExtension {
                public function extend(object $node): array
                {
                    return ['flag' => true];
                }
            }]],
        ]))->render(new ApiDocument());
    }
}
