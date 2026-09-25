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

namespace HyperfApiDoc\Naming;

use HyperfApiDoc\Contract\OperationNamer;
use HyperfApiDoc\Support\Inflector;
use ReflectionClass;
use ReflectionMethod;

/**
 * Action-style naming: CreatePostAction -> "Create post",
 * Acem\Action\Post\... -> "Posts", CreatePostAction::handle -> "CreatePostAction.handle".
 */
class ActionOperationNamer implements OperationNamer
{
    public function tags(ReflectionClass $class, ReflectionMethod $method): array
    {
        $tag = Inflector::tagFromNamespace($class->getNamespaceName());

        return $tag !== null ? [$tag] : [];
    }

    public function summary(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        return Inflector::actionSummary($class->getShortName());
    }

    public function operationId(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        return sprintf('%s.%s', $class->getShortName(), $method->getName());
    }
}
