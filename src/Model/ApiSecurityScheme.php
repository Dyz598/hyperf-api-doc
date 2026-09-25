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

namespace HyperfApiDoc\Model;

use HyperfApiDoc\Exception\InvalidConfigurationException;

/**
 * A security scheme definition (components.securitySchemes entry).
 */
class ApiSecurityScheme
{
    public ?string $description = null;

    /** http scheme (e.g. bearer) */
    public ?string $scheme = null;

    public ?string $bearerFormat = null;

    /** apiKey location: query|header|cookie */
    public ?string $in = null;

    /** apiKey parameter name (OpenAPI "name") */
    public ?string $parameterName = null;

    /** @var null|array<string, mixed> oauth2 flows */
    public ?array $flows = null;

    public ?string $openIdConnectUrl = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type = 'http',
    ) {
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new InvalidConfigurationException(sprintf(
                'Security scheme name [%s] is invalid; use letters, digits, dots, underscores, and hyphens.',
                $name
            ));
        }
    }

    /**
     * @param array<string, mixed> $definition config-style definition array
     */
    public static function fromArray(string $name, array $definition): self
    {
        $type = (string) ($definition['type'] ?? 'http');

        if (! in_array($type, ['http', 'apiKey', 'oauth2', 'openIdConnect'], true)) {
            throw new InvalidConfigurationException(sprintf(
                'Security scheme [%s] has invalid type [%s].',
                $name,
                $type
            ));
        }

        $scheme = new self($name, $type);
        $scheme->description = $definition['description'] ?? null;
        $scheme->scheme = $definition['scheme'] ?? null;
        $scheme->bearerFormat = $definition['bearerFormat'] ?? null;
        $scheme->in = $definition['in'] ?? null;
        $scheme->parameterName = $definition['parameterName'] ?? ($definition['name'] ?? null);
        $scheme->flows = $definition['flows'] ?? null;
        $scheme->openIdConnectUrl = $definition['openIdConnectUrl'] ?? null;

        return $scheme;
    }

    public function toArray(): array
    {
        $array = ['type' => $this->type];

        if ($this->description !== null) {
            $array['description'] = $this->description;
        }

        switch ($this->type) {
            case 'http':
                $array['scheme'] = $this->scheme ?? 'bearer';
                if ($this->bearerFormat !== null) {
                    $array['bearerFormat'] = $this->bearerFormat;
                }
                break;
            case 'apiKey':
                $array['name'] = $this->parameterName ?? $this->name;
                $array['in'] = $this->in ?? 'header';
                break;
            case 'oauth2':
                $array['flows'] = $this->flows ?? [];
                break;
            case 'openIdConnect':
                $array['openIdConnectUrl'] = $this->openIdConnectUrl ?? '';
                break;
        }

        return $array;
    }

    /**
     * Same scheme under a new name (keyed security definition entries).
     */
    public function withName(string $name): self
    {
        $clone = new self($name, $this->type);
        $clone->description = $this->description;
        $clone->scheme = $this->scheme;
        $clone->bearerFormat = $this->bearerFormat;
        $clone->in = $this->in;
        $clone->parameterName = $this->parameterName;
        $clone->flows = $this->flows;
        $clone->openIdConnectUrl = $this->openIdConnectUrl;

        return $clone;
    }
}
