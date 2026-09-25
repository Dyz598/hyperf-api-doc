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
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Scanner\ApiHandlerContext;

/**
 * Default response inference: the candidates resolved from the handler's
 * return type (or "return new X(...)" body heuristic) become a 200
 * response — but only when no response is set yet, so attribute and
 * explicit documentation win.
 *
 * The first candidate describes the body; the rest are union members that
 * merge their properties into it at generation time.
 */
class ReturnTypeStrategy implements ApiOperationStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if ($operation->responses !== [] || $context->returnTypes === []) {
            return;
        }

        $classes = $context->returnTypes;
        $first = (string) array_shift($classes);

        $operation->response(200, $first, configure: function (ApiResponse $response) use ($classes, $context) {
            if ($context->returnsCollection) {
                $response->collection();
            }

            if ($classes !== []) {
                $response->merge(...$classes);
            }
        });
    }
}
