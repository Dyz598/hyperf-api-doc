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

namespace HyperfApiDoc\Extension;

use BackedEnum;
use HyperfApiDoc\Contract\ApiEnumDocumented;
use HyperfApiDoc\Contract\SpecExtension;
use HyperfApiDoc\Model\ApiProperty;

/**
 * Adds x-apidog-enum to enum-backed properties whose enum implements
 * ApiEnumDocumented, so Apidog shows each value with a name and a
 * description.
 */
class ApidogEnumExtension implements SpecExtension
{
    public function extend(object $node): array
    {
        if (! $node instanceof ApiProperty
            || $node->enumClass === null
            || ! is_a($node->enumClass, ApiEnumDocumented::class, true)) {
            return [];
        }

        $entries = [];

        /** @var ApiEnumDocumented&BackedEnum $case */
        foreach ($node->enumClass::cases() as $case) {
            $entry = ['value' => $case->value];

            if ($case->apiDescription() !== null) {
                $entry['description'] = $case->apiDescription();
            }

            $entries[] = $entry;
        }

        return ['x-apidog-enum' => $entries];
    }
}
