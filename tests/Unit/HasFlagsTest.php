<?php

declare(strict_types=1);

use JesseKoerhuis\EventFlags\Exceptions\FlagNotAllowedException;
use JesseKoerhuis\EventFlags\Exceptions\InvalidFlagException;
use JesseKoerhuis\EventFlags\Tests\Unit\Fixtures\FlaggableEvent;
use JesseKoerhuis\EventFlags\Tests\Unit\Fixtures\RestrictedFlaggableEvent;

it('should return an empty array of flags by default', function (): void {
    $event = new FlaggableEvent();

    expect($event->getFlags())->toBe([]);
});

it('should return a new instance when applying flags', function (): void {
    $event = new FlaggableEvent();
    $flagged = $event->withFlags(['feature']);

    expect($flagged)->not->toBe($event)
        ->and($event->getFlags())->toBe([]);
});

it('should normalize implicit flags with numeric keys to true', function (): void {
    $event = (new FlaggableEvent())->withFlags(['feature', 'beta']);

    expect($event->getFlags())->toBe([
        'feature' => true,
        'beta' => true,
    ]);
});

it('should cast integer implicit flag names to strings', function (): void {
    $event = (new FlaggableEvent())->withFlags([42]);

    expect($event->getFlags())->toBe(['42' => true]);
});

it('should preserve explicit flag values with string keys', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'bool' => true,
        'int' => 5,
        'float' => 1.5,
        'string' => 'value',
        'null' => null,
    ]);

    expect($event->getFlags())->toBe([
        'bool' => true,
        'int' => 5,
        'float' => 1.5,
        'string' => 'value',
        'null' => null,
    ]);
});

it('should merge implicit and explicit flags together', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'feature',
        'level' => 3,
    ]);

    expect($event->getFlags())->toBe([
        'feature' => true,
        'level' => 3,
    ]);
});

it('should overwrite previously applied flags with the same name', function (): void {
    $event = (new FlaggableEvent())
        ->withFlags(['level' => 1])
        ->withFlags(['level' => 2]);

    expect($event->getFlag('level'))->toBe(2);
});

it('should throw InvalidFlagException when an explicit flag receives a non-primitive value', function (): void {
    (new FlaggableEvent())->withFlags(['payload' => new stdClass()]);
})->throws(InvalidFlagException::class, "Flag 'payload' must contain a primitive value. stdClass given.");

it('should throw InvalidFlagException when an explicit flag receives an array value', function (): void {
    (new FlaggableEvent())->withFlags(['payload' => ['nested']]);
})->throws(InvalidFlagException::class, "Flag 'payload' must contain a primitive value. array given.");

it('should throw InvalidFlagException when an implicit flag name is not a string or integer', function (): void {
    (new FlaggableEvent())->withFlags([true]);
})->throws(
    InvalidFlagException::class,
    'Numeric flag entries must contain a string or integer flag name, bool given.',
);

it('should report a flag as enabled when its value is truthy', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'implicit',
        'explicit' => true,
        'numeric' => 1,
        'string' => 'yes',
    ]);

    expect($event->isFlagEnabled('implicit'))->toBeTrue()
        ->and($event->isFlagEnabled('explicit'))->toBeTrue()
        ->and($event->isFlagEnabled('numeric'))->toBeTrue()
        ->and($event->isFlagEnabled('string'))->toBeTrue();
});

it('should report a flag as disabled when its value is falsy or missing', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'off' => false,
        'zero' => 0,
        'empty' => '',
        'null' => null,
    ]);

    expect($event->isFlagEnabled('off'))->toBeFalse()
        ->and($event->isFlagEnabled('zero'))->toBeFalse()
        ->and($event->isFlagEnabled('empty'))->toBeFalse()
        ->and($event->isFlagEnabled('null'))->toBeFalse()
        ->and($event->isFlagEnabled('missing'))->toBeFalse();
});

it('should return a flag value when present', function (): void {
    $event = (new FlaggableEvent())->withFlags(['level' => 5]);

    expect($event->getFlag('level'))->toBe(5);
});

it('should return the default value when a flag is missing', function (): void {
    $event = new FlaggableEvent();

    expect($event->getFlag('missing'))->toBeNull()
        ->and($event->getFlag('missing', 'fallback'))->toBe('fallback');
});

