# hyperf-api-doc

OpenAPI documentation generator for [Hyperf](https://hyperf.io) 3.1. It infers the
document from code you already write — validation rules, return types, resources, auth
attributes — so Actions stay free of OpenAPI attributes.

- Infers request schemas from validation rules, responses from return types, security from auth attributes.
- Three documentation tiers: inference, interface on the action, external definition class.
- Outputs OpenAPI 3.0 JSON and/or YAML; the writer registry accepts custom formats. No swagger-php dependency.
- Per-document component scoping: each file only contains the schemas it references.

## Table of contents

- [hyperf-api-doc](#hyperf-api-doc)
  - [Table of contents](#table-of-contents)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Generating documents](#generating-documents)
  - [The three documentation tiers](#the-three-documentation-tiers)
    - [Tier 1: Inference only (zero config)](#tier-1-inference-only-zero-config)
    - [Tier 2: Interface on the action](#tier-2-interface-on-the-action)
    - [Tier 3: `#[ApiDoc]` attribute](#tier-3-apidoc-attribute)
    - [The `ApiOperation` fluent API](#the-apioperation-fluent-api)
  - [Documenting schemas (`ApiSchemaDocumented`)](#documenting-schemas-apischemadocumented)
  - [Response envelopes](#response-envelopes)
  - [Security](#security)
  - [Strategies](#strategies)
  - [Rule detectors](#rule-detectors)
  - [Spec extensions](#spec-extensions)
  - [Discovery](#discovery)
    - [Naming (discovery.namer)](#naming-discoverynamer)
  - [Documents (multiple files)](#documents-multiple-files)
  - [Reusable components (`#[ApiDocSchema]`)](#reusable-components-apidocschema)
  - [Validation rule inference](#validation-rule-inference)
  - [Configuration](#configuration)
  - [How it works](#how-it-works)
  - [License](#license)

## Requirements

- PHP >= 8.1
- Hyperf ~3.1 (`hyperf/command`, `hyperf/config`, `hyperf/di`, `hyperf/http-server`, `hyperf/validation`)
- `hyperf/devtool` — required only to publish the config file via `vendor:publish`
- `symfony/yaml` — required only for the built-in YAML writer (`^6.4 || ^7.0`)

## Installation

```bash
composer require dyz598/hyperf-api-doc
```

Publish the configuration (requires `hyperf/devtool`):

```bash
php bin/hyperf.php vendor:publish dyz598/hyperf-api-doc -i config
```

This creates `config/autoload/api_doc.php`. Then generate:

```bash
php bin/hyperf.php api-doc:generate
# API document generated: /path/to/app/storage/openapi/api.json
```

That is the entire setup. The default config scans your `src` directory for `#[Controller]` /
`#[AutoController]` classes and writes `storage/openapi/api.json`.

## Generating documents

```bash
php bin/hyperf.php api-doc:generate [options]
```

| Option | Description |
| --- | --- |
| `--format, -f` | A registered writer format (`json`, `yaml`) or `both` (default: config `output.format`) |
| `--output, -o` | Output directory (default: config `output.path`) |
| `--file` | Base file name for the single generated document |
| `--group` | Only include operations of these `#[ApiDoc(group: ...)]` groups (comma-separated) |
| `--tag` | Only include operations with this tag (repeatable) |

When `--group`/`--tag` are used, the run collapses to a single document named `api`.
Formats are resolved against the [writer registry](#configuration); register a class
implementing `HyperfApiDoc\Contract\Writer` to add other output formats.

## The three documentation tiers

### Tier 1: Inference only (zero config)

```php
#[Controller(prefix: 'api/users')]
class CreateUserController extends AbstractController
{
    public function __invoke(CreateUserRequest $request, UserService $service)
    {
        return $service->create($request->toDto())->toResource();
    }
}
```

From this alone the generator documents:

- **Request body** from `CreateUserRequest::rules()` — types, `required`, `maxLength`,
  `min`/`max`, `Rule::enum`, formats, nested fields (`array<string>` in dot notation, `*` wildcards).
- **Response** from the return type / body: `UserResource`, `UserResource::collection(...)`,
  or a `#[WithResource]` attribute (via [strategies](#strategies)) resolve to the schema
  inside its resource envelope.
- **Security** from an `#[Auth('guard')]` attribute (via [strategies](#strategies)) mapped
  through the security config's `guards` binding.
- **Path** from the controller prefix + `#[Mapping]` path; `{id}` placeholders (incl. Hyperf
  `{id:\d+}` patterns) become required path parameters.
- **Naming** via the [namer](#naming-discoverynamer): `CreateUserAction` → summary "Create user",
  `App\Action\User\...` → tag "Users", operationId `CreateUserAction.handle`. Swap in any
  `OperationNamer` for non-Action layouts; Tier 2/3 documentation overrides any of them.

### Tier 2: Interface on the action

```php
use HyperfApiDoc\Contract\ApiOperationDocumented;
use HyperfApiDoc\Model\ApiOperation;

class GetPostAction extends Action implements ApiOperationDocumented
{
    public function documentApi(ApiOperation $api): void
    {
        $api->summary('Get a post')
            ->response(200, PostResource::class, 'The post.')
            ->response(404, ErrorResource::class, 'Post not found.');
    }

    public function handle(): PostResource { /* ... */ }
}
```

`documentApi()` runs **after** inference; anything you set overrides the inferred value.
Defining **any** response disables response inference for that operation (the request body
and security inference remain).

### Tier 3: `#[ApiDoc]` attribute

```php
use HyperfApiDoc\Attribute\ApiDoc;

#[ApiDoc(CreatePostApi::class)]
class CreatePostAction extends Action { /* ... */ }
```

```php
class CreatePostApi implements ApiOperationDocumented
{
    public function documentApi(ApiOperation $api): void
    {
        $api->summary('Create a post')
            ->request(CreatePostRequest::class)
            ->response(201, PostResource::class, 'Created.')
            ->response(422, ErrorResource::class, 'Validation failed.');
    }
}
```

All parameters are optional — the attribute is a pointer, never an OpenAPI DSL:

- `#[ApiDoc(CreatePostApi::class)]` — external definition class (must implement
  `ApiOperationDocumented`)
- `#[ApiDoc(group: 'admin')]` — assigns a documentation [group](#documents-multiple-files);
  pass an array for several: `#[ApiDoc(group: ['admin', 'partner'])]`
- `#[ApiDoc(tags: ['Posts', 'Internal'])]` — tags, **replacing** the inferred ones;
  a Tier 2/3 definition may append more
- `#[ApiDoc(paginated: true)]` — paginated collection [meta](#response-envelopes);
  pass a class-string for a custom meta schema
- `#[ApiDoc]` — bare opt-in marker, meaningful with
  [explicit discovery](#discovery); everything else is inferred

Precedence: `#[ApiDoc]` (method or class) > `ApiOperationDocumented` on the action class >
pure inference.

### The `ApiOperation` fluent API

Available on `$api` in both tiers:

| Method | Purpose |
| --- | --- |
| `summary()`, `description()`, `tags()`, `operationId()`, `group()`, `deprecated()` | Operation metadata |
| `request(string $schema, ?string $contentType, ?callable $configure)` | Request body (FormRequest or explicit schema class) |
| `response(int $status, ?string $schema, ?string $description, ?callable $configure)` | One response per status (duplicates throw) |
| `security(string ...$schemes)` / `public()` | Security requirements (OR-combined) / explicitly public |
| `withoutDefaultResponses()` | Skip the configured validation/unauthorized default responses |
| `pathParameters()`, `queryParameters()`, `headerParameters()`, `cookieParameters()` | Parameter maps with `description/type/format/example/enum/required/deprecated` keys |
| `parameter(ApiParameter $p)` | Single parameter object — built via the `ApiParameter::path()/query()/header()/cookie()` factories (all take an optional `$required` flag) |
| `extensions(['x-internal' => true])` | `x-` vendor extensions |

The `configure` closures receive the `ApiRequestBody` / `ApiResponse` object for full control:

```php
$api->response(200, PostResource::class, 'The post.', function (ApiResponse $response): void {
    $response->collection()->headers(['X-Post-Id' => ['description' => 'Persisted post id']]);
});
```

`ApiRequestBody` fluent methods: `description()`, `contentType()`, `required()`, plus
the schema-level `descriptions()/examples()/enums()/fields()` overlay and `extensions()`.

`ApiResponse` fluent methods: `description()`, `schema()` (class-string or built
`ApiSchema`), `contentType()`, `collection()`, `headers()`, plus the schema-level
`descriptions()/examples()/enums()/fields()` overlay and `extensions()`.

## Documenting schemas (`ApiSchemaDocumented`)

Any class used as a schema (FormRequest, JsonResource, DTO) can implement the contract to
document its fields. Customization methods **upsert**: they patch inferred properties and
create missing ones, so the same API works for both inference overlays and standalone
definitions.

```php
class CreatePostRequest extends FormRequest implements ApiSchemaDocumented
{
    public function rules(): array
    {
        return [
            'title'    => 'required|string|max:150',
            'category' => ['required', new Enum(PostCategory::class)],
            'body'     => 'required|string',
        ];
    }

    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions([
                'title'    => 'Post title.',
                'category' => 'Post category.',
                'body'     => 'Post body content.',
            ])
            ->examples([
                'title'    => 'Hello',
                'category' => 'general',
                'body'     => 'Hello world!',
            ]);
    }
}
```

The enum list for `category` is **inferred automatically** from `Rule::enum()` — requests
never declare it twice. Responses have no rules to infer from, so declare enums explicitly:

```php
class PostResource extends JsonResource implements ApiSchemaDocumented
{
    public function documentApiSchema(ApiSchema $schema): void
    {
        $schema
            ->descriptions(['category' => 'Post category.', /* ... */])
            ->examples(['category' => 'general', /* ... */])
            ->enums(['category' => PostCategory::class]);
    }
}
```

`ApiSchema` fluent API:

| Method | Purpose |
| --- | --- |
| `title()`, `description()` | Schema-level metadata |
| `descriptions(array)`, `examples(array)` | Per-field description / example maps |
| `enums(array)` | Per-field enum — backed enum class-string or explicit value array |
| `fields(array)` | Raw constraint access: `type, format, description, example, default, enum, nullable, minimum, maximum, minLength, maxLength, minItems, maxItems, pattern, deprecated, readOnly, writeOnly` |
| `require(string ...$names)` | Mark properties required |
| `property(string $name)` | Get-or-create a single `ApiProperty` for full fluent control |

## Response envelopes

Envelopes are schemas, not renderer flags. `JsonResourceStrategy` (core) replaces a
`JsonResource` response schema with a built envelope: the resource — or a list of
them — under the resource's own `$wrap` key (default `data`; a declared
`?string $wrap = null` opts out). Paginated operations get `PaginationSchema`, which
adds the paginator's `links` and `meta`:

```php
#[ApiDoc(paginated: true)]                        // meta = PaginationMeta
#[ApiDoc(paginated: CustomPaginationMeta::class)]  // meta = custom schema
```

Additional fields merge into any envelope at generation time — colliding properties
union recursively, exactly like Hyperf's `additional()`. `MenumbingJsonResourceStrategy`
contributes menumbing's meta this way, so paginated responses document the merged
meta (pagination + request fields) the runtime actually emits:

```php
new JsonResourceSchema(PostResource::class);                   // {data}
new JsonResourceSchema(PostResource::class, collection: true); // {data: [...]}
new PaginationSchema(PostResource::class);                     // {data: [...], links, meta}

// Definitions may build envelopes explicitly and add any root fields.
$api->response(200, (new JsonResourceSchema(PostResource::class))
    ->additionalFields(VersionHeaderFields::class));
```

menumbing's `RequestMeta` (hostname, client_ip) and core `PaginationMeta`
(current_page, from, last_page, path, per_page, to, total) / `PaginationLinks` (first,
last, prev, next) ship with the package; extend them to add fields. Swap menumbing's
contribution with `new MenumbingJsonResourceStrategy(CustomFields::class)`.

## Security

One flat config map. String keys with array values are scheme definitions — `guards`
binds `#[Auth('guard')]` to the scheme, and `default => true` marks the global default;
class-string values are `ApiSecurityDocumented` definition classes — keyed to name
their single scheme, unkeyed when they register several:

```php
'security' => [
    'oauth2' => [
        'guards' => ['oauth2_client'],       // #[Auth('oauth2_client')] -> oauth2
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT',
        'description' => 'OAuth2 access token authentication.',
        // 'default' => true,                // global default requirement
    ],
    'partner' => PartnerSecurity::class,    // key renames its single scheme
    ApplicationSecurity::class,             // registers several, names its own
],
```

Definition classes register schemes programmatically:

```php
class ApplicationSecurity implements ApiSecurityDocumented
{
    public function documentApiSecurity(SecurityRegistry $security): void
    {
        $security->bearer('partnerBearer', 'JWT', 'Partner API bearer tokens.');
    }
}
```

`SecurityRegistry` helpers: `bearer()`, `basic()`, `apiKey()`, `oauth2()`, `openIdConnect()`,
`add(ApiSecurityScheme)`. Operation requirements come from guard inference (above), explicit
tier 2/3 calls (`->security(...)`, `->public()`), or the scheme marked `default`.

## Strategies

Strategies build each operation from endpoint metadata, after Tier 2/3 definitions —
explicit documentation wins and each strategy only fills what is still missing:

```php
interface ApiOperationStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void;
}
```

`ApiHandlerContext` carries the read-only endpoint facts: route path and HTTP method,
the controller/method reflections, all attributes (method-level first), middleware,
and the resolved return types.

Two phases keep precedence structural — the config list order is preference only:

1. **Detection** — configured strategies, then core inference, set the facts that
   are still missing.
2. **Decoration** — core, then configured strategies, enrich the documented
   responses; implement the `ResponseDecoratorStrategy` marker interface to run in
   this phase.

Core inference always runs and is not configurable:

| Strategy | Fills |
| --- | --- |
| `ReturnTypeStrategy` | Return types / `return new X(...)` → response (only if none set); union members merge |
| `FormRequestStrategy` | FormRequest parameter → request body source |
| `JsonResourceStrategy` | `JsonResource` responses → the resource / pagination envelope |

The menumbing integrations live in the `HyperfApiDoc\Menumbing` namespace, ship with
the package, and are registered via the `strategies` config (class-strings or
instances); they are inert when the packages are absent:

| Strategy | Fills |
| --- | --- |
| `MenumbingAuthStrategy` | `#[Auth('guard')]` → the operation's guard → security |
| `MenumbingResourceStrategy` | `#[WithResource(resource: X, statusCode: N)]` → response (only if none set) |
| `MenumbingJsonResourceStrategy` | menumbing's additional fields — the request meta |

Write your own for any stack:

```php
use HyperfApiDoc\Contract\ApiOperationStrategy;
use HyperfApiDoc\Model\ApiOperation;
use HyperfApiDoc\Scanner\ApiHandlerContext;

final class AuditStrategy implements ApiOperationStrategy
{
    public function build(ApiOperation $operation, ApiHandlerContext $context): void
    {
        // key off $context->attribute(...), $context->middleware, $context->returnTypes ...
    }
}

'strategies' => [/* ... */, new AuditStrategy()],
```

## Rule detectors

Detectors translate validation rules into OpenAPI facts:

```php
interface RuleDetector
{
    public function detect(object|string $rule): ?RuleFacts; // enum class/values, type, format
}
```

Always active first: `EnumRuleDetector` — it covers `Rule::enum()` objects, backed-enum
instances used as rules, custom rule objects holding a backed enum (class-string, instance,
or list of cases), and string rule names mapped through its constructor. Register additional
detectors via the `rule_detectors` config.

**String rule names → enums.** Projects validating through custom string rules
(`'required|string|in_gender'`) map each name to its backing enum with the same detector:

```php
use HyperfApiDoc\Scanner\EnumRuleDetector;

'rule_detectors' => [
    new EnumRuleDetector([
        'in_gender'         => Gender::class,
        'in_religion'       => Religion::class,
        'in_marital_status' => MaritalStatus::class,
        'in_education'      => Education::class,
    ]),
],
```

**Custom rules → type/format.** A detector can also report the OpenAPI shape itself.
A `money` rule that accepts integers or decimal numeric strings is best documented as
a decimal string (JSON floats lose precision):

```php
use HyperfApiDoc\Contract\RuleDetector;
use HyperfApiDoc\Scanner\RuleFacts;

final class MoneyRuleDetector implements RuleDetector
{
    public function detect(object|string $rule): ?RuleFacts
    {
        if ($rule !== 'money') {
            return null;
        }

        return new RuleFacts(type: 'string', format: 'decimal');
    }
}

'rule_detectors' => [new MoneyRuleDetector()],
```

## Spec extensions

Extension classes add computed `x-` data to the spec. One contract, called for
every document, operation, response, schema, parameter, and property rendered:

```php
interface SpecExtension
{
    public function extend(object $node): array; // x- keys for this node, or []
}
```

The shipped `ApidogEnumExtension` adds `x-apidog-enum` so Apidog shows each enum
value with a name and a description. Descriptions come from the enum itself —
implement `ApiEnumDocumented`:

```php
use HyperfApiDoc\Contract\ApiEnumDocumented;

enum PostCategory: string implements ApiEnumDocumented
{
    case GENERAL = 'general';
    // ...

    public function apiDescription(): ?string
    {
        return match ($this) {
            self::GENERAL => 'General-purpose posts.',
            self::NEWS    => 'Announcements and updates.',
            // ...
        };
    }
}
```

Both request fields (via `Rule::enum()`) and response fields (via
`->enums(['category' => PostCategory::class])`) render:

```json
"category": {
    "type": "string",
    "enum": ["general", "news", "tech", "comedy"],
    "x-apidog-enum": [
        { "value": "general", "description": "General-purpose posts." }
    ]
}
```

Write your own for any tool — return `x-` data for the nodes you care about:

```php
use HyperfApiDoc\Contract\SpecExtension;
use HyperfApiDoc\Model\ApiOperation;

final class InternalFlagExtension implements SpecExtension
{
    public function extend(object $node): array
    {
        return $node instanceof ApiOperation ? ['x-internal' => true] : [];
    }
}
```

Register via the `output.extensions` config (class-strings or instances):

```php
'output' => [
    // ...
    'extensions' => [
        ApidogEnumExtension::class,
        new InternalFlagExtension(),
    ],
],
```

Extensions set explicitly through `extensions()` win when keys collide.

## Discovery

Which routes enter the document, and how the discovered ones are labeled:

```php
'discovery' => [
    'mode' => 'auto',              // 'auto' = every route; 'explicit' = only #[ApiDoc]-marked
    'exclude' => [
        '#^/v1/debug/#',           // regex vs the resolved route path
        '#/internal/#',
    ],
    'namer' => ActionOperationNamer::class,
],
```

In `explicit` mode only routes (methods or classes) carrying `#[ApiDoc]` are documented —
including the bare `#[ApiDoc]` marker form. Exclusions apply in both modes.

### Naming (discovery.namer)

The namer labels what nothing else labels: summary, tags, and operationId of inferred
operations. The shipped `ActionOperationNamer` derives Action-style names —
`CreatePostAction` → "Create post", `App\Action\Post\...` → tag "Posts",
`CreatePostAction.handle`. Tier 2/3 documentation and `#[ApiDoc(tags: ...)]` override it.

Not using Action classes? Point `namer` at any `OperationNamer` implementation —
extending `ActionOperationNamer` keeps the parts you like:

```php
use HyperfApiDoc\Naming\ActionOperationNamer;
use HyperfApiDoc\Support\Inflector;
use ReflectionClass;
use ReflectionMethod;

class ControllerNamer extends ActionOperationNamer
{
    public function tags(ReflectionClass $class, ReflectionMethod $method): array
    {
        $base = (string) preg_replace('/Controller$/', '', $class->getShortName());

        return [Inflector::pluralize($base)];   // UserController -> "Users"
    }

    public function summary(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        return ucfirst($method->getName());      // store() -> "Store"
    }
}
```

## Documents (multiple files)

Assign operations to documentation groups (independent from OpenAPI tags) and generate
one file per document. An operation may belong to several groups — it appears in every
document whose filter matches any of them:

```php
#[ApiDoc(group: 'admin')]
class AdminStatsAction extends Action { /* ... */ }

#[ApiDoc(group: ['public', 'internal'])]
class StatusAction extends Action { /* ... */ }
```

```php
'documents' => [
    'customer' => ['group' => 'customer', 'file' => 'customer'],
    'internal' => ['group' => ['admin', 'partner'], 'file' => 'internal'], // OR-match
    'shared'   => ['group' => [null], 'file' => 'shared'],                 // ungrouped only
    'public'   => ['tags' => ['Posts'], 'file' => 'public'],               // optional tag filter
],
```

Each document is rendered with its own component set — `components/schemas` only contains
the `$ref`s that document actually uses.

## Reusable components (`#[ApiDocSchema]`)

- A class marked `#[ApiDocSchema]` always renders as a `#/components/schemas` `$ref`,
  even when used once. `#[ApiDocSchema(name: 'Post')]` sets the component name;
  duplicate explicit names throw.
- Unmarked classes always render inline, no matter how often they are used.
- Nested DTO classes (constructor-promoted properties, readonly properties) are expanded
  recursively up to depth 5, with cycle protection; mark a nested class to render it as
  a component `$ref` instead.

```php
#[ApiDocSchema]
class PostResource extends JsonResource implements ApiSchemaDocumented { /* ... */ }
```

## Validation rule inference

| Rule | OpenAPI mapping |
| --- | --- |
| `required`, `nullable` | `required` list, `nullable` |
| `string` / `array` / `integer` / `numeric` / `boolean` / `uuid` / `email` / `url` / `date` / `date_format` | `type`, `format` |
| `max:n` / `min:n` | `maxLength`/`maximum`/`maxItems` — typed by field |
| `in:a,b` | `enum` values |
| `Rule::enum(...)`, enum-backed rule objects, mapped string rules | `enum` via the [detector chain](#rule-detectors) |
| Dot notation (`user.name`) and `*` wildcards | Nested object / array-of-object schemas |

## Configuration

Full reference of `config/autoload/api_doc.php` (all keys optional; shown with defaults):

```php
return [
    // Directories scanned for routed classes.
    'scan' => ['paths' => [BASE_PATH . '/src']],

    'discovery' => [
        'mode' => 'auto',                    // auto | explicit
        'exclude' => [],                     // regex patterns vs route paths (see Discovery)
        'namer' => ActionOperationNamer::class, // see Naming
    ],

    // Integration strategies; core inference (return type, FormRequest,
    // envelopes) always runs and is not listed here (see Strategies).
    'strategies' => [
        MenumbingAuthStrategy::class,
        MenumbingResourceStrategy::class,
        MenumbingJsonResourceStrategy::class,
    ],

    // Rule detectors, after the built-in EnumRuleDetector.
    'rule_detectors' => [
        // new EnumRuleDetector(['in_gender' => Gender::class]),
    ],

    'output' => [
        'path' => BASE_PATH . '/storage/openapi',
        'format' => 'json',        // writer format (json, yaml) or both
        'pretty' => true,
        'writers' => [JsonWriter::class, YamlWriter::class],
        'extensions' => [ApidogEnumExtension::class], // see Spec extensions
    ],

    // The OpenAPI info object.
    'info' => [
        'title' => env('APP_NAME', 'API'),
        'description' => null,
        'version' => '1.0.0',
    ],

    'servers' => [
        // ['url' => 'https://api.example.com', 'description' => 'API Server'],
    ],

    // Flat security map: guards/default inside scheme entries, class-strings
    // are ApiSecurityDocumented definitions — keyed to name their single
    // scheme (see Security).
    'security' => [
        // 'oauth2' => ['guards' => ['oauth2_client'], 'type' => 'http', ...],
    ],

    // Auto error responses when an operation has a FormRequest (validation) or an
    // auth guard (unauthorized); absent or null disables. Class-string uses the
    // default status (422/401); an array overrides it. Opt out per operation
    // with ->withoutDefaultResponses().
    'responses' => [
        // 'validation' => ErrorResource::class,
        // 'unauthorized' => ['status' => 401, 'schema' => ErrorResource::class],
    ],

    // One file per document (see Documents).
    'documents' => [
        // 'customer' => ['group' => 'customer', 'file' => 'customer'],
    ],
];
```

Configuration errors (bad status codes, duplicate responses, invalid scheme names,
unbacked enums, invalid exclude patterns, unknown namer/strategy/detector/extension
classes, non `x-` extension keys, ...)
throw `InvalidConfigurationException`, reported cleanly by the command.

## How it works

1. `RouteScanner` reflects over all classes in `scan.paths`, applying discovery rules
   and building an `ApiHandlerContext` per route.
2. Tier 2/3 definitions run first, then detection strategies (configured, then
   core), then decoration strategies; the namer labels whatever is still
   undocumented.
3. `DocumentationGenerator` merges union overlays, adds configured auto responses,
   and maps guards to security schemes.
4. `OpenApiRenderer` turns the model into an OpenAPI 3.0 array — envelopes are
   plain schemas by then; the writer registry encodes it. The model is
   renderer-agnostic — custom output formats only need a `Writer` implementation.

## License

MIT
