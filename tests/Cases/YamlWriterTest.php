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

use HyperfApiDoc\Renderer\YamlWriter;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 * @coversNothing
 */
class YamlWriterTest extends AbstractTestCase
{
    public function testEncodesAndRoundTrips()
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => [
                'schemas' => [
                    'Thing' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 1],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
        ];

        $yaml = (new YamlWriter())->encode($spec);
        $parsed = Yaml::parse($yaml);

        // An empty object renders as the {} flow mapping; both an empty
        // map and list parse back as an empty PHP array.
        $this->assertSame([], $parsed['paths']);
        $this->assertSame($spec['components'], $parsed['components']);
        $this->assertSame($spec['info'], $parsed['info']);
        $this->assertSame('3.0.3', $parsed['openapi']);
    }

    public function testEmptyObjectsSurviveEncoding()
    {
        $yaml = (new YamlWriter())->encode(['paths' => new stdClass()]);

        $this->assertStringContainsString('paths: {', $yaml);
    }

    public function testEmptyListsSurviveEncoding()
    {
        $yaml = (new YamlWriter())->encode(['security' => []]);

        $this->assertStringContainsString('security: []', $yaml);
        $this->assertStringNotContainsString('security: {', $yaml);
    }
}
