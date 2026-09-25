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

use HyperfApiDoc\Command\ApiDocGenerateCommand;
use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Scanner\EnumRuleDetector;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\Constant\Gender;
use HyperfTest\Fixtures\Resource\ErrorResource;

/**
 * @internal
 * @coversNothing
 */
class CommandTest extends AbstractTestCase
{
    public function testSingleDocumentByDefault()
    {
        $targets = ApiDocGenerateCommand::buildTargets($this->config());

        $this->assertCount(1, $targets);
        $this->assertSame('api', $targets[0]['name']);
        $this->assertSame('api', $targets[0]['file']);
        $this->assertCount(9, $targets[0]['spec']['paths']);
    }

    public function testConfiguredDocumentsProduceSeparateFiles()
    {
        $config = $this->config();
        $config['documents'] = [
            'admin' => ['group' => 'admin', 'file' => 'admin-api'],
            'everything' => [],
        ];

        $targets = ApiDocGenerateCommand::buildTargets($config);

        $this->assertCount(2, $targets);

        [$admin, $everything] = $targets;

        $this->assertSame('admin', $admin['name']);
        $this->assertSame('admin-api', $admin['file']);
        $this->assertSame(['/v1/admin/stats'], array_keys($admin['spec']['paths']));

        $this->assertSame('everything', $everything['name']);
        $this->assertCount(9, $everything['spec']['paths']);
    }

    public function testDocumentGroupArraysMatchUngroupedOperations()
    {
        $config = $this->config();
        $config['documents'] = [
            'shared' => ['group' => [null]],
        ];

        [$shared] = ApiDocGenerateCommand::buildTargets($config);

        // Ungrouped operations only: the grouped routes are excluded.
        $this->assertNotContains('/v1/admin/stats', array_keys($shared['spec']['paths']));
        $this->assertNotContains('/v1/status', array_keys($shared['spec']['paths']));
        $this->assertCount(7, $shared['spec']['paths']);
    }

    public function testDocumentIncludesOperationsOfAnyAssignedGroup()
    {
        $config = $this->config();
        $config['documents'] = [
            'public' => ['group' => 'public'],
        ];

        [$public] = ApiDocGenerateCommand::buildTargets($config);

        // StatusAction belongs to both "public" and "internal"; it lands
        // in the public document via either.
        $this->assertSame(['/v1/status'], array_keys($public['spec']['paths']));
    }

    public function testDocumentsScopeComponentsToTheirRefs()
    {
        $config = $this->config();
        $config['documents'] = [
            'posts' => ['tags' => ['Posts']],
            'users' => ['tags' => ['Users']],
        ];

        [$posts, $users] = ApiDocGenerateCommand::buildTargets($config);

        $this->assertArrayHasKey('PostResource', $posts['spec']['components']['schemas']);
        $this->assertArrayNotHasKey('UserResource', $posts['spec']['components']['schemas']);

        $this->assertArrayHasKey('UserResource', $users['spec']['components']['schemas']);
        $this->assertArrayNotHasKey('PostResource', $users['spec']['components']['schemas']);
    }

    public function testCliFiltersCollapseToSingleTarget()
    {
        $targets = ApiDocGenerateCommand::buildTargets($this->config(), ['admin'], []);

        $this->assertCount(1, $targets);
        $this->assertSame(['/v1/admin/stats'], array_keys($targets[0]['spec']['paths']));
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
            'info' => ['title' => 'Fixture API', 'version' => '1.0.0'],
        ];
    }
}
