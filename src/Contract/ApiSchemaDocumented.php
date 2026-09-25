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

use HyperfApiDoc\Model\ApiSchema;

/**
 * Documents the body schema of a data class: field descriptions, examples,
 * and enums. May be implemented by FormRequests, DTOs, resources, response
 * classes, and value objects.
 *
 * On classes with inference (FormRequests, DTOs) the fluent calls upsert
 * onto the inferred schema; explicit values override inference.
 */
interface ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void;
}
