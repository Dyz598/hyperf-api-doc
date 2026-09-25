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
 * Naming conventions for inferred operations.
 */
class Inflector
{
    private const REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        418 => "I'm a teapot",
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    /**
     * CreatePostAction -> "Create post".
     */
    public static function actionSummary(string $classShortName): string
    {
        $base = preg_replace('/Action$/', '', $classShortName) ?? $classShortName;
        $words = static::splitCamel($base);

        if ($words === []) {
            return $base;
        }

        $words[0] = ucfirst(mb_strtolower($words[0]));
        for ($i = 1, $count = count($words); $i < $count; ++$i) {
            $words[$i] = mb_strtolower($words[$i]);
        }

        return implode(' ', $words);
    }

    /**
     * Acem\Action\Post\CreatePostAction -> "Posts" (segment after \Action\).
     */
    public static function tagFromNamespace(string $namespace): ?string
    {
        if (! preg_match('#\\\Action\\\([^\\\]+)#', $namespace, $matches)) {
            return null;
        }

        return static::pluralize(ucfirst($matches[1]));
    }

    public static function pluralize(string $word): string
    {
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|ch|sh)$/i', $word)) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * @return array<int, string>
     */
    public static function splitCamel(string $value): array
    {
        $split = preg_split('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $split), static fn (string $word) => $word !== ''));
    }

    public static function reasonPhrase(int $status): string
    {
        return self::REASON_PHRASES[$status] ?? sprintf('HTTP %d', $status);
    }
}
