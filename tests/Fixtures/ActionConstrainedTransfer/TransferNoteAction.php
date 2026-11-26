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

namespace HyperfTest\Fixtures\ActionConstrainedTransfer;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PatchMapping;
use HyperfTest\Fixtures\Request\TransferNoteRequest;

/**
 * Hyphenated constrained placeholder with a mixed route/body form request:
 * the route field must not leak into the documented body.
 */
#[Controller]
class TransferNoteAction
{
    #[PatchMapping('/v1/transfers/{transfer-id:[0-9]+}')]
    public function handle(TransferNoteRequest $request): string
    {
        return 'ok';
    }
}
