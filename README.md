# Laravel Event Flags

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](https://packagist.org/packages/jessekoerhuis/laravel-event-flags)
[![Total Downloads](https://img.shields.io/packagist/dt/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](https://packagist.org/packages/jessekoerhuis/laravel-event-flags)
[![License](https://img.shields.io/packagist/l/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](LICENSE)

![Laravel Event Flags](laravel-event-flags-banner.webp)

A tiny, expressive utility package for attaching **flags** to Laravel events, so your listeners know *what to do* (or *not to do*) without polluting your event constructors with a growing list of boolean parameters.

## Table of Contents

- [The Problem](#introduction)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
    - [Applying Flags](#applying-flags)
    - [Implicit vs. Explicit Flags](#implicit-vs-explicit-flags)
    - [Reading Flags in a Listener](#reading-flags-in-a-listener)
    - [Dispatching With Flags](#dispatching-with-flags)
    - [Restricting Allowed Flags](#restricting-allowed-flags)
    - [Using Enums as Flags](#using-enums-as-flags)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Introduction

Events in Laravel are meant to be **descriptive statements about something that happened**. Cases like `UserRegistered`, `OrderPaid`, `InvoiceGenerated` should describe *what occurred*, not *what should happen next*. That's the listener's job.

But sooner or later, a pragmatic need creeps in. You want to dispatch the same event, but tell one specific listener to skip its work. Or run it in "silent mode". Or force it to re-run even when it usually wouldn't. The path of least resistance is to add a flag to the event constructor:

```php
class UserRegistered
{
    public function __construct(
        public readonly User $user,
        public readonly bool $sendWelcomeEmail = true,
        public readonly bool $skipAuditLog = false,
        public readonly bool $forceCacheWarmup = false,
        public readonly bool $silent = false,
        public readonly bool $fromImport = false,
    ) {}
}
```

The `laravel-event-flags` package gives your events a lightweight, opt-in bag of flags. Flags are simple name-based hints — optionally with a scalar value — that any listener can inspect. The event's *data* stays clean. The *directions* live in a separate, well-defined channel.

```php
class UserRegistered
{
    use Dispatchable;
    use HasFlags;

    public function __construct(public readonly User $user) {}
}

event((new UserRegistered($user))->withFlags('skip-welcome-email'));
```

In a listener:

```php
public function handle(UserRegistered $event): void
{
    if ($event->isFlagEnabled('skip-welcome-email')) {
        return;
    }

    Mail::to($event->user)->send(new WelcomeMail($event->user));
}
```

That's the whole idea. Below is everything you need to use it well.

## Installation

Install via Composer:

```bash
composer require jessekoerhuis/laravel-event-flags
```

**Requirements:**

- PHP `^8.2`
- Laravel `^11.0 || ^12.0 || ^13.0`

## Quick Start

Add the `HasFlags` trait to any event you want to flag:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JesseKoerhuis\EventFlags\Traits\HasFlags;

class OrderPlaced
{
    use Dispatchable;
    use SerializesModels;
    use HasFlags;

    public function __construct(public readonly Order $order) {}
}
```

Dispatch the event with one or more flags:

```php
event((new OrderPlaced($order))->withFlags('skip-inventory-sync'));
```

Inspect flags in your listener:

```php
public function handle(OrderPlaced $event): void
{
    if ($event->isFlagEnabled('skip-inventory-sync')) {
        return;
    }

    $this->inventory->sync($event->order);
}
```

Done.

## Usage

### Applying Flags

`withFlags()` returns a **new, cloned event instance** with the flags applied — the original event is left untouched. This means flags must be attached to the instance *before* it is passed to the dispatcher.

Attach a single flag by name:

```php
$event = (new OrderPlaced($order))->withFlags('skip-inventory-sync');
```

Attach multiple flags as variadic string arguments:

```php
$event = (new OrderPlaced($order))->withFlags('skip-inventory-sync', 'silent');
```

Or pass an array — mixing implicit (name-only) and explicit (name-value) entries:

```php
$event = (new OrderPlaced($order))->withFlags([
    'skip-inventory-sync',
    'silent',
    'retry-attempt' => 3,
    'triggered-by' => 'nightly-import-job',
]);
```

Calling `withFlags()` again merges — later values overwrite earlier ones under the same name:

```php
$event = (new OrderPlaced($order))
    ->withFlags(['level' => 1])
    ->withFlags(['level' => 2]);

$event->getFlag('level');
```

> **Note:** Because `withFlags()` clones the event, always assign the result. `$event->withFlags(...)` on its own does nothing to `$event`.

### Implicit vs. Explicit Flags

Flags come in two shapes:

- **Implicit flags** — a bare flag name (numeric-keyed array entry, string variadic, or single string argument). The name is stored with the value `true`.
- **Explicit flags** — a string key mapped to a scalar value (`bool`, `int`, `float`, `string`, or `null`). The value is preserved as-is.

```php
$event = (new OrderPlaced($order))->withFlags([
    'silent',
    'level' => 3,
    'source' => 'admin',
    'dry-run' => false,
]);

$event->getFlags();
```

Passing a non-primitive value for an explicit flag throws an `InvalidFlagException`:

```php
(new OrderPlaced($order))->withFlags(['payload' => new stdClass()]);
```

### Reading Flags in a Listener

Three methods are available for inspecting flags:

```php
public function handle(OrderPlaced $event): void
{
    if ($event->isFlagEnabled('silent')) {
        return;
    }

    if ($event->flagEquals('source', 'admin')) {
        $this->auditor->recordAdminAction($event->order);
    }

    $attempt = $event->getFlag('retry-attempt', 0);
    $source  = $event->getFlag('triggered-by', 'unknown');

    $this->notifier->notify($event->order, $attempt, $source);
}
```

- `isFlagEnabled(string $flag): bool` — returns `true` when the flag exists **and** its value is truthy. Missing flags, `false`, `0`, `''`, and `null` all return `false`.
- `flagEquals(string $flag, bool|int|float|string|null $value): bool` — returns `true` when the flag is present **and** its value is *strictly identical* (`===`) to the given value. Correctly matches falsy values such as `false`, `0`, `''`, and `null`.
- `getFlag(string $flag, mixed $default = null): mixed` — returns the stored value, or `$default` if the flag is not present.
- `getFlags(): array` — returns the full flag map, useful for debugging or bulk inspection.

Use `isFlagEnabled()` for on/off directives, `flagEquals()` when a listener cares about one specific value (e.g. `source === 'admin'`), and `getFlag()` when you need to work with the value itself.

### Dispatching With Flags

Any event that uses Laravel's `Dispatchable` trait gets a static `dispatch()` helper. However, `dispatch()` fires the event **immediately** and returns the listeners' responses — not the event instance — so `SomeEvent::dispatch(...)->withFlags(...)` **will not work**: the event has already been handled by the time you try to attach flags, and the chained call is made on the dispatcher's return value.

There are two supported patterns:

**Pattern 1 — build the event, then dispatch via the `event()` helper:**

```php
event((new OrderPlaced($order))->withFlags('skip-inventory-sync'));
```

**Pattern 2 — use the static `dispatchWithFlags()` helper provided by the trait:**

```php
OrderPlaced::dispatchWithFlags(
    ['skip-inventory-sync', 'retry-attempt' => 3],
    $order,
);
```

The first argument is the flags array; the remaining arguments are forwarded to the event constructor. `dispatchWithFlags()` returns whatever the dispatcher returns (an array of listener responses, or `null`).

### Restricting Allowed Flags

By default, an event that uses `HasFlags` accepts any flag name — the trait's `$allowedFlags` property is seeded with a single wildcard entry, `'*'`. When you want an event to accept only a known set of flags, override `$allowedFlags` on the event and list the names you consider valid:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use JesseKoerhuis\EventFlags\Traits\HasFlags;

class OrderPlaced
{
    use Dispatchable;
    use HasFlags;

    protected array $allowedFlags = [
        'skip-inventory-sync',
        'silent',
        'retry-attempt',
        'triggered-by',
    ];

    public function __construct(public readonly Order $order) {}
}
```

Because PHP forbids a class from redeclaring a trait property with a differing default, set the list in the constructor when your event doesn't already override the property:

```php
public function __construct(public readonly Order $order)
{
    $this->allowedFlags = ['skip-inventory-sync', 'silent'];
}
```

Any call to `withFlags()` — including the array path used by `dispatchWithFlags()` — verifies every incoming flag against the allow-list. Passing a flag not on the list throws a `FlagNotAllowedException`:

```php
(new OrderPlaced($order))->withFlags('unknown-flag');
```

The check applies to implicit flags, explicit flags, the single-string form, and every variadic string. Keep `'*'` in the array to preserve wildcard behaviour, or omit it to enforce the allow-list strictly.

You can also invoke the check directly — for example, from a custom builder or a manual dispatch path:

```php
$event->assertFlagAllowed('silent');
```

### Using Enums as Flags

Flag names must be strings or integers. Backed string enums work well as long as you access their `->value`:

```php
namespace App\Events\Flags;

enum OrderFlag: string
{
    case SkipInventorySync = 'skip-inventory-sync';
    case Silent = 'silent';
    case FromImport = 'from-import';
}

event((new OrderPlaced($order))->withFlags(OrderFlag::Silent->value));

if ($event->isFlagEnabled(OrderFlag::Silent->value)) {
    return;
}
```

Enums give you refactor-safety and IDE autocompletion at every call site.

## Best Practices

- **Prefer enums over raw strings.** Backed enums are refactor-safe, discoverable, and self-documenting. Use `->value` when passing them into `withFlags()` and reader methods.
- **Name flags as directives, not states.** `skip-welcome-email` is clearer than `imported` when the reader is a listener deciding what to do.
- **Keep flags optional.** A listener with no flag knowledge should still behave correctly by default. Flags are hints, not required inputs.
- **Don't move data into flags.** Values are restricted to scalars for a reason — if a listener needs a customer ID or a rich object, put it on the event. Flags are for *directions*, not payload.
- **Never chain flags after `dispatch()`.** `SomeEvent::dispatch(...)` fires the event before flags can be attached. Use `event((new SomeEvent(...))->withFlags(...))` or `SomeEvent::dispatchWithFlags([...], ...)` instead.
- **Lock down flags on public events.** Override `$allowedFlags` on events that cross package or team boundaries so unknown flag names fail loudly instead of being silently ignored by listeners.

## API Reference

All methods are provided by the `JesseKoerhuis\EventFlags\Traits\HasFlags` trait.

| Method                                                                  | Description                                                                                                                                                                              |
|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `withFlags(array\|string $flags, string ...$additionalFlags): static`   | Returns a clone of the event with the given flags merged in. Accepts a single string, multiple string variadics, or an array of mixed implicit/explicit entries.                         |
| `getFlags(): array<string, bool\|int\|float\|string\|null>`             | Returns the full flag map.                                                                                                                                                               |
| `getFlag(string $flag, mixed $default = null): mixed`                   | Returns the value stored for `$flag`, or `$default` if the flag is absent.                                                                                                               |
| `isFlagEnabled(string $flag): bool`                                     | Returns `true` when the flag is present and its value is truthy.                                                                                                                         |
| `flagEquals(string $flag, bool\|int\|float\|string\|null $value): bool` | Returns `true` when the flag is present and its value strictly equals (`===`) the given value.                                                                                           |
| `static dispatchWithFlags(array $flags, mixed ...$arguments): ?array`   | Constructs the event with `$arguments`, applies `$flags`, dispatches through `event()`, and returns the dispatcher's response. Requires the event to use Laravel's `Dispatchable` trait. |
| `assertFlagAllowed(string $flag): void`                                 | Throws `FlagNotAllowedException` when `$flag` is not in `$allowedFlags` and the list does not contain the `'*'` wildcard. Called automatically by `withFlags()` for every incoming flag. |

Attempting to store a non-scalar (and non-null) value for an explicit flag, or using a non-string/non-int implicit flag name, throws `JesseKoerhuis\EventFlags\Exceptions\InvalidFlagException`. Passing a flag name that is not covered by the event's `$allowedFlags` list throws `JesseKoerhuis\EventFlags\Exceptions\FlagNotAllowedException`.

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis and linting:

```bash
composer lint
```

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

![Laravel Event Flags Footer](laravel-event-flags-footer.webp)
