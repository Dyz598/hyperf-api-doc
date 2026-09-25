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

namespace HyperfApiDoc\Schema;

use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiSchema;

/**
 * The Hyperf JsonResource envelope: the resource — or a list of them —
 * under its own wrap key. Additional fields (menumbing's meta, custom
 * headers) merge in via additionalFields().
 */
class JsonResourceSchema extends ApiSchema
{
    public function __construct(
        string $resourceClass,
        string $wrapKey = 'data',
        bool $collection = false,
    ) {
        $data = $this->property($wrapKey);

        if ($collection) {
            $data->type('array')->items((new ApiProperty())->ref($resourceClass));
        } else {
            $data->ref($resourceClass);
        }
    }
}
