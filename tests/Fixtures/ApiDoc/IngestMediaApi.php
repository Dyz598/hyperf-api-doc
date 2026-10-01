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

namespace HyperfTest\Fixtures\ApiDoc;

use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;
use HyperfTest\Fixtures\DTO\MediaUploadedData;

class IngestMediaApi implements ApiOperationDocumented
{
    public function documentApi(ApiOperation $api): void
    {
        $api
            ->summary('Ingest Media')
            ->tags('Media')
            ->response(200, MediaUploadedData::class, 'Media ingested.');
    }
}
