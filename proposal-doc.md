I think supporting response classes is a strong design choice, particularly for complex APIs with multiple response statuses, reusable response formats, and consistent documentation.

I would support both:

Explicit registration in ApiOperation
Attributes on response/resource classes
Automatic detection from controller return types

Each serves a different purpose.

1. Register response classes in ApiOperation

Recommended API:

$api
->response(200, PostResponse::class)
->response(201, CreatedPostResponse::class)
->response(404, NotFoundResponse::class);

For more control:

$api->response(
status: 200,
type: PostResponse::class,
configure: function (ApiResponse $response) {
$response
->description('Post retrieved successfully.')
->examples([
'id' => 123,
'title' => 'Hello',
]);
},
);

This works well when the endpoint has different responses depending on the status code.

Why this is useful
public function __invoke(): PostResponse|NotFoundResponse
{
// ...
}

PHP union return types don't necessarily communicate the HTTP status code. Explicit registration solves that problem.

2. Annotate response classes

You could use an attribute to mark a class as an API response:

#[ApiResponse(
status: 200,
description: 'Post retrieved successfully.',
)]
final class PostResponse
{
public int $id;

    public string $title;
}

However, I would avoid using the same attribute name as the internal ApiResponse model.

Use something like:

#[ApiResponseDefinition(
description: 'Post retrieved successfully.',
)]
final class PostResponse
{
public int $id;

    public string $title;
}

Or:

#[ApiSchema]
final class PostResponse
{
public int $id;

    public string $title;
}
Important distinction

A response class should generally describe the body schema, while the operation describes HTTP semantics:

PostResponse
├── Body fields
├── Field descriptions
└── Field examples

ApiResponse
├── HTTP status code
├── Response description
├── Headers
└── Content type

Therefore, I would prefer:

#[ApiSchema]
final class PostResponse
{
public int $id;

    public string $title;
}

Then:

$api->response(
status: 200,
schema: PostResponse::class,
);
3. Automatic detection from return types

Yes, this should be supported.

final class GetPostController
{
public function __invoke(): PostResponse
{
// ...
}
}

The generator detects PostResponse and generates the response schema automatically.

For a resource:

public function __invoke(): PostResource
{
return new PostResource(...);
}

The generator resolves the resource's schema.

Recommended detection priority
1. Explicit ApiOperation response definition
2. Explicit response attribute/definition
3. Controller return type
4. Resource/DTO schema inference
5. No response schema

Explicit configuration should override automatic inference.

4. Handling multiple response statuses

I recommend supporting a dedicated response definition interface:

interface ApiResponseDocumented
{
public function documentApiResponse(ApiResponse $response): void;
}

Example:

final class PostResponse implements ApiResponseDocumented
{
public function documentApiResponse(ApiResponse $response): void
{
$response
->description('Post retrieved successfully.')
->descriptions([
'id' => 'Unique post identifier.',
'title' => 'Post title.',
])
->examples([
'id' => 123,
'title' => 'Hello',
]);
}
}

Then:

$api->response(200, PostResponse::class);

This is particularly useful for reusable, complex responses.

5. My recommended final design

Support three layers:

Automatic
public function __invoke(): PostResponse

The generator infers the schema.

Explicit operation registration
$api->response(200, PostResponse::class);
$api->response(404, ErrorResponse::class);

The developer specifies HTTP response semantics.

Reusable response documentation
final class PostResponse implements ApiResponseDocumented
{
public function documentApiResponse(ApiResponse $response): void
{
$response
->description('Post retrieved successfully.')
->examples([
'id' => 123,
]);
}
}
One architectural adjustment

I would avoid making every response class responsible for its HTTP status code.

Prefer:

$api->response(200, PostResponse::class);

over:

#[ApiResponseDefinition(status: 200)]
final class PostResponse {}

The same schema may be returned with different statuses or used in multiple operations.

Final recommendation: Let response classes define the body schema and optional documentation. Let ApiOperation define the status code, headers, and HTTP-specific behavior. Automatically infer response schemas from return types, while allowing explicit response registration to override inference.

ok recap the AI coding agent doc
Hyperf OpenAPI Documentation Generator
1. Overview

