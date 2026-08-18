<?php

declare(strict_types=1);

namespace JesseKoerhuis\EventFlags\Tests\Feature\Fixtures;

use Illuminate\Foundation\Events\Dispatchable;
use JesseKoerhuis\EventFlags\Traits\HasFlags;

class DispatchableFlaggedEvent
{
    use Dispatchable;
    use HasFlags;

    public function __construct(public string $name = 'default')
    {
        //
    }
}