it('should accept a single string flag name', function (): void {
    $event = (new FlaggableEvent())->withFlags('feature');

    expect($event->getFlags())->toBe(['feature' => true]);
});

it('should accept multiple string flag names as variadic arguments', function (): void {
    $event = (new FlaggableEvent())->withFlags('feature', 'beta', 'experimental');

    expect($event->getFlags())->toBe([
        'feature' => true,
        'beta' => true,
        'experimental' => true,
    ]);
});

it('should report a flag as equal when its value strictly matches the given value', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'implicit',
        'level' => 5,
        'ratio' => 1.5,
        'source' => 'admin',
        'enabled' => true,
    ]);

    expect($event->flagEquals('implicit', true))->toBeTrue()
        ->and($event->flagEquals('level', 5))->toBeTrue()
        ->and($event->flagEquals('ratio', 1.5))->toBeTrue()
        ->and($event->flagEquals('source', 'admin'))->toBeTrue()
        ->and($event->flagEquals('enabled', true))->toBeTrue();
});

it('should report a flag as equal when its value is a falsy scalar or null', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'off' => false,
        'zero' => 0,
        'zero-float' => 0.0,
        'empty' => '',
        'null' => null,
    ]);

    expect($event->flagEquals('off', false))->toBeTrue()
        ->and($event->flagEquals('zero', 0))->toBeTrue()
        ->and($event->flagEquals('zero-float', 0.0))->toBeTrue()
        ->and($event->flagEquals('empty', ''))->toBeTrue()
        ->and($event->flagEquals('null', null))->toBeTrue();
});

it('should not report a flag as equal when comparing loosely equivalent values', function (): void {
    $event = (new FlaggableEvent())->withFlags([
        'level' => 5,
        'source' => 'admin',
    ]);

    expect($event->flagEquals('level', '5'))->toBeFalse()
        ->and($event->flagEquals('level', 5.0))->toBeFalse()
        ->and($event->flagEquals('source', 'ADMIN'))->toBeFalse();
});

it('should not report a missing flag as equal to any value', function (): void {
    $event = new FlaggableEvent();

    expect($event->flagEquals('missing', null))->toBeFalse()
        ->and($event->flagEquals('missing', false))->toBeFalse()
        ->and($event->flagEquals('missing', 0))->toBeFalse()
        ->and($event->flagEquals('missing', ''))->toBeFalse();
});

it('should allow flags listed allowed', function (): void {
    $event = (new RestrictedFlaggableEvent())->withFlags(['feature', 'level' => 3]);

    expect($event->getFlags())->toBe([
        'feature' => true,
        'level' => 3,
    ]);
});

it('should throw FlagNotAllowedException when an implicit flag is not allowed', function (): void {
    (new RestrictedFlaggableEvent())->withFlags(['forbidden']);
})->throws(
    FlagNotAllowedException::class,
    "Flag 'forbidden' is not allowed on " . RestrictedFlaggableEvent::class . '.',
);

it('should throw FlagNotAllowedException when an explicit flag is not allowed', function (): void {
    (new RestrictedFlaggableEvent())->withFlags(['forbidden' => true]);
})->throws(
    FlagNotAllowedException::class,
    "Flag 'forbidden' is not allowed on " . RestrictedFlaggableEvent::class . '.',
);

it('should throw FlagNotAllowedException when a single string flag is not allowed', function (): void {
    (new RestrictedFlaggableEvent())->withFlags('forbidden');
})->throws(
    FlagNotAllowedException::class,
    "Flag 'forbidden' is not allowed on " . RestrictedFlaggableEvent::class . '.',
);

it('should throw FlagNotAllowedException when a variadic string flag is not allowed', function (): void {
    (new RestrictedFlaggableEvent())->withFlags('feature', 'forbidden');
})->throws(
    FlagNotAllowedException::class,
    "Flag 'forbidden' is not allowed on " . RestrictedFlaggableEvent::class . '.',
);

it('should allow any flag when the allow list contains the wildcard', function (): void {
    $event = (new FlaggableEvent())->withFlags(['anything', 'else' => 'value']);

    expect($event->getFlags())->toBe([
        'anything' => true,
        'else' => 'value',
    ]);
});
