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

namespace HyperfTest\Fixtures\ActionConstrained;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * UUID constraint fixture: the quantifier braces are what used to leak
 * phantom parameters ("4", "12") into the document.
 */
#[Controller]
class UuidSavingsDetailAction
{
    #[GetMapping('/v1/savings/{id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}')]
    public function handle(string $id): string
    {
        return 'ok';
    }
}