Build a Hyperf-native OpenAPI documentation generator that automatically generates API documentation from application code.

The package should follow these principles:

OpenAPI is a projection of the application, not something developers must write their application around.
Automatic documentation by default.
Explicit customization when inference is insufficient.
Full escape hatch for complex use cases.
Avoid OpenAPI-specific attributes throughout ordinary business logic.
Remain independent of the underlying OpenAPI rendering library.

The package should support:

Automatic route and controller scanning
FormRequest validation inference
Request and response schema generation
DTOs and resources
PHP enums
Field-level descriptions, examples, and enums
Reusable response classes
Authentication and security schemes
API tags and documentation groups
JSON and YAML output
Extensible customization and filtering
2. Architecture

Use an intermediate documentation model between application code and the OpenAPI renderer.

Hyperf Application
│
▼
Route Scanner
│
▼
Controller / Type / Validation Resolvers
│
▼
Internal API Documentation Model
│
├── ApiDocument
├── ApiOperation
├── ApiSchema
├── ApiRequestBody
├── ApiResponse
├── ApiParameter
└── Security Models
│
▼
OpenAPI Renderer / Adapter
│
▼
OpenAPI JSON / YAML

The internal model must not expose the underlying OpenAPI library's classes as the primary public API.

3. Automatic Documentation

The generator should scan:

Hyperf routes
Controller methods
PHP parameter and return types
FormRequests
Validation rules
DTOs
Resources
PHP enums
Registered documentation definitions

Automatically infer:

Source	Documentation
Route	HTTP method and path
Controller	Operation and return types
FormRequest	Request schema and validation
PHP types	Property types
PHP enum	Enum values
Resource / DTO	Response schema
Middleware / guard	Optional security requirement
Attributes / interfaces	Explicit customization

No OpenAPI-specific code should be required for basic endpoints.

4. Operation Documentation

Provide an interface for explicit operation-level customization.

interface ApiOperationDocumented
{
public function documentApi(ApiOperation $api): void;
}

Example:

final class CreateUserController implements ApiOperationDocumented
{
public function __invoke(
CreateUserRequest $request
): UserResource {
// ...
}

    public function documentApi(ApiOperation $api): void
    {
        $api
            ->summary('Create user')
            ->description('Creates a new user account.')
            ->response(201, UserResource::class)
            ->tags('Users')
            ->security('bearerAuth');
    }
}
Resolution priority
1. Explicit method-level documentation
2. Controller-level documentation interface
3. Convention-based external definition
4. Automatic inference

Conflicting explicit definitions should produce a clear configuration error or follow documented precedence rules.

5. External Operation Definitions

Support external documentation classes for controllers that should not implement an interface.

#[ApiDoc(CreateUserApi::class)]
public function store(
CreateUserRequest $request
): UserResource {
// ...
}

The attribute should only point to a definition class. It should not become a miniature Swagger DSL.

Example:

final class CreateUserApi implements ApiOperationDocumented
{
public function documentApi(ApiOperation $api): void
{
$api
->summary('Create user')
->response(201, UserResource::class);
}
}
6. Schema Documentation

Separate schema documentation from operation documentation.

interface ApiSchemaDocumented
{
public function documentApiSchema(ApiSchema $schema): void;
}

Potential implementations:

FormRequests
DTOs
Resources
Response classes
Value objects
Enums

Example:

final class UserResponse implements ApiSchemaDocumented
{
public function documentApiSchema(ApiSchema $schema): void
{
$schema
->descriptions([
'id' => 'Unique user identifier.',
'name' => 'User display name.',
])
->examples([
'id' => 123,
'name' => 'Aldi',
]);
}
}

Operation-level documentation should describe HTTP behavior. Schema-level documentation should describe the data structure.

7. Request Bodies

Do not assume that a request class represents the entire request body.

A request may contain:

JSON
Multipart form data
File uploads
Multiple content types

Recommended API:

$api
->request(CreateUserRequest::class)
->response(201, UserResource::class);

Internal structure:

ApiOperation
└── ApiRequestBody
├── required
├── content types
└── schemas
Request resolution
1. Explicit ApiOperation::request()
2. FormRequest controller parameter
3. No request body
8. Field-Level Customization

Use short, contextual methods:

