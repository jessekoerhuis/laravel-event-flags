# Laravel Event Flags

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](https://packagist.org/packages/jessekoerhuis/laravel-event-flags)
[![Total Downloads](https://img.shields.io/packagist/dt/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](https://packagist.org/packages/jessekoerhuis/laravel-event-flags)
[![License](https://img.shields.io/packagist/l/jessekoerhuis/laravel-event-flags.svg?style=flat-square)](LICENSE)

A tiny, expressive utility for attaching **flags** to Laravel events, so your listeners know *what to do* (or *not to do*) without polluting your event constructors with a growing list of boolean parameters.

---

## Table of Contents

- [The Problem](#introduction)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
    - [Applying Flags](#applying-flags)
    - [Implicit vs. Explicit Flags](#implicit-vs-explicit-flags)
    - [Reading Flags in a Listener](#reading-flags-in-a-listener)
    - [Dispatching With Flags](#dispatching-with-flags)
    - [Using Enums as Flags](#using-enums-as-flags)
- [Real-World Examples](#real-world-examples)
    - [1. Skipping Notifications During Data Imports](#1-skipping-notifications-during-data-imports)
    - [2. Silencing Audit Logs for System Actions](#2-silencing-audit-logs-for-system-actions)
    - [3. Forcing a Cache Warm-Up After a Model Update](#3-forcing-a-cache-warm-up-after-a-model-update)
    - [4. Selective Webhook Dispatching](#4-selective-webhook-dispatching)
    - [5. Test-Only Behavior Without Mocking Everything](#5-test-only-behavior-without-mocking-everything)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Introduction

Events in Laravel are meant to be **descriptive statements about something that happened**. `UserRegistered`, `OrderPaid`, `InvoiceGenerated` — these events should describe *what occurred*, not *what should happen next*. That's the listener's job.

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

This escalates quickly. A few weeks in, your events are half data, half configuration. Every listener has to know which combination of booleans applies to it. Adding a new flag means touching every dispatch site. The event no longer describes an occurrence — it describes an occurrence *plus a small imperative program*.

You want a way to say:

> "This event happened — and by the way, don't send emails this time."

…without turning the event's signature into a control panel.

`laravel-event-flags` gives your events a lightweight, opt-in bag of flags. Flags are simple name-based hints — optionally with a scalar value — that any listener can inspect. The event's *data* stays clean. The *directions* live in a separate, well-defined channel.

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

---

## Installation

Install via Composer:

```bash
composer require jessekoerhuis/laravel-event-flags
```

**Requirements:**

- PHP `^8.2`
- Laravel `^11.0 || ^12.0`

---

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

---

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

---

## Real-World Examples

These are the situations that motivated this package. Each one is a case where the event should stay clean, but a listener needs a nudge.

### 1. Skipping Notifications During Data Imports

You're importing 50,000 users from a legacy system. You still want the `UserRegistered` event to fire so that analytics, search indexing, and provisioning all run — but you absolutely do **not** want to email 50,000 people a welcome message.

```php
foreach ($legacyUsers as $legacyUser) {
    $user = User::create($legacyUser->toArray());

    UserRegistered::dispatchWithFlags([UserFlag::FromImport->value], $user);
}
```

```php
class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        if ($event->isFlagEnabled(UserFlag::FromImport->value)) {
            return;
        }

        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }
}
```

The event still describes what happened (a user was registered). Only the *listener that cares* is affected.

### 2. Silencing Audit Logs for System Actions

Your app records every model change in an audit log. But when the nightly cleanup job soft-deletes expired records, those aren't user actions — they're system actions, and logging them just adds noise.

```php
$expiredSubscriptions->each(function (Subscription $subscription): void {
    $subscription->delete();

    SubscriptionExpired::dispatchWithFlags(['system-action'], $subscription);
});
```

```php
class RecordSubscriptionExpiredAudit
{
    public function handle(SubscriptionExpired $event): void
    {
        if ($event->isFlagEnabled('system-action')) {
            return;
        }

        AuditLog::record($event->subscription, 'expired');
    }
}
```

### 3. Forcing a Cache Warm-Up After a Model Update

Normally, `ProductUpdated` invalidates a cache and lets it lazily rebuild on the next request. But when a merchandiser triggers a "publish" action from the admin, you want the cache warm *now* so the storefront doesn't hiccup.

```php
$product->publish();

ProductUpdated::dispatchWithFlags(['warm-cache'], $product);
```

```php
class WarmProductCache
{
    public function handle(ProductUpdated $event): void
    {
        if (! $event->isFlagEnabled('warm-cache')) {
            return;
        }

        $this->cache->warm($event->product);
    }
}
```

The listener's default is "do nothing" — it only acts when a flag opts it in. Perfect for expensive, situational behavior.

### 4. Selective Webhook Dispatching

You have multiple webhook subscribers reacting to `InvoicePaid`. When a payment is retried and finally succeeds, you want to dispatch webhooks to the customer's endpoint but **not** to internal Slack notifiers (which already reported the retry).

```php
InvoicePaid::dispatchWithFlags(
    ['skip-slack-webhook', 'was-retried', 'attempt' => 4],
    $invoice,
);
```

```php
class SendSlackNotification
{
    public function handle(InvoicePaid $event): void
    {
        if ($event->isFlagEnabled('skip-slack-webhook')) {
            return;
        }

        $this->slack->post($event->invoice);
    }
}
```

Each listener makes its own decision based on flags relevant to it. No listener needs to understand the full set.

### 5. Test-Only Behavior Without Mocking Everything

In feature tests you often want the event to fire and *most* listeners to run — but skip the one that calls a third-party API. Instead of mocking the API client or faking the event entirely, flag it:

```php
it('records the payment and updates the ledger', function (): void {
    $invoice = Invoice::factory()->create();

    InvoicePaid::dispatchWithFlags(['skip-external-api'], $invoice);

    expect(Ledger::latest()->first())->invoice_id->toBe($invoice->id);
});
```

```php
class NotifyPaymentProvider
{
    public function handle(InvoicePaid $event): void
    {
        if ($event->isFlagEnabled('skip-external-api')) {
            return;
        }

        $this->paymentProvider->confirm($event->invoice);
    }
}
```

The production path stays untouched; the test just whispers to one listener.

---

## Best Practices

- **Prefer enums over raw strings.** Backed enums are refactor-safe, discoverable, and self-documenting. Use `->value` when passing them into `withFlags()` and reader methods.
- **Name flags as directives, not states.** `skip-welcome-email` is clearer than `imported` when the reader is a listener deciding what to do.
- **Keep flags optional.** A listener with no flag knowledge should still behave correctly by default. Flags are hints, not required inputs.
- **Don't move data into flags.** Values are restricted to scalars for a reason — if a listener needs a customer ID or a rich object, put it on the event. Flags are for *directions*, not payload.
- **Document expected flags near the event.** A short PHPDoc listing recognized flags saves future-you a search.
- **Never chain flags after `dispatch()`.** `SomeEvent::dispatch(...)` fires the event before flags can be attached. Use `event((new SomeEvent(...))->withFlags(...))` or `SomeEvent::dispatchWithFlags([...], ...)` instead.

---

## API Reference

All methods are provided by the `JesseKoerhuis\EventFlags\Traits\HasFlags` trait.

| Method | Description |
| --- | --- |
| `withFlags(array\|string $flags, string ...$additionalFlags): static` | Returns a clone of the event with the given flags merged in. Accepts a single string, multiple string variadics, or an array of mixed implicit/explicit entries. |
| `getFlags(): array<string, bool\|int\|float\|string\|null>` | Returns the full flag map. |
| `getFlag(string $flag, mixed $default = null): mixed` | Returns the value stored for `$flag`, or `$default` if the flag is absent. |
| `isFlagEnabled(string $flag): bool` | Returns `true` when the flag is present and its value is truthy. |
| `flagEquals(string $flag, bool\|int\|float\|string\|null $value): bool` | Returns `true` when the flag is present and its value strictly equals (`===`) the given value. |
| `static dispatchWithFlags(array $flags, mixed ...$arguments): ?array` | Constructs the event with `$arguments`, applies `$flags`, dispatches through `event()`, and returns the dispatcher's response. Requires the event to use Laravel's `Dispatchable` trait. |

Attempting to store a non-scalar (and non-null) value for an explicit flag, or using a non-string/non-int implicit flag name, throws `JesseKoerhuis\EventFlags\Exceptions\InvalidFlagException`.

---

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis and linting:

```bash
composer lint
```

---

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
