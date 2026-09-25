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

namespace HyperfTest\Fixtures\ActionOptOut;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Request\CreateUserRequest;

/**
 * Fixture opting out of the configured default responses.
 */
#[Controller]
#[TestAuthAttribute('oauth2_client')]
class HealthAction implements ApiOperationDocumented
{
    #[PostMapping('/v1/health')]
    public function handle(CreateUserRequest $request): string
    {
        return 'ok';
    }

    public function documentApi(ApiOperation $api): void
    {
        $api->withoutDefaultResponses();
    }
}
