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

namespace HyperfTest\Fixtures\Action\Admin;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfTest\Fixtures\Resource\UserResource;

/**
 * Grouped fixture for multi-document generation tests.
 */
#[Controller]
#[ApiDoc(group: 'admin')]
class AdminStatsAction
{
    #[GetMapping('/v1/admin/stats')]
    public function handle(): UserResource
    {
        return new UserResource();
    }
}
