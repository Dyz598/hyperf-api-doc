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

use HyperfApiDoc\Concerns\MergesAdditionalFields;
use HyperfApiDoc\Contract\SpecExtension;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Generator\SchemaRegistry;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Model\ApiComposition;
use HyperfApiDoc\Model\ApiDocument;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Model\ApiParameter;
use HyperfApiDoc\Model\ApiProperty;
use HyperfApiDoc\Model\ApiRequestBody;
use HyperfApiDoc\Model\ApiResponse;
use HyperfApiDoc\Model\ApiSchema;
use HyperfApiDoc\Support\ConfigInstances;
use HyperfApiDoc\Support\Inflector;
use JsonSerializable;
use stdClass;

/**
 * Renders the intermediate model into an OpenAPI 3.1.2 array.
 *
 * Component emission is scoped per render() call: only schemas actually
 * referenced through $ref in the rendered paths are emitted under
 * components/schemas, so filtered documents never carry dead components.
 */
class OpenApiRenderer
{
    use MergesAdditionalFields;

    /** @var array<class-string, true> classes'd during the current render */
    protected array $usedRefs = [];

    /** @var null|array<int, SpecExtension> resolved lazily from config */
    protected ?array $specExtensions = null;

    public function __construct(
        protected SchemaRegistry $registry,
        protected array $config = [],
        protected SchemaResolver $resolver = new SchemaResolver(),
    ) {}

    public function render(ApiDocument $document): array
    {
        $this->usedRefs = [];

        $paths = [];

        foreach ($document->operations as $operation) {
            $paths[$operation->path][strtolower($operation->httpMethod)] = $this->renderOperation($operation);
        }

        $spec = [
            'openapi' => $document->openapi,
            'info' => $this->renderInfo($document),
            'paths' => $paths ?: new stdClass(),
        ];

        $tags = $document->tags();
        if ($tags !== []) {
            $spec['tags'] = array_map(static fn (string $name) => ['name' => $name], $tags);
        }

        if ($document->servers !== []) {
            $spec['servers'] = $document->servers;
        }

        $components = $this->renderComponents($document);
        if ($components !== null) {
            $spec['components'] = $components;
        }

        if ($document->security !== null) {
            $spec['security'] = $document->security;
        }

        return [...$spec, ...$this->extensionsFor($document)];
    }

    public function renderSchema(?ApiSchema $schema): array|stdClass
    {
        if ($schema === null) {
            return ['type' => 'object'];
        }

        $item = ['type' => 'object'];

        if ($schema->title !== null) {
            $item['title'] = $schema->title;
        }

        if ($schema->description !== null) {
            $item['description'] = $schema->description;
        }

        if ($schema->properties !== []) {
            $item['properties'] = array_map(
                fn (ApiProperty $property) => $this->renderProperty($property),
                $schema->properties
            );
        } else {
            $item['additionalProperties'] = true;
        }

        if ($schema->required !== []) {
            $item['required'] = $schema->required;
        }

        if ($schema->hasExample) {
            $item['example'] = $this->normalizeValue($schema->example);
        }

        if ($schema->examples !== []) {
            $item['examples'] = array_map(
                fn (mixed $value): mixed => $this->normalizeValue($value),
                $schema->examples
            );
        }

        return [...$item, ...$this->extensionsFor($schema), ...$schema->extensionData()];
    }

