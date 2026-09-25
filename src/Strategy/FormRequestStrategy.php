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

namespace HyperfApiDoc\Strategy;

use HyperfApiDoc\Contract\ApiOperationStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;
use HyperfApiDoc\Support\HyperfClasses;
use ReflectionNamedType;

/**
 * Default request inference: a FormRequest-typed handler parameter marks
 * the request body source, parsed from its validation rules.
 */
class FormRequestStrategy implements ApiOperationStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if ($operation->formRequest !== null) {
            return;
        }

        foreach ($context->method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType
                && ! $type->isBuiltin()
                && class_exists($type->getName())
                && is_subclass_of($type->getName(), HyperfClasses::FORM_REQUEST)) {
                $operation->formRequest = $type->getName();

                return;
            }
        }
    }
}
