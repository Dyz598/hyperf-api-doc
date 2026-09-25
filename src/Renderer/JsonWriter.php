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

namespace HyperfApiDoc\Renderer;

use HyperfApiDoc\Contract\Writer;
use JsonException;

/**
 * Encodes the rendered spec to JSON, preserving empty objects (stdClass)
 * so that OpenAPI object shapes survive encoding.
 */
class JsonWriter implements Writer
{
    public function format(): string
    {
        return 'json';
    }

    public function extension(): string
    {
        return 'json';
    }

    /**
     * @throws JsonException
     */
    public function encode(array $data, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }
}
