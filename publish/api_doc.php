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
use HyperfApiDoc\Extension\ApidogEnumExtension;
use HyperfApiDoc\Menumbing\MenumbingAuthStrategy;
use HyperfApiDoc\Menumbing\MenumbingJsonResourceStrategy;
use HyperfApiDoc\Menumbing\MenumbingResourceStrategy;
use HyperfApiDoc\Naming\ActionOperationNamer;
use HyperfApiDoc\Renderer\JsonWriter;
use HyperfApiDoc\Renderer\YamlWriter;

use function Hyperf\Support\env;

return [
    // Directories scanned for routed classes.
    'scan' => [
        'paths' => [
            BASE_PATH . '/src',
        ],
    ],

    // Which routes enter the document and how they are labeled.
    'discovery' => [
        // 'auto' documents every route; 'explicit' only #[ApiDoc]-marked ones.
        'mode' => 'auto',
        // Regexes matched against the resolved route path.
        'exclude' => [
            // '#^/v1/debug/#',
        ],
        // Labels (summary, tags, operationId) for undocumented operations.
        // Any HyperfApiDoc\Contract\OperationNamer implementation.
        'namer' => ActionOperationNamer::class,
    ],

    // Integration strategies (class-strings or instances); core inference
    // (return type, FormRequest, envelopes) always runs and is not
    // listed here — see README: Strategies.
    'strategies' => [
        MenumbingAuthStrategy::class,
        MenumbingResourceStrategy::class,
        MenumbingJsonResourceStrategy::class,
    ],

    // Extra validation rule detectors after the built-in EnumRuleDetector.
    // Its constructor maps string rules to enums: new EnumRuleDetector(['in_gender' => Gender::class]).
    'rule_detectors' => [],

    'output' => [
        'path' => BASE_PATH . '/storage/openapi',
        // A registered writer format (json, yaml) or 'both'.
        'format' => 'json',
        'pretty' => true,
        // Implement HyperfApiDoc\Contract\Writer to add formats.
        'writers' => [
            JsonWriter::class,
            YamlWriter::class,
        ],
        // Spec extension classes; see README: Spec extensions.
        'extensions' => [
            ApidogEnumExtension::class,
        ],
    ],

    // The OpenAPI info object.
    'info' => [
        'title' => env('APP_NAME', 'API'),
        'description' => null,
        'version' => '1.0.0',
    ],

    'servers' => [],

    // Security schemes; class-string entries register ApiSecurityDocumented
    // definitions — keyed to name their single scheme, unkeyed for several.
    'security' => [
        // 'oauth2' => [
        //     'type' => 'http',
        //     'scheme' => 'bearer',
        //     'guards' => ['authentik'], // #[Auth('authentik')] maps here
        //     'default' => true,         // applies to unguarded operations
        // ],
        // 'partner' => \App\ApiDoc\PartnerSecurity::class,
        // \App\ApiDoc\ApplicationSecurity::class,
    ],

    // Auto error responses; absent or null disables. Class-string uses the
    // default status (422/401); an array overrides it. Opt out per
    // operation with ->withoutDefaultResponses().
    'responses' => [
        // 'validation' => \App\Resource\ErrorResource::class,
        // 'unauthorized' => ['status' => 401, 'schema' => \App\Resource\ErrorResource::class],
    ],

    // One file per entry; omit for a single api.json.
    'documents' => [
        // 'admin' => ['group' => 'admin', 'file' => 'admin'],
        // 'internal' => ['group' => ['admin', 'partner'], 'file' => 'internal'],
    ],
];
