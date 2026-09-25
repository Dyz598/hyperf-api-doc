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
 * Resource wrapping under a custom key.
 */
class ResultWrappedResource extends JsonResource
{
    public ?string $wrap = 'result';

    public function toArray(): array
    {
        return [];
    }
}
