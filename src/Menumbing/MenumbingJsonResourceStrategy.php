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
use HyperfApiDoc\Support\HyperfClasses;

/**
 * Documents menumbing/resource's response behavior:
 *
 *  - every JsonResource envelope gains the request meta object;
 *  - under #[WithResource], plain class-string bodies (DTOs) gain the
 *    same {data, meta} envelope the runtime wraps them in.
 *
 * Runs last, merging into whatever the core envelopes put in place;
 * JsonResource classes keep the envelope the core strategy built from
 * their own $wrap.
 */
class MenumbingJsonResourceStrategy implements ResponseDecoratorStrategy
{
    /**
     * @param class-string $additionalFields override for testing or forks
     * @param class-string $withResourceAttribute override for testing or forks
     */
    public function __construct(
        protected string $additionalFields = MenumbingResourceFields::class,
        protected string $withResourceAttribute = MenumbingResourceStrategy::WITH_RESOURCE_ATTRIBUTE,
    ) {}

    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        $this->wrapWithResourceBodies($operation, $context);

        foreach ($operation->responses as $response) {
            if ($response->schema instanceof JsonResourceSchema) {
                $response->schema->additionalFields($this->additionalFields);
            }
        }
    }

    /**
     * The runtime wraps any returned body under the resource wrap key when
     * #[WithResource] is present; mirror that for class-string schemas the
     * core envelope strategy left alone (DTOs and documented classes).
     */
    private function wrapWithResourceBodies(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if ($context->attribute($this->withResourceAttribute) === null) {
            return;
        }

        foreach ($operation->responses as $response) {
            $class = $response->schema;

            if (! is_string($class) || ! class_exists($class) || $this->isJsonResource($class)) {
                continue;
            }

            $response->schema = new JsonResourceSchema($class, collection: ($response->arrayOf ?? false));
            $response->arrayOf = null;
        }
    }

    private function isJsonResource(string $class): bool
    {
        return class_exists(HyperfClasses::JSON_RESOURCE)
            && is_subclass_of($class, HyperfClasses::JSON_RESOURCE);
    }
}