$api
->enums([
'status' => UserStatus::class,
'category' => ['news', 'comedy'],
])
->descriptions([
'status' => 'Current user status.',
'category' => 'Content category.',
])
->examples([
'status' => 'active',
'category' => 'news',
]);

These methods should be available on relevant documentation contexts, such as:

ApiSchema
ApiRequestBody
ApiResponse
Potentially parameter-specific builders

Also provide a comprehensive fallback:

$api->fields([
'status' => [
'enum' => UserStatus::class,
'description' => 'Current user status.',
'example' => 'active',
],
]);

The fields() API should support future properties such as:

format
default
nullable
deprecated
minimum
maximum
pattern
readOnly
writeOnly
9. Path and Query Parameters

Path and query parameters should have explicit context APIs.

$api->pathParameters([
'user' => [
'description' => 'The user ID.',
'example' => 123,
],
]);
$api->queryParameters([
'category' => [
'enum' => ['news', 'comedy'],
'description' => 'Filter posts by category.',
],
]);

Example:

$api
->pathParameters([
'user' => [
'description' => 'The user ID.',
'example' => 123,
],
])
->queryParameters([
'status' => [
'enum' => PostStatus::class,
],
]);

Internally distinguish:

ApiOperation
├── PathParameters
├── QueryParameters
├── HeaderParameters
├── CookieParameters
├── RequestBody
└── Responses

Although field customization can be shared internally, parameters are not the same as schema properties.

10. Enum Support

Support PHP backed enums automatically.

enum UserStatus: string
{
case ACTIVE = 'active';
case INACTIVE = 'inactive';
case SUSPENDED = 'suspended';
}

FormRequest:

final class UpdateUserRequest extends FormRequest
{
public function rules(): array
{
return [
'status' => [
'required',
Rule::enum(UserStatus::class),
],
];
}
}

Generated schema:

status:
type: string
enum:
- active
- inactive
- suspended

Use backed enum values, not case names.

For integer-backed enums:

enum UserType: int
{
case CUSTOMER = 1;
case ADMIN = 2;
}

Generate an integer schema.

Explicit enum definitions
$schema->enums([
'status' => UserStatus::class,
'category' => ['news', 'comedy'],
]);
Enum resolution precedence
1. Explicit field-level enum override
2. Explicit enum class
3. Inferred PHP enum type
4. Inferred validation rule
5. No enum

Validate that:

The enum class is a valid PHP enum.
Backed values are scalar.
Manual values have consistent scalar types.
Explicit values do not conflict with the inferred field type.
Unit enums

For unit enums without backed values, do not silently assume how values should be represented. Require explicit configuration or document a clear configured convention.

11. API Responses

Response classes should describe the response body schema. The operation should define HTTP semantics.

Response Schema
├── Body fields
├── Field descriptions
├── Field examples
└── Field enums

ApiResponse
├── HTTP status code
├── Response description
├── Headers
├── Content type
└── Body schema
Explicit response registration
$api
->response(200, PostResponse::class)
->response(404, NotFoundResponse::class);

For complex configuration:

$api->response(
status: 200,
schema: PostResponse::class,
configure: function (ApiResponse $response): void {
$response
->description('Post retrieved successfully.')
->examples([
'id' => 123,
'title' => 'Hello',
]);
},
);
Response documentation interface
interface ApiResponseDocumented
{
public function documentApiResponse(ApiResponse $response): void;
}

Example:

final class PostResponse implements ApiResponseDocumented
{
public function documentApiResponse(ApiResponse $response): void
{
$response
->description('Post retrieved successfully.')
->descriptions([
'id' => 'Unique post identifier.',
'title' => 'Post title.',
])
->examples([
'id' => 123,
'title' => 'Hello',
]);
}
}
Automatic response detection
final class GetPostController
{
public function __invoke(): PostResponse
{
// ...
}
}

The generator should automatically detect PostResponse from the return type.

Support:

Resource classes
DTOs
Response classes
Union return types where possible
Explicit response registrations
Multiple HTTP statuses
Response resolution priority
1. Explicit ApiOperation response definition
2. Explicit response documentation
3. Controller return type
4. Resource / DTO schema inference
5. No response schema