    public function renderProperty(ApiProperty $property): array|stdClass
    {
        // Nested documented objects become $refs when their class is marked;
        // otherwise their properties render inline below.
        if ($property->refClass !== null) {
            $ref = $this->refFor($property->refClass);

            if ($ref !== null) {
                return $ref;
            }
        }

        $item = [];

        if ($property->type !== null) {
            $item['type'] = $property->type;
        }

        if ($property->format !== null) {
            $item['format'] = $property->format;
        }

        if ($property->description !== null) {
            $item['description'] = $property->description;
        }

        if ($property->enum !== null) {
            $item['enum'] = $property->enum;
        }

        if ($property->hasExample) {
            $item['example'] = $this->normalizeValue($property->example);
        }

        if ($property->hasDefault) {
            $item['default'] = $this->normalizeValue($property->default);
        }

        foreach (['minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'pattern'] as $constraint) {
            if ($property->{$constraint} !== null) {
                $item[$constraint] = $property->{$constraint};
            }
        }

        if ($property->deprecated === true) {
            $item['deprecated'] = true;
        }

        if ($property->readOnly === true) {
            $item['readOnly'] = true;
        }

        if ($property->writeOnly === true) {
            $item['writeOnly'] = true;
        }

        if ($property->items !== null) {
            $item['items'] = $this->renderProperty($property->items);
        }

        if ($property->properties !== null) {
            $item['properties'] = array_map(
                fn (ApiProperty $child) => $this->renderProperty($child),
                $property->properties
            );

            if ($property->type === null) {
                $item['type'] = 'object';
            }

            if ($property->required !== null && $property->required !== []) {
                $item['required'] = $property->required;
            }
        }

        // 3.1 expresses nullability as a type array; no declared type
        // means any value, null included
        if ($property->nullable === true && isset($item['type']) && ! is_array($item['type'])) {
            $item['type'] = [$item['type'], 'null'];
        }

        // A bare ref to an unmarked class renders its schema inline.
        if ($item === [] && $property->refClass !== null) {
            $inline = $this->registry->schema($property->refClass) ?? new ApiSchema();

            return $this->renderSchema($inline);
        }

        return [...$item, ...$this->extensionsFor($property)];
    }

    private function renderOperation(ApiOperation $operation): array
    {
        $item = [];

        if ($operation->tags !== []) {
            $item['tags'] = $operation->tags;
        }

        if ($operation->summary !== null) {
            $item['summary'] = $operation->summary;
        }

        if ($operation->description !== null) {
            $item['description'] = $operation->description;
        }

        if ($operation->operationId !== null) {
            $item['operationId'] = $operation->operationId;
        }

        if ($operation->deprecated) {
            $item['deprecated'] = true;
        }

        $parameters = $this->renderParameters($operation);
        if ($parameters !== []) {
            $item['parameters'] = $parameters;
        }

        if ($operation->requestBody !== null) {
            $item['requestBody'] = $this->renderRequestBody($operation->requestBody);
        }

        $item['responses'] = $this->renderResponses($operation);

        if ($operation->security !== null) {
            $item['security'] = $operation->security;
        }

        return [...$item, ...$this->extensionsFor($operation), ...$operation->extensionData()];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renderParameters(ApiOperation $operation): array
    {
        $order = ['path' => 0, 'query' => 1, 'header' => 2, 'cookie' => 3];
        $parameters = array_values($operation->parameters);

        usort($parameters, static function (ApiParameter $a, ApiParameter $b) use ($order) {
            return ($order[$a->in] ?? 9) <=> ($order[$b->in] ?? 9);
        });

        $rendered = [];

        foreach ($parameters as $parameter) {
            $item = [
                'name' => $parameter->name,
                'in' => $parameter->in,
                'required' => $parameter->in === 'path' ? true : $parameter->required,
            ];

            if ($parameter->deprecated) {
                $item['deprecated'] = true;
            }

            if ($parameter->description !== null) {
                $item['description'] = $parameter->description;
            }

            $schema = [];

            if ($parameter->type !== null) {
                $schema['type'] = $parameter->type;
            }

            if ($parameter->format !== null) {
                $schema['format'] = $parameter->format;
            }

            if ($parameter->enum !== null) {
                $schema['enum'] = $parameter->enum;
            }

            if ($schema !== []) {
                $item['schema'] = $schema;
            }

            if ($parameter->hasExample) {
                $item['example'] = $this->normalizeValue($parameter->example);
            }

            $rendered[] = [...$item, ...$this->extensionsFor($parameter)];
        }

        return $rendered;
    }

    private function renderRequestBody(ApiRequestBody $body): array
    {
        if ($body->composition !== null && $body->hasOverlay()) {
            throw new InvalidConfigurationException(sprintf(
                'Composed request bodies do not support field overlays; document the member classes for request body of [%s].',
                $body->schemaClass ?? 'composition'
            ));
        }

        $item = ['required' => $body->required];

        if ($body->description !== null) {
            $item['description'] = $body->description;
        }

        $schema = $body->composition !== null
            ? $this->renderComposition($body->composition)
            : $this->schemaForClass($body->schemaClass, $body->hasOverlay() ? $body->overlay() : null);

        $item['content'] = [
            $body->contentType => $this->mediaType($schema, $body),
        ];

        return [...$item, ...$body->extensionData()];
    }

    /**
     * @return array<string, mixed>
     */
    private function renderResponses(ApiOperation $operation): array
    {
        $responses = [];

        foreach ($operation->responses as $response) {
            $item = [
                'description' => $response->description ?? Inflector::reasonPhrase($response->status),
            ];

            if ($response->headers !== []) {
                $item['headers'] = $response->headers;
            }

            $schema = null;

            if ($response->composition !== null) {
                if ($response->hasOverlay() || $response->mergeClasses !== []) {
                    throw new InvalidConfigurationException(
                        'Composed responses do not support field overlays or merge(); document the member classes instead.'
                    );
                }

                $schema = $this->renderComposition($response->composition);
            } elseif (is_string($response->schema)) {
                $schema = $this->schemaForClass(
                    $response->schema,
                    $response->hasOverlay() ? $response->overlay() : null
                );
            } elseif ($response->schema !== null) {
                $schema = $this->renderBodySchema(
                    $response->schema,
                    $response->hasOverlay() ? $response->overlay() : null
                );
            } elseif ($response->hasOverlay()) {
                $schema = $this->renderSchema($response->overlay());
            } elseif ($response->hasExample || $response->examples !== []) {
                $schema = $this->renderSchema(null);
            }

            // Collections wrap the schema in an array, after envelopes.
            if ($schema !== null && $response->arrayOf === true) {
                $schema = ['type' => 'array', 'items' => $schema];
            }

            if ($response->status !== 204 && $schema !== null) {
                $item['content'] = [
                    $response->contentType => $this->mediaType($schema, $response),
                ];
            }

            $item = [...$item, ...$this->extensionsFor($response), ...$response->extensionData()];

            $responses[(string) $response->status] = $item;
        }

        return $responses;
    }

    /**
     * A oneOf/anyOf/allOf schema: class-string members resolve through
     * the registry (reusable components become $refs), ApiSchema members
     * render inline.
     */
    private function renderComposition(ApiComposition $composition): array
    {
        $item = [
            $composition->keyword->value => array_map(
                fn (ApiSchema|string $member): array|stdClass => is_string($member)
                    ? $this->memberSchema($member)
                    : $this->renderBodySchema($member),
                $composition->members
            ),
        ];

        if ($composition->discriminatorProperty !== null) {
            $item['discriminator'] = ['propertyName' => $composition->discriminatorProperty];

            if ($composition->discriminatorMapping !== []) {
                $item['discriminator']['mapping'] = array_map(
                    function (string $class): string {
                        $ref = $this->refFor($class);

                        if ($ref === null) {
                            throw new InvalidConfigurationException(sprintf(
                                'Discriminator mapping target [%s] must be a reusable #[ApiDocSchema] component.',
                                $class
                            ));
                        }

                        return $ref['$ref'];
                    },
                    $composition->discriminatorMapping
                );
            }
        }

        return $item;
    }

    /**
     * A composition member: lazily registered so renderer-only usage
     * resolves members; marked classes become $refs.
     */
    private function memberSchema(string $class): array|stdClass
    {
        if ($this->registry->schema($class) === null) {
            $this->registry->register($class, $this->resolver->resolve($class));
        }

        return $this->schemaForClass($class);
    }

    /**
     * The media type object: the body schema plus its example(s).
     */
    private function mediaType(array|stdClass $schema, ApiRequestBody|ApiResponse $node): array
    {
        $media = ['schema' => $schema];

        if ($node->hasExample) {
            $media['example'] = $this->normalizeValue($node->example);
        }

        if ($node->examples !== []) {
            $media['examples'] = $node->examples;
        }

        return $media;
    }

    private function schemaForClass(?string $class, ?ApiSchema $overlay = null): array|stdClass
    {
        if ($class === null) {
            return $this->renderSchema($overlay ?? new ApiSchema());
        }

        $ref = $this->registry->ref($class);

        if ($ref !== null) {
            // Reused component: field-level overlays belong on the
            // class-level ApiSchemaDocumented, not on individual operations.
            $this->usedRefs[$class] = true;

            return $ref;
        }

        $base = $this->registry->schema($class) ?? new ApiSchema();

        if ($overlay !== null) {
            $base = (clone $base)->merge($overlay);
        }

        return $this->renderSchema($base);
    }

    /**
     * Component ref for a referenced class, registering it lazily; null
     * to render inline.
     */
    private function refFor(string $class): ?array
    {
        if ($this->registry->schema($class) === null) {
            $this->registry->register($class, $this->resolver->resolve($class));
        }

        $ref = $this->registry->ref($class);

        if ($ref !== null) {
            $this->usedRefs[$class] = true;
        }

        return $ref;
    }

    /**
     * Render a built response schema: additional field classes resolve
     * and merge in first, then field-level overlays apply.
     */
    private function renderBodySchema(ApiSchema $schema, ?ApiSchema $overlay = null): array|stdClass
    {
        $schema = $this->withAdditionalFields($schema);

        if ($overlay !== null) {
            $schema = (clone $schema)->merge($overlay);
        }

        return $this->renderSchema($schema);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function renderComponents(ApiDocument $document): ?array
    {
        $components = [];

        $schemas = [];

        foreach ($this->registry->reusable() as $class => $schema) {
            // Emit only components actually referenced in this document.
            if (isset($this->usedRefs[$class])) {
                $schemas[(string) $this->registry->name($class)] = $this->renderSchema($schema);
            }
        }

        if ($schemas !== []) {
            $components['schemas'] = $schemas;
        }

        if ($document->securitySchemes !== []) {
            $components['securitySchemes'] = array_map(
                static fn ($scheme) => $scheme->toArray(),
                $document->securitySchemes
            );
        }

        return $components === [] ? null : $components;
    }

    private function renderInfo(ApiDocument $document): array
    {
        $info = [
            'title' => $document->info['title'],
            'version' => $document->info['version'],
        ];

        if (! empty($document->info['description'])) {
            $info['description'] = $document->info['description'];
        }

        return $info;
    }

    /**
     * x- data from the configured spec extensions for this node.
     *
     * @return array<string, mixed>
     */
    private function extensionsFor(object $node): array
    {
        if ($this->specExtensions() === []) {
            return [];
        }

        $data = [];

        foreach ($this->specExtensions() as $extension) {
            foreach ($extension->extend($node) as $key => $value) {
                $key = (string) $key;

                if (! str_starts_with($key, 'x-')) {
                    throw new InvalidConfigurationException(sprintf(
                        'OpenAPI extension keys must be x- prefixed, got [%s].',
                        $key
                    ));
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @return array<int, SpecExtension>
     */
    private function specExtensions(): array
    {
        if ($this->specExtensions === null) {
            $this->specExtensions = ConfigInstances::resolve(
                (array) ($this->config['output']['extensions'] ?? []),
                SpecExtension::class,
                'Spec extension'
            );
        }

        return $this->specExtensions;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }
}
