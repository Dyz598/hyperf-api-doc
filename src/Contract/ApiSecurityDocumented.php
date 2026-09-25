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

namespace HyperfApiDoc\Contract;

use HyperfApiDoc\Security\SecurityRegistry;

/**
 * Registers security schemes at the document level. Register definition
 * classes as class-string entries under the "security" config key.
 */
interface ApiSecurityDocumented
{
    public function documentApiSecurity(SecurityRegistry $security): void;
}
