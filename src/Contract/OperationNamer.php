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

use ReflectionClass;
use ReflectionMethod;

/**
 * Labels the operations the scanner discovers. Only consulted when nothing
 * explicit exists: Tier 2/3 documentation and #[ApiDoc] tags win.
 */
interface OperationNamer
{
    /**
     * @return array<int, string>
     */
    public function tags(ReflectionClass $class, ReflectionMethod $method): array;

    public function summary(ReflectionClass $class, ReflectionMethod $method): ?string;

    public function operationId(ReflectionClass $class, ReflectionMethod $method): ?string;
}
