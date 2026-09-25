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

namespace HyperfApiDoc\Security;

use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiSecurityScheme;

/**
 * Collects security schemes from config arrays and ApiSecurityDocumented
 * definition classes.
 */
class SecurityRegistry
{
    /** @var array<string, ApiSecurityScheme> */
    protected array $schemes = [];

    public function add(ApiSecurityScheme $scheme): ApiSecurityScheme
    {
        if (isset($this->schemes[$scheme->name])) {
            throw new InvalidConfigurationException(sprintf(
                'Duplicate security scheme name [%s].',
                $scheme->name
            ));
        }

        return $this->schemes[$scheme->name] = $scheme;
    }

    public function bearer(string $name, string $bearerFormat = 'JWT', ?string $description = null): ApiSecurityScheme
    {
        $scheme = new ApiSecurityScheme($name, 'http');
        $scheme->scheme = 'bearer';
        $scheme->bearerFormat = $bearerFormat;
        $scheme->description = $description;

        return $this->add($scheme);
    }

    public function basic(string $name, ?string $description = null): ApiSecurityScheme
    {
        $scheme = new ApiSecurityScheme($name, 'http');
        $scheme->scheme = 'basic';
        $scheme->description = $description;

        return $this->add($scheme);
    }

    public function apiKey(string $name, string $parameterName, string $in = 'header', ?string $description = null): ApiSecurityScheme
    {
        $scheme = new ApiSecurityScheme($name, 'apiKey');
        $scheme->parameterName = $parameterName;
        $scheme->in = $in;
        $scheme->description = $description;

        return $this->add($scheme);
    }

    /**
     * @param array<string, mixed> $flows
     */
    public function oauth2(string $name, array $flows, ?string $description = null): ApiSecurityScheme
    {
        $scheme = new ApiSecurityScheme($name, 'oauth2');
        $scheme->flows = $flows;
        $scheme->description = $description;

        return $this->add($scheme);
    }

    public function openIdConnect(string $name, string $url, ?string $description = null): ApiSecurityScheme
    {
        $scheme = new ApiSecurityScheme($name, 'openIdConnect');
        $scheme->openIdConnectUrl = $url;
        $scheme->description = $description;

        return $this->add($scheme);
    }

    public function has(string $name): bool
    {
        return isset($this->schemes[$name]);
    }

    /**
     * Rename a registered scheme (keyed security definition entries).
     */
    public function rename(string $from, string $to): ApiSecurityScheme
    {
        if (! isset($this->schemes[$from])) {
            throw new InvalidConfigurationException(sprintf(
                'Cannot rename unknown security scheme [%s].',
                $from
            ));
        }

        if ($from !== $to && isset($this->schemes[$to])) {
            throw new InvalidConfigurationException(sprintf(
                'Duplicate security scheme name [%s].',
                $to
            ));
        }

        $scheme = $this->schemes[$from];
        unset($this->schemes[$from]);

        return $this->add($scheme->withName($to));
    }

    /**
     * @return array<string, ApiSecurityScheme>
     */
    public function schemes(): array
    {
        return $this->schemes;
    }
}
