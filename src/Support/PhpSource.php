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

namespace HyperfApiDoc\Support;

/**
 * Tokenizer-based access to a PHP source file's structural facts: declared
 * classes, namespace, use statements, and name resolution against them.
 */
final class PhpSource
{
    /** @var array<int|string, array|string> */
    private array $tokens;

    private ?string $namespace = null;

    /** @var array<string, string> short name => FQCN */
    private array $uses = [];

    /** @var array<int, string> fully qualified class names in declaration order */
    private array $classes = [];

    public function __construct(string $source)
    {
        $this->tokens = token_get_all($source);
        $this->parse();
    }

    public static function fromFile(string $path): ?self
    {
        $source = is_readable($path) ? @file_get_contents($path) : false;

        return $source === false ? null : new self($source);
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @return array<string, string> short name => FQCN
     */
    public function uses(): array
    {
        return $this->uses;
    }

    /**
     * @return array<int, string>
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * Resolve a (possibly relative) class name against the file's namespace
     * and use statements.
     */
    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $root = explode('\\', $name)[0];

        if (isset($this->uses[$root])) {
            return $this->uses[$root] . substr($name, strlen($root));
        }

        return $this->namespace !== null
            ? $this->namespace . '\\' . $name
            : $name;
    }

    private function parse(): void
    {
        $count = count($this->tokens);
        $lastId = null;
        $depth = 0;

        for ($i = 0; $i < $count; ++$i) {
            $token = $this->tokens[$i];

            if (! is_array($token)) {
                if ($token === '{' || $token === '(') {
                    ++$depth;
                } elseif ($token === '}' || $token === ')') {
                    --$depth;
                }

                $lastId = $token;
                continue;
            }

            switch ($token[0]) {
                case T_NAMESPACE:
                    if ($this->namespace === null) {
                        $this->namespace = $this->collectName($i + 1, $count) ?: null;
                    }
                    break;
                case T_USE:
                    // Only header uses; depth tracking keeps closure uses out.
                    if ($depth === 0) {
                        $this->collectUse($i + 1, $count);
                    }
                    break;
                case T_CLASS:
                    if ($lastId !== T_DOUBLE_COLON) {
                        $name = $this->nextString($i + 1, $count);

                        if ($name !== null) {
                            $this->classes[] = ($this->namespace !== null ? $this->namespace . '\\' : '') . $name;
                        }
                    }
                    break;
            }

            $lastId = $token[0];
        }
    }

    private function collectName(int $start, int $count): string
    {
        $name = '';

        for ($i = $start; $i < $count; ++$i) {
            $token = $this->tokens[$i];

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            } elseif ($token === ';' || $token === '{' || (is_array($token) && ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                break;
            }
        }

        return trim($name);
    }

    private function nextString(int $start, int $count): ?string
    {
        for ($i = $start; $i < $count; ++$i) {
            $token = $this->tokens[$i];

            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                if ($token[0] === T_STRING) {
                    return $token[1];
                }

                return null;
            }

            if ($token === '(' || $token === '{' || $token === ';') {
                return null;
            }
        }

        return null;
    }

    private function collectUse(int $start, int $count): void
    {
        $name = '';
        $alias = null;
        $phase = 'name';

        for ($i = $start; $i < $count; ++$i) {
            $token = $this->tokens[$i];

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;
                    case T_FUNCTION:
                    case T_CONST:
                        return;
                    case T_AS:
                        $phase = 'alias';
                        break;
                    case T_STRING:
                    case T_NAME_QUALIFIED:
                    case T_NAME_FULLY_QUALIFIED:
                    case T_NS_SEPARATOR:
                        if ($phase === 'alias') {
                            $alias = $token[1];
                        } else {
                            $name .= $token[1];
                        }
                        break;
                    default:
                        return;
                }

                continue;
            }

            if ($token === ',' || $token === ';') {
                if ($name !== '') {
                    $fqcn = ltrim($name, '\\');
                    $short = $alias ?? (str_contains($fqcn, '\\')
                        ? substr($fqcn, (int) strrpos($fqcn, '\\') + 1)
                        : $fqcn);
                    $this->uses[$short] = $fqcn;
                }

                if ($token === ';') {
                    return;
                }

                $name = '';
                $alias = null;
                $phase = 'name';
            }
        }
    }
}
