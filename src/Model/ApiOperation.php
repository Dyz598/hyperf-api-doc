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
use HyperfApiDoc\Model\Concerns\HasExtensions;

use function Hyperf\Support\class_basename;

/**
 * The fluent operation builder exposed to ApiOperationDocumented
 * implementations, and the internal representation of one routed endpoint.
 *
 * Route facts are filled by the RouteScanner; Tier 2/3 definitions run
 * before the strategy chain, so explicit documentation always wins and
 * strategies only fill what is still missing.
 */
class ApiOperation
{
    use HasExtensions;

    public ?string $summary = null;

    public ?string $description = null;

    /** @var array<int, string> */
    public array $tags = [];

    public ?string $operationId = null;

    /** @var array<int, string> */
    public array $groups = [];

    /** true = default pagination meta; class-string = custom meta schema. */
    public bool|string|null $paginated = null;

    public bool $deprecated = false;

    /** @var array<string, ApiParameter> */
    public array $parameters = [];

    public ?ApiRequestBody $requestBody = null;

    /** @var array<int, ApiResponse> */
    public array $responses = [];

    /**
     * null = not documented (resolve from guard/config);
     * [] = explicitly public; non-empty = requirements.
     *
     * @var null|array<array<string, array<string>>|array<string, array>>
     */
    public ?array $security = null;

    // ------------------------------------------------------------------
    // Scanner metadata
    // ------------------------------------------------------------------

    public string $controller = '';

    public string $controllerMethod = '';

    public string $httpMethod = '';

    public string $path = '';

    /** @var null|class-string */
    public ?string $formRequest = null;

    public ?string $authGuard = null;

    /** Skip the configured validation/unauthorized default responses. */
    public bool $withoutDefaultResponses = false;

    public function summary(?string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function tags(string ...$tags): static
    {
        foreach ($tags as $tag) {
            if (! in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }

        return $this;
    }

    public function operationId(string $operationId): static
    {
        $this->operationId = $operationId;
        return $this;
    }

    /**
     * Assign the operation to one or more documentation groups.
     */
    public function group(string ...$groups): static
    {
        $this->groups = array_values($groups);

        return $this;
    }

    public function deprecated(bool $deprecated = true): static
    {
        $this->deprecated = $deprecated;
        return $this;
    }

    /**
     * Register a response for an HTTP status. The schema describes the
     * body; the operation owns the HTTP semantics.
     *
     * @param null|ApiSchema|class-string $schema body schema class or built schema
     * @param null|callable $configure escape hatch receiving the ApiResponse
     */
    public function response(int $status, ApiSchema|string|null $schema = null, ?string $description = null, ?callable $configure = null): static
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid response status [%d] for operation [%s]; expected 100-599.',
                $status,
                $this->describe()
            ));
        }

        if ($this->findResponse($status) !== null) {
            throw new InvalidConfigurationException(sprintf(
                'Duplicate response status [%d] for operation [%s].',
                $status,
                $this->describe()
            ));
        }

        $response = new ApiResponse($status);

        if ($schema !== null) {
            $response->schema($schema);
        }

        if ($description !== null) {
            $response->description($description);
        }

        if ($configure !== null) {
            $configure($response);
        }

        $this->responses[] = $response;

        return $this;
    }

    /**
     * @param class-string $request FormRequest or explicit schema class
     */
    public function request(string $request, ?string $contentType = null, ?callable $configure = null): static
    {
        $body = new ApiRequestBody();
        $body->schema($request);

        if ($contentType !== null) {
            $body->contentType($contentType);
        }

        if ($configure !== null) {
            $configure($body);
        }

        $this->requestBody = $body;

        return $this;
    }

    /**
     * OR-combined security requirements.
     */
    public function security(string ...$schemes): static
    {
        $this->security = array_map(
            static fn (string $scheme) => [$scheme => []],
            $schemes
        );

        return $this;
    }

    /**
     * Mark the operation as explicitly public, overriding global defaults.
     */
    public function public(): static
    {
        $this->security = [];
        return $this;
    }

    /**
     * Suppress the globally configured default responses (validation 422,
     * unauthorized 401) for this operation.
     */
    public function withoutDefaultResponses(bool $without = true): static
    {
        $this->withoutDefaultResponses = $without;
        return $this;
    }

    /**
     * @param array<string, array{description?: string, type?: string, format?: string, example?: mixed, enum?: array, required?: bool}> $parameters
     */
    public function pathParameters(array $parameters): static
    {
        return $this->addParameters('path', $parameters, true);
    }

    public function queryParameters(array $parameters): static
    {
        return $this->addParameters('query', $parameters, false);
    }

    public function headerParameters(array $parameters): static
    {
        return $this->addParameters('header', $parameters, false);
    }

    public function cookieParameters(array $parameters): static
    {
        return $this->addParameters('cookie', $parameters, false);
    }

    public function parameter(ApiParameter $parameter): static
    {
        $this->parameters[$parameter->name] = $parameter;
        return $this;
    }

    public function findResponse(int $status): ?ApiResponse
    {
        foreach ($this->responses as $response) {
            if ($response->status === $status) {
                return $response;
            }
        }

        return null;
    }

    public function describe(): string
    {
        if ($this->controller !== '') {
            return sprintf('%s::%s', class_basename($this->controller), $this->controllerMethod);
        }

        return sprintf('%s %s', $this->httpMethod, $this->path);
    }

    private function addParameters(string $in, array $parameters, bool $required): static
    {
        foreach ($parameters as $name => $options) {
            $isRequired = $required || (bool) ($options['required'] ?? false);
            $parameter = new ApiParameter((string) $name, $in, $isRequired);

            foreach ($options as $key => $value) {
                match ($key) {
                    'description' => $parameter->description((string) $value),
                    'type' => $parameter->type((string) $value),
                    'format' => $parameter->format((string) $value),
                    'example' => $parameter->example($value),
                    'enum' => $parameter->enum((array) $value),
                    'required' => null,
                    'deprecated' => $parameter->deprecated((bool) $value),
                    default => throw new InvalidConfigurationException(sprintf(
                        'Unknown parameter option [%s] for parameter [%s].',
                        $key,
                        $name
                    )),
                };
            }

            $this->parameter($parameter);
        }

        return $this;
    }
}
