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

use HyperfApiDoc\Contract\ApiOperationStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;

/**
 * Documents the response from menumbing/resource's #[WithResource]
 * attribute — but only when no response is set yet, so explicit Tier 2/3
 * documentation wins. A #[WithResource] without an explicit resource class
 * relies on runtime auto-detection and reports nothing; ReturnTypeStrategy
 * covers those cases.
 *
 * The attribute is matched by name only, so this strategy works (and
 * simply matches nothing) when the package is absent.
 */
class MenumbingResourceStrategy implements ApiOperationStrategy
{
    public const WITH_RESOURCE_ATTRIBUTE = 'Menumbing\Resource\Annotation\WithResource';

    /**
     * @param class-string $withResourceAttribute override for testing or forks
     */
    public function __construct(
        protected string $withResourceAttribute = self::WITH_RESOURCE_ATTRIBUTE,
    ) {}

    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if ($operation->responses !== []) {
            return;
        }

        $attribute = $context->attribute($this->withResourceAttribute);

        if ($attribute === null) {
            return;
        }

        $resource = $attribute->resource ?? null;

        if (! is_string($resource) || $resource === '') {
            return;
        }

        $status = isset($attribute->statusCode) && is_int($attribute->statusCode)
            ? $attribute->statusCode
            : 200;

        $operation->response($status, $resource, configure: function ($response) use ($context) {
            if ($context->returnsCollection) {
                $response->collection();
            }
        });
    }
}
