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

/**
 * Documents the API meaning of each case of a backed enum. Implement on the
 * enum so spec extensions (e.g. x-apidog-enum) can describe its values.
 */
interface ApiEnumDocumented
{
    public function apiDescription(): ?string;
}
