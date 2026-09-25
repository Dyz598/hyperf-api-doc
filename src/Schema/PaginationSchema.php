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

/**
 * The paginated collection envelope: the items under the wrap key plus
 * the paginator's links and meta objects.
 */
class PaginationSchema extends JsonResourceSchema
{
    public function __construct(
        string $resourceClass,
        string $wrapKey = 'data',
        ?string $metaClass = null,
    ) {
        parent::__construct($resourceClass, $wrapKey, collection: true);

        $this->property('links')->ref(PaginationLinks::class);
        $this->property('meta')->ref($metaClass ?? PaginationMeta::class);
    }
}
