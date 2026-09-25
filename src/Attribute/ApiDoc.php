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

namespace HyperfApiDoc\Attribute;

use Attribute;
use HyperfApiDoc\Contract\ApiOperationDocumented;

/**
 * Marks a routed method (or class) for documentation. All parameters are
 * optional; the attribute stays a pointer — never an OpenAPI DSL.
 *
 *  - #[ApiDoc(CreatePostApi::class)]              external definition class
 *  - #[ApiDoc(group: 'admin')]                    one documentation group
 *  - #[ApiDoc(group: ['admin', 'partner'])]       several groups
 *  - #[ApiDoc(tags: ['Posts'])]                   tags, replacing inferred ones
 *  - #[ApiDoc(paginated: true)]                   paginated collection meta
 *  - #[ApiDoc]                                    bare opt-in marker, meaningful
 *    with discovery.mode = "explicit"
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class ApiDoc
{
    public readonly ?string $definition;

    /** @var null|array<int, string> */
    public readonly ?array $groups;

    /** @var null|array<int, string> */
    public readonly ?array $tags;

    /** true = default pagination meta; class-string = custom meta schema. */
    public readonly bool|string|null $paginated;

    /**
     * String and array forms are both accepted for group and tags and
     * normalized to arrays.
     *
     * @param null|class-string<ApiOperationDocumented> $definition
     * @param null|array<int, string>|string $group
     * @param null|array<int, string>|string $tags
     * @param null|bool|class-string $paginated
     */
    public function __construct(
        ?string $definition = null,
        array|string|null $group = null,
        array|string|null $tags = null,
        bool|string|null $paginated = null,
    ) {
        $this->definition = $definition;
        $this->groups = $group === null ? null : array_values((array) $group);
        $this->tags = $tags === null ? null : array_values((array) $tags);
        $this->paginated = $paginated;
    }
}
