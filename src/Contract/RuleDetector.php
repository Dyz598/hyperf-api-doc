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

use HyperfApiDoc\Scanner\RuleFacts;

/**
 * Detects OpenAPI facts (enum classes/values, type, format) from a
 * validation rule object or custom rule name. Register implementations
 * via the "rule_detectors" config key; the first non-null result wins.
 */
interface RuleDetector
{
    public function detect(object|string $rule): ?RuleFacts;
}
