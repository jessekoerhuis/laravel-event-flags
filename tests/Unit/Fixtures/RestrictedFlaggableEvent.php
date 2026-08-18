<?php

declare(strict_types=1);

namespace JesseKoerhuis\EventFlags\Tests\Unit\Fixtures;

use JesseKoerhuis\EventFlags\Traits\HasFlags;

class RestrictedFlaggableEvent
{
    use HasFlags;

    public function __construct(public string $name = 'default')
    {
        $this->allowedFlags = ['feature', 'level'];
    }
}
