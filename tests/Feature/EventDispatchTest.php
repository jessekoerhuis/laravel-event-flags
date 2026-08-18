<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use JesseKoerhuis\EventFlags\Tests\Feature\Fixtures\DispatchableFlaggedEvent;
use JesseKoerhuis\EventFlags\Tests\TestCase;

uses(TestCase::class);

it('should dispatch a flagged event through the laravel event dispatcher and capture it in a listener', function (): void {
    $captured = null;

    Event::listen(DispatchableFlaggedEvent::class, function (DispatchableFlaggedEvent $event) use (&$captured): void {
        $captured = $event;
    });

    DispatchableFlaggedEvent::dispatchWithFlags(['feature', 'level' => 3], 'order.created');

    expect($captured)->toBeInstanceOf(DispatchableFlaggedEvent::class)
        ->and($captured?->name)->toBe('order.created')
        ->and($captured?->getFlags())->toBe([
            'feature' => true,
            'level' => 3,
        ])
        ->and($captured?->isFlagEnabled('feature'))->toBeTrue()
        ->and($captured?->getFlag('level'))->toBe(3);
});

it('should dispatch a flagged event through the event helper function and capture it in a listener', function (): void {
    $captured = null;

    Event::listen(DispatchableFlaggedEvent::class, function (DispatchableFlaggedEvent $event) use (&$captured): void {
        $captured = $event;
    });

    event((new DispatchableFlaggedEvent('order.shipped'))
        ->withFlags(['feature', 'level' => 3]));

    expect($captured)->toBeInstanceOf(DispatchableFlaggedEvent::class)
        ->and($captured?->name)->toBe('order.shipped')
        ->and($captured?->getFlags())->toBe([
            'feature' => true,
            'level' => 3,
        ])
        ->and($captured?->isFlagEnabled('feature'))->toBeTrue()
        ->and($captured?->getFlag('level'))->toBe(3);
});

it('should resolve the event dispatcher from the laravel service container', function (): void {
    expect(app('events'))->toBeInstanceOf(Dispatcher::class);
});
