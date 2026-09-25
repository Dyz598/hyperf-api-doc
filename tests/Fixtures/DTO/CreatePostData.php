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

namespace HyperfTest\Fixtures\DTO;

use HyperfTest\Fixtures\Constant\UserType;

final class CreatePostData
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly UserType $type,
        public readonly ?string $summary = null,
        public readonly bool $published = false,
    ) {}
}
