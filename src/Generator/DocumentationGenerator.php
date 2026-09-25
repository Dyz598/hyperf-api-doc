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

namespace HyperfApiDoc\Generator;

use HyperfApiDoc\Contract\ApiSecurityDocumented;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiRequestBody;
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Model\ApiSecurityScheme;
use HyperfApiDoc\Scanner\RouteScanner;
use HyperfApiDoc\Security\SecurityRegistry;
use ReflectionClass;
use Throwable;

/**
 * Orchestrates one generation run: scan -> resolve -> validate -> ApiDocument.
 *
 * Responses are documented by the strategy chain during scanning; this
 * class materializes overlays, adds the configured auto responses, maps
 * guards to security schemes, and registers schemas.
 */
class DocumentationGenerator
{
    protected SchemaRegistry $registry;

    /** @var array<string, string> guard name => security scheme name */
    protected array $guardMap = [];

    protected ?string $defaultScheme = null;

    public function __construct(
        protected RouteScanner $scanner,
        protected SchemaResolver $resolver,
        protected array $config = [],
    ) {
        $this->registry = new SchemaRegistry();
    }

    public function generate(): ApiDocument
    {
        $document = new ApiDocument();
        $document->info = $this->info();
        $document->servers = (array) ($this->config['servers'] ?? []);

        $security = $this->resolveSecuritySchemes();
        $document->securitySchemes = $security->schemes();
        $defaultSecurity = $this->defaultSecurity();
        $document->security = $defaultSecurity;

        $operationIds = [];
        $routes = [];

        foreach ($this->scanner->scan() as $operation) {
            $routeKey = strtolower($operation->httpMethod) . ' ' . $operation->path;

            if (isset($routes[$routeKey])) {
                throw new InvalidConfigurationException(sprintf(
                    'Duplicate route [%s] detected for operations [%s] and [%s].',
                    $routeKey,
                    $routes[$routeKey],
                    $operation->describe()
                ));
            }

            $routes[$routeKey] = $operation->describe();

            $this->resolveRequest($operation);
            $this->resolveResponses($operation);
            $this->resolveOperationSecurity($operation, $security, $defaultSecurity);

            if ($operation->operationId !== null) {
                if (isset($operationIds[$operation->operationId])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Duplicate operationId [%s] used by [%s] and [%s].',
                        $operation->operationId,
                        $operationIds[$operation->operationId],
                        $operation->describe()
                    ));
                }

                $operationIds[$operation->operationId] = $operation->describe();
            }

