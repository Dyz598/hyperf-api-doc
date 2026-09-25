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

namespace HyperfApiDoc\Filter;

use HyperfApiDoc\Model\ApiOperation;

/**
 * Matches operations assigned to one of the given groups
 * (#[ApiDoc(group: ...)]).
 *
 * Including null in the list also matches ungrouped operations:
 * new GroupFilter(['customer', null]) — customer group plus shared endpoints.
 */
class GroupFilter implements ApiOperationFilter
{
    /**
     * @param array<int, ?string> $groups
     */
    public function __construct(protected array $groups) {}

    public function matches(ApiOperation $operation): bool
    {
        if ($operation->groups === []) {
            return in_array(null, $this->groups, true);
        }

        foreach ($this->groups as $group) {
            if ($group !== null && in_array($group, $operation->groups, true)) {
                return true;
            }
        }

        return false;
    }
}
