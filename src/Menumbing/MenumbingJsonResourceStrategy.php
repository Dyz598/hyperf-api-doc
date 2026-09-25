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

namespace HyperfApiDoc\Menumbing;

use HyperfApiDoc\Contract\ResponseDecoratorStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;
use HyperfApiDoc\Schema\JsonResourceSchema;

/**
 * Documents menumbing/resource's additional fields: every JsonResource
 * envelope gains the request meta object. Runs last, merging into
 * whatever the core envelopes put in place.
 */
class MenumbingJsonResourceStrategy implements ResponseDecoratorStrategy
{
    /**
     * @param class-string $additionalFields override for testing or forks
     */
    public function __construct(
        protected string $additionalFields = MenumbingResourceFields::class,
    ) {}

    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        foreach ($operation->responses as $response) {
            if ($response->schema instanceof JsonResourceSchema) {
                $response->schema->additionalFields($this->additionalFields);
            }
        }
    }
}
