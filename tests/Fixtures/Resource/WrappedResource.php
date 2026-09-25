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

namespace HyperfTest\Fixtures\Resource;

use Hyperf\Resource\Json\JsonResource;

/**
 * Resource inheriting the framework's $wrap = 'data' default.
 */
class WrappedResource extends JsonResource
{
    public function toArray(): array
    {
        return [];
    }
}
