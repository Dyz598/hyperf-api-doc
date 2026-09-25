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

namespace HyperfApiDoc\Strategy;

use HyperfApiDoc\Contract\ResponseDecoratorStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Scanner\ApiHandlerContext;
use HyperfApiDoc\Schema\JsonResourceSchema;
use HyperfApiDoc\Schema\PaginationSchema;
use HyperfApiDoc\Support\HyperfClasses;
use ReflectionClass;

/**
 * Default response envelopes: JsonResource bodies wrap under the
 * resource's own $wrap key (a declared `?string $wrap = null` opts out),
 * and paginated operations get the pagination envelope instead.
 *
 * Runs in the decoration phase, after responses exist; built schemas are
 * left alone so explicit documentation wins.
 */
class JsonResourceStrategy implements ResponseDecoratorStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if (! class_exists(HyperfClasses::JSON_RESOURCE)) {
            return;
        }

        foreach ($operation->responses as $response) {
            $class = $response->schema;

            if (! is_string($class) || ! is_subclass_of($class, HyperfClasses::JSON_RESOURCE)) {
                continue;
            }

            $wrapKey = $this->wrapDefault($class);

            if ($wrapKey === null) {
                continue;
            }

            $response->schema = $operation->paginated
                ? new PaginationSchema($class, $wrapKey, is_string($operation->paginated) ? $operation->paginated : null)
                : new JsonResourceSchema($class, $wrapKey, $this->isCollection($response, $class));

            // The collection shape now lives inside the envelope.
            $response->arrayOf = null;
        }
    }

    private function isCollection(ApiResponse $response, string $class): bool
    {
        if ($response->arrayOf === true) {
            return true;
        }

        return class_exists(HyperfClasses::RESOURCE_COLLECTION)
            && is_subclass_of($class, HyperfClasses::RESOURCE_COLLECTION);
    }

    private function wrapDefault(string $class): ?string
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        $wrap = array_key_exists('wrap', $defaults) ? $defaults['wrap'] : 'data';

        return is_string($wrap) && $wrap !== '' ? $wrap : null;
    }
}