Do not require response classes to contain HTTP status codes:

$api->response(200, PostResponse::class);

The same schema can be reused with different HTTP status codes.

12. Security and Authentication

Separate:

Security scheme definitions
Security requirements on operations
Security scheme example
components:
securitySchemes:
bearerAuth:
type: http
scheme: bearer
bearerFormat: JWT

Operation requirement:

security:
- bearerAuth: []
  Security definition interface
  interface ApiSecurityDocumented
  {
  public function documentApiSecurity(
  ApiSecurityRegistry $security
  ): void;
  }

Example:

final class ApplicationSecurity implements ApiSecurityDocumented
{
public function documentApiSecurity(
ApiSecurityRegistry $security
): void {
$security->bearer(
name: 'bearerAuth',
bearerFormat: 'JWT',
description: 'JWT access token authentication.',
);

        $security->apiKey(
            name: 'internalApiKey',
            parameterName: 'X-Internal-API-Key',
            location: 'header',
            description: 'Internal service API key.',
        );
    }
}

Register definitions through configuration:

return [
'security_definitions' => [
ApplicationSecurity::class,
],
];

Support:

$security->bearer(...);
$security->apiKey(...);
$security->oauth2(...);
$security->openIdConnect(...);
$security->custom(...);
Operation security
$api->security('bearerAuth');

OAuth scopes:

$api->security('oauth2', ['users:read']);

Public endpoint:

$api->public();

public() should generate:

security: []

This explicitly overrides global security defaults.

AND / OR semantics

OR:

security:
- bearerAuth: []
- apiKeyAuth: []

AND:

security:
- bearerAuth: []
  signatureAuth: []

Possible APIs:

$api->securityAnyOf([
['bearerAuth' => []],
['apiKeyAuth' => []],
]);
$api->securityAllOf([
['bearerAuth' => []],
['signatureAuth' => []],
]);
Security resolution precedence
1. Explicit operation security
2. Explicit public override
3. Inferred middleware / guard security
4. Global default security
5. No security

Hyperf guards and middleware should not automatically be treated as OpenAPI security schemes. Provide an explicit mapping:

return [
'guards' => [
'api' => [
'security_scheme' => 'bearerAuth',
],
'internal' => [
'security_scheme' => 'internalServiceAuth',
],
],
];
13. Tags and Groups

Keep tags and groups separate.

Tags

Used to organize operations in the OpenAPI document.

Examples:

Users
Customers
Savings Accounts
Time Deposits
Groups

Used to determine which operations are included in a generated document.

Examples:

customer
admin
internal
partner

Do not automatically map groups to tags.

Group assignment

Support:

#[ApiGroup('customer')]

Or:

$api->group('customer');

Also support namespace, route, and configuration conventions.

Group resolution
1. Explicit operation-level group
2. Controller-level group
3. Convention / configuration
4. Default group
   Filtering
   php bin/hyperf.php openapi:generate
   php bin/hyperf.php openapi:generate --group=customer
   php bin/hyperf.php openapi:generate --tag="Savings Accounts"
   php bin/hyperf.php openapi:generate \
   --group=customer \
   --tag="Savings Accounts"

Filter operations before rendering:

Route Scanner
↓
All Operations
↓
Group / Tag Filters
↓
Selected Operations
↓
Schema Dependency Resolution
↓
OpenAPI Document

Referenced schemas must still be included recursively, even when their own group does not match.

Potential interface:

interface ApiOperationFilter
{
public function matches(ApiOperation $operation): bool;
}

Implement:

