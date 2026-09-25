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

class SingleSchemeSecurity implements ApiSecurityDocumented
{
    public function documentApiSecurity(SecurityRegistry $security): void
    {
        $security->bearer(
            name: 'legacyToken',
            bearerFormat: 'JWT',
            description: 'Partner bearer authentication.'
        );
    }
}
