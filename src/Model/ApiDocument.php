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

use HyperfApiDoc\Filter\ApiOperationFilter;

/**
 * The root of the intermediate documentation model, one per generation run.
 */
class ApiDocument
{
    public string $openapi = '3.0.3';

    /** @var array{title: string, description?: null|string, version: string} */
    public array $info = [
        'title' => 'API',
        'description' => null,
        'version' => '1.0.0',
    ];

    /** @var array<int, array{url: string, description?: null|string}> */
    public array $servers = [];

    /** @var array<int, ApiOperation> */
    public array $operations = [];

    /** @var array<string, ApiSecurityScheme> */
    public array $securitySchemes = [];

    /** @var null|array<array<string, array<string>>|array<string, array>> */
    public ?array $security = null;

    public function operation(ApiOperation $operation): static
    {
        $this->operations[] = $operation;
        return $this;
    }

    /**
     * @return array<int, string> unique tags in first-seen order
     */
    public function tags(): array
    {
        $tags = [];

        foreach ($this->operations as $operation) {
            foreach ($operation->tags as $tag) {
                if (! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }

        return $tags;
    }

    /**
     * Return a document containing only the operations accepted by every
     * filter. Returns this instance when no filters are given.
     *
     * @param array<int, ApiOperationFilter> $filters
     */
    public function filtered(array $filters): self
    {
        if ($filters === []) {
            return $this;
        }

        $filtered = new self();
        $filtered->info = $this->info;
        $filtered->servers = $this->servers;
        $filtered->securitySchemes = $this->securitySchemes;
        $filtered->security = $this->security;

        foreach ($this->operations as $operation) {
            foreach ($filters as $filter) {
                if (! $filter->matches($operation)) {
                    continue 2;
                }
            }

            $filtered->operations[] = $operation;
        }

        return $filtered;
    }
}
