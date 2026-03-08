---
layout: default
title: Events
nav_order: 12
---

# Events

---

## Overview

Lyger's event system allows decoupled communication between components. Events are dispatched globally and any number of listeners can respond to them. Wildcard patterns allow listening to multiple events with a single registration.

---

## Defining Events

Create an event class extending `Lyger\Events\Event`:

```php
<?php

namespace App\Events;

use Lyger\Events\Event;

class UserRegistered extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email
    ) {}

    // Optional: expose payload for logging/broadcasting
    public function getPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'email'   => $this->email,
        ];
    }
}
```

---

## Registering Listeners

Use `EventDispatcher::listen()` to register a listener:

```php
use Lyger\Events\EventDispatcher;
use App\Events\UserRegistered;

// Listen to a specific event (by class name short name)
EventDispatcher::listen('UserRegistered', function (UserRegistered $event) {
    // Send welcome email
    mail($event->email, 'Welcome!', 'Thanks for joining.');
});

// Multiple listeners for the same event
EventDispatcher::listen('UserRegistered', function (UserRegistered $event) {
    // Log the registration
    error_log("New user: {$event->email}");
});
```

---

## Dispatching Events

```php
use Lyger\Events\EventDispatcher;
use App\Events\UserRegistered;

$event = new UserRegistered(userId: $user->id, email: $user->email);
$results = EventDispatcher::dispatch($event);

// $results is an array of all listener return values
```

---

## Wildcard Listeners

Listen to multiple events using `*` wildcards:

```php
// Listen to all events
EventDispatcher::listen('*', function ($event) {
    error_log('Event fired: ' . get_class($event));
});

// Listen to all User* events
EventDispatcher::listen('User*', function ($event) {
    // UserRegistered, UserDeleted, UserUpdated, etc.
});

// Listen to events matching a pattern
EventDispatcher::listen('Order*', function ($event) {
    // OrderPlaced, OrderShipped, OrderCancelled
});
```

---

## Event Payload

You can also dispatch an event with an additional payload array:

```php
EventDispatcher::dispatch($event, ['source' => 'api', 'ip' => '1.2.3.4']);
```

---

## Checking Listeners

```php
// Does this event have any listeners?
EventDispatcher::hasListeners('UserRegistered');  // bool

// How many listeners?
EventDispatcher::getListenerCount('UserRegistered');  // int
```

---

## Clearing Listeners

```php
// Clear listeners for a specific event
EventDispatcher::clearEvent('UserRegistered');

// Clear all listeners
EventDispatcher::clear();
```

---

## Event Service Provider

For organized event registration, create a service provider:

```php
<?php

namespace App\Providers;

use Lyger\Events\EventServiceProvider as BaseProvider;
use Lyger\Events\EventDispatcher;
use App\Events\UserRegistered;
use App\Events\OrderPlaced;

class EventServiceProvider extends BaseProvider
{
    public function register(): void
    {
        EventDispatcher::listen('UserRegistered', function (UserRegistered $e) {
            // Welcome email
        });

        EventDispatcher::listen('OrderPlaced', function (OrderPlaced $e) {
            // Notify fulfillment
        });

        EventDispatcher::listen('User*', function ($e) {
            // Audit log for all user events
        });
    }
}
```

Boot it in `public/index.php`:

```php
$provider = new \App\Providers\EventServiceProvider();
$provider->register();
```

---

## Broadcaster

The `Broadcaster` allows organizing listeners into named channels:

```php
use Lyger\Events\Broadcaster;
use App\Events\OrderPlaced;

$broadcaster = new Broadcaster();

// Register a channel with a callback
$broadcaster->channel('orders', function (OrderPlaced $event) {
    // Handle order event on this channel
});

// Broadcast an event to a channel
$broadcaster->broadcast('orders', new OrderPlaced($orderId));
```

---

## Complete Example

```php
// 1. Define events
class UserRegistered extends Event
{
    public function __construct(public int $userId, public string $email) {}
}

class EmailVerified extends Event
{
    public function __construct(public int $userId) {}
}

// 2. Register listeners (in a service provider or bootstrap)
EventDispatcher::listen('UserRegistered', function (UserRegistered $e) {
    // Queue a welcome email
    SendWelcomeEmail::dispatch($e->email);
});

EventDispatcher::listen('EmailVerified', function (EmailVerified $e) {
    $user = User::find($e->userId);
    $user->fill(['verified' => true])->save();
});

// Global audit logger
EventDispatcher::listen('*', function ($event) {
    $name    = $event->getName();
    $payload = json_encode($event->getPayload());
    error_log("[Event] {$name}: {$payload}");
});

// 3. Dispatch in controller
public function register(Request $request): Response
{
    $user = User::create($request->all());
    EventDispatcher::dispatch(new UserRegistered($user->id, $user->email));
    return Response::json($user->toArray(), 201);
}
```

---

## Method Reference

### EventDispatcher

| Method | Description |
|--------|-------------|
| `listen(string $event, callable $listener): void` | Register listener (supports wildcards) |
| `dispatch(Event $event, array $payload = []): array` | Fire event, return listener results |
| `hasListeners(string $event): bool` | Check if event has listeners |
| `getListenerCount(string $event): int` | Count listeners for event |
| `clearEvent(string $event): void` | Remove listeners for an event |
| `clear(): void` | Remove all listeners |

### Event (base class)

| Method | Description |
|--------|-------------|
| `getName(): string` | Returns the short class name |
| `getPayload(): array` | Override to expose event data |