GroupFilter
TagFilter
Future VersionFilter
Future VisibilityFilter
14. Suggested Package Structure
    src/
    ├── Contract/
    │   ├── ApiOperationDocumented.php
    │   ├── ApiSchemaDocumented.php
    │   ├── ApiResponseDocumented.php
    │   ├── ApiSecurityDocumented.php
    │   └── ApiAuthenticationResolver.php
    │
    ├── Attribute/
    │   ├── ApiDoc.php
    │   └── ApiGroup.php
    │
    ├── Definition/
    │   ├── ApiDocument.php
    │   ├── ApiOperation.php
    │   ├── ApiSchema.php
    │   ├── ApiRequestBody.php
    │   ├── ApiResponse.php
    │   └── ApiParameter.php
    │
    ├── Security/
    │   ├── ApiSecurityScheme.php
    │   ├── ApiSecurityRequirement.php
    │   ├── ApiSecurityRegistry.php
    │   ├── SecuritySchemeRegistry.php
    │   ├── SecurityResolver.php
    │   ├── GuardSecurityMapper.php
    │   └── MiddlewareSecurityResolver.php
    │
    ├── Generator/
    │   ├── OpenApiGenerator.php
    │   ├── RouteScanner.php
    │   ├── ControllerScanner.php
    │   ├── RequestSchemaGenerator.php
    │   └── ResponseSchemaGenerator.php
    │
    ├── Resolver/
    │   ├── ApiDefinitionResolver.php
    │   ├── SchemaDefinitionResolver.php
    │   ├── ResponseDefinitionResolver.php
    │   └── EnumResolver.php
    │
    ├── Registry/
    │   ├── DocumentationRegistry.php
    │   ├── SchemaRegistry.php
    │   ├── ApiGroupRegistry.php
    │   └── EnumRegistry.php
    │
    ├── Filter/
    │   ├── ApiOperationFilter.php
    │   ├── GroupFilter.php
    │   └── TagFilter.php
    │
    ├── Renderer/
    │   └── OpenApiRenderer.php
    │
    ├── Adapter/
    │   └── OpenApi/
    │
    ├── Integration/
    │   └── Hyperf/
    │       └── Security/
    │
    └── Command/
    └── OpenApiGenerateCommand.php
15. Important Design Rules
    Keep HTTP semantics separate from schemas
    $api->response(200, PostResponse::class);

The response class defines the body. The operation defines the status code and HTTP behavior.

Prefer automatic inference

Developers should not need to manually register every DTO, enum, or resource.

Explicit customization overrides inference

Explicit configuration should take precedence over inferred metadata.

Avoid excessive attributes

Attributes should be lightweight pointers or markers, not a full OpenAPI DSL.

Support reusable schemas

Use inline schemas by default. Generate reusable component schemas when:

Explicitly registered
Reused across multiple operations
Required by configuration
Referenced as a reusable definition
Validate conflicts

Detect and report:

Duplicate operation IDs
Conflicting schema definitions
Invalid enum types
Undefined security schemes
Duplicate response status codes
Invalid parameter locations
Circular references
Invalid OpenAPI structures
16. Implementation Roadmap
    Phase 1: Core inference
    Route scanning
    Controller scanning
    FormRequest schema generation
    Resource and DTO detection
    Basic OpenAPI rendering
    Phase 2: Explicit customization
    ApiOperationDocumented
    ApiSchemaDocumented
    External operation definitions
    ApiDoc pointer attribute
    Field descriptions and examples
    Phase 3: Responses
    Automatic return type detection
    Explicit response registration
    Response documentation interface
    Multiple status codes
    Response headers and content types
    Phase 4: Enums
    PHP backed enum inference
    Manual enum arrays
    Field-level enum configuration
    Enum validation
    Reusable enum schemas
    Phase 5: Security
    Manual security scheme definitions
    Operation security requirements
    Public endpoint override
    Global defaults
    OAuth2 and API key support
    Phase 6: Hyperf integration
    Middleware inspection
    Guard-to-security mapping
    Optional authentication inference
    Hyperf-specific adapters
    Phase 7: Filtering and output
    Tags
    Groups
    Group and tag filters
    JSON output
    YAML output
    CLI options
    Phase 8: Advanced functionality
    Circular reference handling
    Schema component deduplication
    Version filtering
    Custom resolvers
    Custom OpenAPI extensions
    Comprehensive test suite
17. Core Recommendation

Build the package around this principle:

Automatic inference first, explicit fluent customization second, and a complete escape hatch when needed.

Use concise methods such as:

$api->descriptions([...]);
$api->examples([...]);
$api->enums([...]);

Use explicit context methods when necessary:

$api->pathParameters([...]);
$api->queryParameters([...]);
$api->request(...);
$api->response(...);

Keep schemas, HTTP responses, authentication schemes, and operation metadata as separate internal concepts while providing a convenient fluent developer experience.
