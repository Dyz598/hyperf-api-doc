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

use HyperfApiDoc\Support\HyperfClasses;
use HyperfApiDoc\Support\PhpSource;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Resolves candidate response body classes for an action method.
 *
 * Resolution order: declared return types first; when the return type is
 * missing or mixed (common in single-action codebases), fall back to a
 * heuristic scan of the method body for "return new X(...)" statements.
 *
 * Sources are read once per file and extracted bodies cached per method,
 * since resolve() and returnsCollection() scan the same body.
 */
class ReturnTypeResolver
{
    /** @var array<string, false|string> file path => source, false when unreadable */
    private array $sources = [];

    /** @var array<string, ?string> "file::method" => extracted body */
    private array $bodies = [];

    /**
     * @return array<int, class-string>
     */
    public function resolve(ReflectionMethod $method): array
    {
        $classes = $this->declaredReturnTypes($method);

        if ($classes === []) {
            $classes = $this->returnedNewClasses($method);
        }

        return array_values(array_unique($classes));
    }

    /**
     * Whether the method body returns a resource collection
     * (XResource::collection(...)), marking the response as an array.
     */
    public function returnsCollection(ReflectionMethod $method): bool
    {
        $body = $this->methodBody($method);

        return $body !== null
            && (bool) preg_match('/return\s+[A-Za-z_\\\][A-Za-z0-9_\\\]*::collection\s*\(/', $body);
    }

    /**
     * @return array<int, class-string>
     */
    private function declaredReturnTypes(ReflectionMethod $method): array
    {
        $returnType = $method->getReturnType();
        $classes = [];

        if ($returnType instanceof ReflectionNamedType) {
            $classes = [$returnType->getName()];
        } elseif ($returnType instanceof ReflectionUnionType) {
            $classes = array_map(
                static fn (ReflectionNamedType $type) => $type->getName(),
                $returnType->getTypes()
            );
        }

        $declaringClass = $method->getDeclaringClass()->getName();
        $classes = array_map(
            static fn (string $name) => in_array($name, ['self', 'static'], true) ? $declaringClass : $name,
            $classes
        );

        return array_values(array_filter(
            $classes,
            static fn (string $name) => ! in_array($name, ['mixed', 'void', 'null', 'static', 'never'], true)
                && (class_exists($name) || enum_exists($name))
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function returnedNewClasses(ReflectionMethod $method): array
    {
        $body = $this->methodBody($method);

        if ($body === null) {
            return [];
        }

        $patterns = [
            '/return\s+new\s+([A-Za-z_\\\][A-Za-z0-9_\\\]*)\s*\(/',
            '/return\s+([A-Za-z_\\\][A-Za-z0-9_\\\]*)::collection\s*\(/',
        ];

        $names = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                $names = [...$names, ...$matches[1]];
            }
        }

        if ($names === []) {
            return [];
        }

        $sourceFile = new PhpSource((string) $this->fileSource($method));

        $classes = [];
        foreach ($names as $name) {
            $fqcn = $sourceFile->resolve($name);

            if ($this->looksLikeResponse($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    private function fileSource(ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();

        if ($file === false) {
            return null;
        }

        if (! array_key_exists($file, $this->sources)) {
            $source = is_readable($file) ? file_get_contents($file) : false;
            $this->sources[$file] = $source === false ? false : $source;
        }

        return $this->sources[$file] === false ? null : $this->sources[$file];
    }

    /**
     * The method's body, read once per method; null when the file is
     * unreadable or the body cannot be located.
     */
    private function methodBody(ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();

        if ($file === false) {
            return null;
        }

        $key = $file . '::' . $method->getName();

        if (! array_key_exists($key, $this->bodies)) {
            $source = $this->fileSource($method);

            $this->bodies[$key] = $source === null
                ? null
                : $this->extractMethodBody($source, $method->getName());
        }

        return $this->bodies[$key];
    }

    /**
     * Extract a method body via brace matching.
     */
    private function extractMethodBody(string $source, string $methodName): ?string
    {
        $start = strpos($source, 'function ' . $methodName . '(');

        if ($start === false) {
            return null;
        }

        $open = strpos($source, '{', $start);

        if ($open === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($source);
        for ($i = $open; $i < $length; ++$i) {
            if ($source[$i] === '{') {
                ++$depth;
            } elseif ($source[$i] === '}') {
                --$depth;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function looksLikeResponse(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        if (class_exists(HyperfClasses::JSON_RESOURCE) && is_subclass_of($class, HyperfClasses::JSON_RESOURCE)) {
            return true;
        }

        return str_ends_with($class, 'Resource');
    }
}
