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

namespace HyperfTest\Fixtures\Action\Muted;

use Hyperf\HttpServer\Annotation\AutoController;

/**
 * An AutoController whose empty defaultMethods list disables routing for
 * every method; the scanner must yield no operation for it.
 */
#[AutoController(prefix: '/v1/muted', defaultMethods: [])]
class MutedAction
{
    public function handle(): void {}
}
