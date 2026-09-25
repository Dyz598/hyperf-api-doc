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

namespace HyperfTest\Fixtures\Action\Report;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Attribute\ApiDoc;

/**
 * Fixture whose attribute tags replace the namespace-inferred ones.
 */
#[Controller]
#[ApiDoc(tags: ['Reports', 'Internal'])]
class ReportAction
{
    #[GetMapping('/v1/reports/summary')]
    public function handle(): string
    {
        return 'ok';
    }
}
