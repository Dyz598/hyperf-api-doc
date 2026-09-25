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

use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiParameter;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Model\ApiSchema;

/**
 * Computes x- spec extensions for rendered nodes. Register implementations
 * via the output.extensions config; consulted for every document, operation,
 * response, schema, parameter, and property being rendered. Extensions set
 * explicitly through extensions() win on key collisions.
 */
interface SpecExtension
{
    /**
     * @param ApiDocument|ApiOperation|ApiParameter|ApiProperty|ApiResponse|ApiSchema $node
     * @return array<string, mixed> x- extension data for the node, or []
     */
    public function extend(object $node): array;
}
