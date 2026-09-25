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
use HyperfApiDoc\Exception\InvalidConfigurationException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Encodes the rendered spec to YAML via symfony/yaml.
 *
 * symfony/yaml cannot distinguish empty maps from empty lists on dump (both
 * render as "{  }"), so empty lists travel through a sentinel string that
 * is restored to [] after dumping; empty objects intentionally render as {}.
 */
class YamlWriter implements Writer
{
    private const EMPTY_LIST = '[]';

    public function format(): string
    {
        return 'yaml';
    }

    public function extension(): string
    {
        return 'yaml';
    }

    public function encode(array $data, bool $pretty = true): string
    {
        if (! class_exists(Yaml::class)) {
            throw new InvalidConfigurationException(
                'YAML output requires symfony/yaml; install it or use the json format.'
            );
        }

        $inline = $pretty ? 8 : 2;

        $yaml = Yaml::dump(self::objectify($data), $inline, 2);

        return str_replace("'" . self::EMPTY_LIST . "'", '[]', $yaml);
    }

    /**
     * Convert stdClass maps to arrays; empty lists become a sentinel so
     * they survive dumping as [] instead of {}.
     */
    private static function objectify(mixed $data): mixed
    {
        if ($data instanceof stdClass) {
            // An empty stdClass becomes an empty array, which symfony/yaml
            // renders as the {} flow mapping — the correct object shape.
            return array_map(static fn ($value) => self::objectify($value), get_object_vars($data));
        }

        if (is_array($data)) {
            if ($data === []) {
                return self::EMPTY_LIST;
            }

            return array_map(static fn ($value) => self::objectify($value), $data);
        }

        return $data;
    }
}
