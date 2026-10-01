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

use DateTimeInterface;
use HyperfApiDoc\Contract\ApiSchemaDocumented;
use HyperfApiDoc\Model\ApiSchema;

final class CamelFieldData implements ApiSchemaDocumented
{
    public function __construct(
        public readonly string $downloadUrl,
        public readonly int $fileSize,
        public readonly ?DateTimeInterface $expiresAt = null,
    ) {}

    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema->descriptions([
            'download_url' => 'Signed playback URL.',
            'file_size' => 'Size in bytes.',
        ]);
    }
}
