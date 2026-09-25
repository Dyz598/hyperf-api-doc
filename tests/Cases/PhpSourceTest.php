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

use HyperfApiDoc\Support\PhpSource;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class PhpSourceTest extends TestCase
{
    private const SOURCE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Http;

        use App\Support\Wrapped as Aliased;
        use App\Support\PostResource;
        use function strlen;

        final class ThingController
        {
            public function show(): PostResource
            {
                return new PostResource();
            }
        }

        final class Other {}
        PHP;

    public function testParsesNamespaceAndClasses()
    {
        $source = new PhpSource(self::SOURCE);

        $this->assertSame('App\Http', $source->namespace());
        $this->assertSame(
            ['App\Http\ThingController', 'App\Http\Other'],
            $source->classes()
        );
    }

    public function testResolvesNamesAgainstUses()
    {
        $source = new PhpSource(self::SOURCE);

        $this->assertSame(
            ['Aliased' => 'App\Support\Wrapped', 'PostResource' => 'App\Support\PostResource'],
            $source->uses()
        );

        $this->assertSame('App\Support\PostResource', $source->resolve('PostResource'));
        $this->assertSame('App\Support\Wrapped', $source->resolve('Aliased'));
        $this->assertSame('App\Support\PostResource\Nested', $source->resolve('PostResource\Nested'));
        $this->assertSame('App\Http\Local', $source->resolve('Local'));
        $this->assertSame('App\Other\Space', $source->resolve('\App\Other\Space'));
    }

    public function testFromFileHandlesMissingFiles()
    {
        $this->assertNull(PhpSource::fromFile(__DIR__ . '/does-not-exist.php'));
    }
}
