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
 * Marker for strategies that decorate documented responses — envelopes,
 * meta, and other facts that only exist once responses are set. They run
 * after the core inference strategies, whatever their config position.
 */
interface ResponseDecoratorStrategy extends ApiOperationStrategy {}
