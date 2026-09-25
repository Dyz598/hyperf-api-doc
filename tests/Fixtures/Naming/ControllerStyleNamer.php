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

namespace HyperfTest\Fixtures\Naming;

use HyperfApiDoc\Naming\ActionOperationNamer;
use HyperfApiDoc\Support\Inflector;
use ReflectionClass;
use ReflectionMethod;

/**
 * Controller-style naming fixture: UserController::store -> "Store" / "Users".
 */
class ControllerStyleNamer extends ActionOperationNamer
{
    public function tags(ReflectionClass $class, ReflectionMethod $method): array
    {
        $base = preg_replace('/Controller$/', '', $class->getShortName());

        return [Inflector::pluralize((string) $base)];
    }

    public function summary(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        return ucfirst($method->getName());
    }
}
