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

use FilesystemIterator;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Mapping;
use HyperfApiDoc\Attribute\ApiDoc;
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Contract\ApiOperationStrategy;
use HyperfApiDoc\Contract\OperationNamer;
use HyperfApiDoc\Contract\ResponseDecoratorStrategy;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiParameter;
use HyperfApiDoc\Naming\ActionOperationNamer;
use HyperfApiDoc\Strategy\FormRequestStrategy;
use HyperfApiDoc\Strategy\JsonResourceStrategy;
use HyperfApiDoc\Strategy\ReturnTypeStrategy;
use HyperfApiDoc\Support\ConfigInstances;
use HyperfApiDoc\Support\PhpSource;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Scans configured paths for #[Controller] / #[AutoController] classes via
 * plain reflection — no server boot or annotation cache required — and
 * builds ApiOperation instances: route facts first, then Tier 2/3
 * definitions (explicit documentation wins), then detection strategies
 * (configured, then core inference) and decoration strategies.
 */
class RouteScanner
{
    /** Core detection strategies; always run after the configured ones. */
    private const CORE_DETECTION_STRATEGIES = [
        ReturnTypeStrategy::class,
        FormRequestStrategy::class,
    ];

    /** Core decoration strategies; always run before the configured ones. */
    private const CORE_DECORATION_STRATEGIES = [
        JsonResourceStrategy::class,
    ];

    private const DEFAULT_ROUTE_METHODS = ['GET', 'POST'];

    protected ReturnTypeResolver $returnTypeResolver;

    protected OperationNamer $namer;

    /** @var array<int, ApiOperationStrategy> */
    protected array $strategies;

    /** @var null|array<int, string> validated exclude patterns, null until first use */
    protected ?array $excludePatterns = null;

    public function __construct(protected array $config = [])
    {
        $this->returnTypeResolver = new ReturnTypeResolver();
        $this->namer = $this->buildNamer();
        $this->strategies = $this->buildStrategies();
    }

    /**
     * @return array<int, ApiOperation>
     */
    public function scan(): array
    {
        $operations = [];

        foreach ((array) ($this->config['scan']['paths'] ?? []) as $path) {
            foreach ($this->classesIn((string) $path) as $class) {
                $operations = [...$operations, ...$this->scanClass($class)];
            }
        }

        return $operations;
    }

