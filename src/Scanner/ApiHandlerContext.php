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

namespace HyperfApiDoc\Scanner;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Read-only endpoint metadata handed to every ApiOperationStrategy.
 */
final class ApiHandlerContext
{
    /** @var array<string, array<int, ReflectionAttribute>> attribute name => reflections, method first */
    private array $attributes = [];

    /**
     * @param array<int, string> $middleware from #[Controller(options: ['middleware'])]
     * @param array<int, class-string> $returnTypes resolved return-type candidates
     */
    public function __construct(
        public readonly string $path,
        public readonly string $httpMethod,
        public readonly ReflectionClass $controller,
        public readonly ReflectionMethod $method,
        public readonly array $middleware = [],
        public readonly array $returnTypes = [],
        public readonly bool $returnsCollection = false,
    ) {
        foreach ([$this->method->getAttributes(), $this->controller->getAttributes()] as $reflections) {
            foreach ($reflections as $attribute) {
                $this->attributes[$attribute->getName()][] = $attribute;
            }
        }
    }

    /**
     * First instance of the named attribute (method-level wins over the
     * class level); null when absent or its class is unavailable.
     */
    public function attribute(string $name): ?object
    {
        $reflection = $this->attributes[$name][0] ?? null;

        if ($reflection === null) {
            return null;
        }

        try {
            return $reflection->newInstance();
        } catch (Throwable) {
            return null;
        }
    }
}