            $document->operation($operation);
        }

        return $document;
    }

    public function registry(): SchemaRegistry
    {
        return $this->registry;
    }

    private function resolveRequest(ApiOperation $operation): void
    {
        if ($operation->requestBody === null && $operation->formRequest !== null) {
            $body = new ApiRequestBody();
            $body->schema($operation->formRequest);
            $operation->requestBody = $body;
        }

        if ($operation->requestBody?->schemaClass !== null) {
            $this->registerSchema($operation->requestBody->schemaClass);
        }
    }

    private function resolveResponses(ApiOperation $operation): void
    {
        // Ensure at least one documented response.
        if ($operation->responses === []) {
            $operation->responses[] = new ApiResponse(200);
        }

        // Union members cannot express per-status semantics; they merge
        // their properties into the primary response body.
        foreach ($operation->responses as $response) {
            foreach ($response->mergeClasses as $class) {
                $response->overlay()->merge($this->resolver->resolve($class));
            }
        }

        $this->addAutoResponse($operation, 'validation', $operation->formRequest !== null);
        $this->addAutoResponse($operation, 'unauthorized', $operation->authGuard !== null);

        foreach ($operation->responses as $response) {
            if (is_string($response->schema)) {
                $this->registerSchema($response->schema);
            } elseif ($response->schema instanceof ApiSchema) {
                foreach ($response->schema->referencedClasses() as $class) {
                    $this->registerSchema($class);
                }
            }
        }
    }

    /**
     * Config values: class-string = default status; array may override it;
     * absent or null disables the response.
     */
    private function addAutoResponse(ApiOperation $operation, string $key, bool $condition): void
    {
        $definition = ($this->config['responses'] ?? [])[$key] ?? null;

        $schema = is_string($definition) ? $definition : ($definition['schema'] ?? null);
        $status = is_array($definition) && isset($definition['status'])
            ? (int) $definition['status']
            : ($key === 'validation' ? 422 : 401);

        if ($operation->withoutDefaultResponses
            || ! $condition
            || ! is_string($schema)
            || $schema === ''
            || $operation->findResponse($status) !== null) {
            return;
        }

        $response = new ApiResponse($status);
        $response->schema($schema);
        $operation->responses[] = $response;

        $this->registerSchema($schema);
    }

    private function resolveOperationSecurity(ApiOperation $operation, SecurityRegistry $security, ?array $default): void
    {
        if ($operation->security !== null) {
            $this->assertSchemesDefined($operation, $security, $this->referencedSchemes($operation->security));

            return;
        }

        $guards = $this->guardMap;

        if ($operation->authGuard !== null && isset($guards[$operation->authGuard])) {
            $scheme = (string) $guards[$operation->authGuard];
            $this->assertSchemesDefined($operation, $security, [$scheme]);
            $operation->security = [[$scheme => []]];

            return;
        }

        if ($default !== null) {
            $this->assertSchemesDefined($operation, $security, $this->referencedSchemes($default));
            $operation->security = $default;
        }
    }

    private function resolveSecuritySchemes(): SecurityRegistry
    {
        $registry = new SecurityRegistry();

        foreach ((array) ($this->config['security'] ?? []) as $key => $entry) {
            if (is_string($entry)) {
                if (! is_subclass_of($entry, ApiSecurityDocumented::class)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Security definition [%s] must implement [%s].',
                        $entry,
                        ApiSecurityDocumented::class
                    ));
                }

                $before = array_keys($registry->schemes());

                try {
                    (new ReflectionClass($entry))->newInstanceWithoutConstructor()
                        ->documentApiSecurity($registry);
                } catch (Throwable $exception) {
                    throw new InvalidConfigurationException(sprintf(
                        'Failed to register security definition [%s]: %s',
                        $entry,
                        $exception->getMessage()
                    ), 0, $exception);
                }

                // A keyed entry ('partner' => PartnerSecurity::class) names
                // the definition's single scheme; unkeyed entries let the
                // class name its own (possibly several) schemes.
                if (is_string($key)) {
                    $added = array_values(array_diff(array_keys($registry->schemes()), $before));

                    if (count($added) !== 1) {
                        throw new InvalidConfigurationException(sprintf(
                            'Security definition [%s] registered %d schemes; keyed entries must register exactly one. Register it unkeyed so the class names its own schemes.',
                            $entry,
                            count($added)
                        ));
                    }

                    $registry->rename($added[0], $key);
                }

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $name = (string) $key;
            $definition = $entry;
            unset($definition['guards'], $definition['default']);

            $registry->add(ApiSecurityScheme::fromArray($name, $definition));

            foreach ((array) ($entry['guards'] ?? []) as $guard) {
                $this->guardMap[(string) $guard] = $name;
            }

            if (! empty($entry['default'])) {
                if ($this->defaultScheme !== null) {
                    throw new InvalidConfigurationException(sprintf(
                        'Only one security scheme may be marked as default; [%s] and [%s] both are.',
                        $this->defaultScheme,
                        $name
                    ));
                }

                $this->defaultScheme = $name;
            }
        }

        return $registry;
    }

    /**
     * @return null|array<array<string, array<string>>|array<string, array>>
     */
    private function defaultSecurity(): ?array
    {
        return $this->defaultScheme !== null
            ? [[$this->defaultScheme => []]]
            : null;
    }

    /**
     * @param array<array<string, array<string>>|array<string, array>> $requirements
     * @return array<int, string>
     */
    private function referencedSchemes(array $requirements): array
    {
        $schemes = [];

        foreach ($requirements as $requirement) {
            foreach (array_keys($requirement) as $scheme) {
                $schemes[(string) $scheme] = (string) $scheme;
            }
        }

        return array_values($schemes);
    }

    private function assertSchemesDefined(ApiOperation $operation, SecurityRegistry $security, array $schemes): void
    {
        foreach ($schemes as $scheme) {
            if (! $security->has($scheme)) {
                throw new InvalidConfigurationException(sprintf(
                    'Operation [%s] references undefined security scheme [%s]; define it under security or via a security definition class.',
                    $operation->describe(),
                    $scheme
                ));
            }
        }
    }

    private function registerSchema(string $class): void
    {
        $this->registry->register($class, $this->resolver->resolve($class));

        // Nested object classes referenced by this schema participate in
        // reuse counting, so shared children become components.
        foreach ($this->resolver->dependencies($class) as $dependency) {
            $this->registry->register($dependency, $this->resolver->resolve($dependency));
        }
    }

    /**
     * @return array{title: string, description: null|string, version: string}
     */
    private function info(): array
    {
        $info = (array) ($this->config['info'] ?? []);

        return [
            'title' => (string) ($info['title'] ?? 'API'),
            'description' => isset($info['description']) && $info['description'] !== null
                ? (string) $info['description']
                : null,
            'version' => (string) ($info['version'] ?? '1.0.0'),
        ];
    }
}