    /**
     * @return array<int, string>
     */
    private function classesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $classes = [...$classes, ...$this->classesInFile($file->getPathname())];
        }

        return $classes;
    }

    /**
     * @return array<int, string>
     */
    private function classesInFile(string $file): array
    {
        return PhpSource::fromFile($file)?->classes() ?? [];
    }

    /**
     * @return array<int, ApiOperation>
     */
    private function scanClass(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()
            || $reflection->isAnonymous()
            || $reflection->isInterface()
            || $reflection->isEnum()
            || $reflection->isTrait()) {
            return [];
        }

        $controller = $this->controllerAttribute($reflection);

        if ($controller === null) {
            return [];
        }

        $operations = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()
                || $method->getDeclaringClass()->getName() !== $class
                || str_starts_with($method->getName(), '__')
                || $method->getName() === 'documentApi') {
                continue;
            }

            [$path, $httpMethods] = $this->route($controller, $method);

            if ($path === null) {
                continue;
            }

            if (! $this->discovered($reflection, $method, $path)) {
                continue;
            }

            $operations[] = $this->buildOperation($reflection, $method, $path, $httpMethods, $controller);
        }

        return $operations;
    }

    /**
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function route(object $controller, ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(Mapping::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes !== []) {
            $mapping = $attributes[0]->newInstance();
            $httpMethods = array_values(array_diff(
                (array) $mapping->methods,
                ['OPTIONS', 'HEADER']
            ));
            $httpMethods = $httpMethods === [] ? self::DEFAULT_ROUTE_METHODS : $httpMethods;

            return [$this->joinPath((string) ($controller->prefix ?? ''), (string) ($mapping->path ?? '')), $httpMethods];
        }

        if ($controller instanceof AutoController) {
            $methods = array_values((array) ($controller->defaultMethods ?? self::DEFAULT_ROUTE_METHODS));

            if ($methods === []) {
                return [null, []];
            }

            return [$this->joinPath((string) $controller->prefix, $method->getName()), $methods];
        }

        return [null, []];
    }

    /**
     * Whether an operation participates in the document at all, per the
     * discovery config: exclude patterns run first (both modes, matched
     * against the resolved route path), then explicit mode requires a
     * #[ApiDoc] marker on the method or class.
     */
    private function discovered(ReflectionClass $class, ReflectionMethod $method, string $path): bool
    {
        foreach ($this->excludePatterns() as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        if ($this->explicitMode()) {
            return $method->getAttributes(ApiDoc::class) !== []
                || $class->getAttributes(ApiDoc::class) !== [];
        }

        return true;
    }

    /**
     * @param array<int, string> $httpMethods
     */
    private function buildOperation(
        ReflectionClass $reflection,
        ReflectionMethod $method,
        string $path,
        array $httpMethods,
        object $controller
    ): ApiOperation {
        $operation = new ApiOperation();
        $operation->controller = $reflection->getName();
        $operation->controllerMethod = $method->getName();
        $operation->httpMethod = $httpMethods[0];
        $operation->path = $path;

        $this->applyPathParameters($path, $method, $operation);

        // Explicit documentation first, so strategies only fill gaps.
        $this->applyDefinitions($reflection, $method, $operation);

        $this->applyStrategies($reflection, $method, $operation, $path, $httpMethods, $controller);

        // The namer labels whatever is still undocumented.
        $this->applyConventions($reflection, $method, $operation);

        return $operation;
    }

    /**
     * The namer labels undocumented operations; explicit Tier 2/3
     * documentation overrides whatever it sets.
     */
    private function applyConventions(ReflectionClass $reflection, ReflectionMethod $method, ApiOperation $operation): void
    {
        $operation->summary ??= $this->namer->summary($reflection, $method);
        $operation->operationId ??= $this->namer->operationId($reflection, $method);

        if ($operation->tags === []) {
            $operation->tags(...$this->namer->tags($reflection, $method));
        }
    }

    private function applyPathParameters(string $path, ReflectionMethod $method, ApiOperation $operation): void
    {
        if (! preg_match_all('/\{([^}:]+)(?::[^}]*)?\}/', $path, $matches)) {
            return;
        }

        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        foreach ($matches[1] as $name) {
            $parameter = $parameters[$name] ?? null;
            $operation->parameter(ApiParameter::path($name)->type($this->parameterType($parameter)));
        }
    }

    /**
     * Run the strategy chain — configured detections, core inference,
     * then decorations; each strategy sees the work of the previous ones.
     *
     * @param array<int, string> $httpMethods
     */
    private function applyStrategies(
        ReflectionClass $reflection,
        ReflectionMethod $method,
        ApiOperation $operation,
        string $path,
        array $httpMethods,
        object $controller
    ): void {
        $context = new ApiHandlerContext(
            path: $path,
            httpMethod: $httpMethods[0],
            controller: $reflection,
            method: $method,
            middleware: (array) ($controller->options['middleware'] ?? []),
            returnTypes: $this->returnTypeResolver->resolve($method),
            returnsCollection: $this->returnTypeResolver->returnsCollection($method),
        );

        foreach ($this->strategies as $strategy) {
            $strategy->build($operation, $context);
        }
    }

    private function applyDefinitions(ReflectionClass $reflection, ReflectionMethod $method, ApiOperation $operation): void
    {
        $definitionClass = null;
        $apiDoc = null;

        foreach ([$method->getAttributes(ApiDoc::class), $reflection->getAttributes(ApiDoc::class)] as $attributes) {
            if ($attributes === []) {
                continue;
            }

            $apiDoc = $attributes[0]->newInstance();
            $definitionClass = $apiDoc->definition;

            break;
        }

        if ($apiDoc !== null) {
            if ($apiDoc->groups !== null) {
                $operation->group(...$apiDoc->groups);
            }

            // Attribute tags replace the namer-inferred ones entirely.
            if ($apiDoc->tags !== null) {
                $operation->tags = $apiDoc->tags;
            }

            if ($apiDoc->paginated !== null) {
                $operation->paginated = $apiDoc->paginated;
            }
        }

        if ($definitionClass !== null) {
            if (! is_subclass_of($definitionClass, ApiOperationDocumented::class)) {
                throw new InvalidConfigurationException(sprintf(
                    '#[ApiDoc] definition [%s] must implement [%s].',
                    $definitionClass,
                    ApiOperationDocumented::class
                ));
            }

            (new ReflectionClass($definitionClass))->newInstanceWithoutConstructor()->documentApi($operation);

            return;
        }

        if ($reflection->implementsInterface(ApiOperationDocumented::class)) {
            $reflection->newInstanceWithoutConstructor()->documentApi($operation);
        }
    }

    private function buildNamer(): OperationNamer
    {
        $configured = ($this->config['discovery'] ?? [])['namer'] ?? ActionOperationNamer::class;

        return ConfigInstances::resolve([$configured], OperationNamer::class, 'Operation namer')[0];
    }

    /**
     * Compose the chain: configured detections, core detection inference,
     * core decoration, then configured decorations — so the config list
     * order is preference only and cannot change precedence.
     *
     * @return array<int, ApiOperationStrategy>
     */
    private function buildStrategies(): array
    {
        $detections = [];
        $decorations = [];

        $strategies = ConfigInstances::resolve(
            (array) ($this->config['strategies'] ?? []),
            ApiOperationStrategy::class,
            'Strategy'
        );

        foreach ($strategies as $strategy) {
            if ($strategy instanceof ResponseDecoratorStrategy) {
                $decorations[] = $strategy;
            } else {
                $detections[] = $strategy;
            }
        }

        return [
            ...$detections,
            ...$this->instantiate(self::CORE_DETECTION_STRATEGIES),
            ...$this->instantiate(self::CORE_DECORATION_STRATEGIES),
            ...$decorations,
        ];
    }

    /**
     * @param array<int, class-string> $classes
     * @return array<int, ApiOperationStrategy>
     */
    private function instantiate(array $classes): array
    {
        return array_map(static fn (string $class): ApiOperationStrategy => new $class(), $classes);
    }

    private function explicitMode(): bool
    {
        return ((string) (($this->config['discovery'] ?? [])['mode'] ?? 'auto')) === 'explicit';
    }

    /**
     * @return array<int, string>
     */
    private function excludePatterns(): array
    {
        if ($this->excludePatterns !== null) {
            return $this->excludePatterns;
        }

        $patterns = [];

        foreach ((array) (($this->config['discovery'] ?? [])['exclude'] ?? []) as $pattern) {
            $pattern = (string) $pattern;

            if (@preg_match($pattern, '') === false) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid discovery exclude pattern [%s].',
                    $pattern
                ));
            }

            $patterns[] = $pattern;
        }

        return $this->excludePatterns = $patterns;
    }

    private function controllerAttribute(ReflectionClass $reflection): ?object
    {
        foreach ([Controller::class, AutoController::class] as $attributeClass) {
            $attributes = $reflection->getAttributes($attributeClass);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }

    private function parameterType(?ReflectionParameter $parameter): ?string
    {
        if ($parameter === null) {
            return 'string';
        }

        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            return $type instanceof ReflectionNamedType ? null : 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            default => 'string',
        };
    }

    private function joinPath(string $prefix, string $path): string
    {
        if ($prefix === '') {
            return '/' . trim($path, '/');
        }

        return '/' . trim($prefix, '/') . '/' . trim($path, '/');
    }
}
