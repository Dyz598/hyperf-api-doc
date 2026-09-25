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

namespace HyperfTest\Fixtures\ApiDoc;

use HyperfApiDoc\Contract\ApiSecurityDocumented;
use HyperfApiDoc\Security\SecurityRegistry;

class ApplicationSecurity implements ApiSecurityDocumented
{
    public function documentApiSecurity(SecurityRegistry $security): void
    {
        $security->bearer(
            name: 'partnerBearer',
            bearerFormat: 'JWT',
            description: 'Partner JWT authentication.'
        );

        $security->apiKey(
            name: 'internalKey',
            parameterName: 'X-Internal-Key',
            description: 'Internal service API key.'
        );
    }
}
