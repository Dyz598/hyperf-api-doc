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
 * Reads the auth guard from menumbing/hyperf-auth's #[Auth] attribute
 * (method-level first, class-level fallback).
 *
 * The attribute is matched by name only, so this strategy works (and
 * simply matches nothing) when the package is absent.
 */
class MenumbingAuthStrategy implements ApiOperationStrategy
{
    public const AUTH_ATTRIBUTE = 'HyperfExtension\Auth\Annotations\Auth';

    /**
     * @param class-string $authAttribute override for testing or forks
     */
    public function __construct(
        protected string $authAttribute = self::AUTH_ATTRIBUTE,
    ) {}

    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        if ($operation->authGuard !== null) {
            return;
        }

        $attribute = $context->attribute($this->authAttribute);

        if ($attribute === null) {
            return;
        }

        $guards = $attribute->guards ?? null;

        if (is_string($guards) && $guards !== '') {
            $operation->authGuard = $guards;

            return;
        }

        if (is_array($guards) && $guards !== []) {
            $operation->authGuard = (string) $guards[0];
        }
    }
}
