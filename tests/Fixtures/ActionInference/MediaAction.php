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

namespace HyperfTest\Fixtures\ActionInference;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfTest\Fixtures\ApiDoc\IngestMediaApi;
use HyperfTest\Fixtures\ApiDoc\ListMediaApi;
use HyperfTest\Fixtures\ApiDoc\UploadMediaApi;
use HyperfTest\Fixtures\Attribute\TestAuthAttribute;
use HyperfTest\Fixtures\Attribute\TestWithResourceAttribute;
use HyperfTest\Fixtures\DTO\MediaUploadedData;
use HyperfTest\Fixtures\Request\UploadMediaRequest;

/**
 * Inference contract fixture: guarded multipart upload with an enum path
 * parameter, an enveloped collection GET, and an un-enveloped raw POST.
 */
#[Controller]
class MediaAction
{
    #[PostMapping('/v1/media/{code}/upload')]
    #[TestAuthAttribute('oauth2_client')]
    #[TestWithResourceAttribute]
    #[ApiDoc(UploadMediaApi::class)]
    public function upload(string $code, UploadMediaRequest $request): mixed
    {
        return new MediaUploadedData('1', 'media/1/a.png', 1024);
    }

    #[GetMapping('/v1/media')]
    #[TestAuthAttribute('oauth2_client')]
    #[TestWithResourceAttribute]
    #[ApiDoc(ListMediaApi::class)]
    public function index(): mixed
    {
        return [];
    }

    #[PostMapping('/v1/media/ingest')]
    #[ApiDoc(IngestMediaApi::class)]
    public function ingest(): mixed
    {
        return new MediaUploadedData('1', 'media/1/a.png', 1024);
    }
}
