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

namespace HyperfTest\Fixtures\Action\Shared;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Attribute\ApiDoc;

/**
 * Fixture belonging to several groups at once.
 */
#[Controller]
#[ApiDoc(group: ['public', 'internal'])]
class StatusAction
{
    #[GetMapping('/v1/status')]
    public function handle(): string
    {
        return 'ok';
    }
}
