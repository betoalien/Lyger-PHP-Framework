<?php

declare(strict_types=1);

namespace Lyger\Events;

/**
 * Event - Base class for events
 */
abstract class Event
{
    /**
     * Get the event name
     */
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get the event payload
     */
    public function getPayload(): array
    {
        return (array) $this;
    }
}

/**
 * EventDispatcher - Dispatch and listen to events
 */
class EventDispatcher
{
    private static array $listeners = [];
    private static array $wildcardListeners = [];

    /**
     * Register an event listener
     */
    public static function listen(string $event, callable $listener): void
    {
        if (str_contains($event, '*')) {
            self::$wildcardListeners[$event][] = $listener;
        } else {
            self::$listeners[$event][] = $listener;
        }
    }

    /**
     * Dispatch an event
     */
    public static function dispatch(Event $event, array $payload = []): array
    {
        $eventName = $event->getName();
        $results = [];

        // Dispatch to exact listeners
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                $results[] = $listener($event, $payload);
            }
        }

        // Dispatch to wildcard listeners
        foreach (self::$wildcardListeners as $pattern => $listeners) {
            if (self::matchesPattern($eventName, $pattern)) {
                foreach ($listeners as $listener) {
                    $results[] = $listener($event, $payload);
                }
            }
        }

        return $results;
    }

    /**
     * Check if event matches wildcard pattern
     */
    private static function matchesPattern(string $event, string $pattern): bool
    {
        $pattern = str_replace(['/', '.'], ['\\/', '\\.'], $pattern);
        $pattern = str_replace('*', '.*', $pattern);
        return (bool) preg_match("/^{$pattern}$/", $event);
    }

    /**
     * Check if event has listeners
     */
    public static function hasListeners(string $event): bool
    {
        return !empty(self::$listeners[$event]) || !empty(self::$wildcardListeners);
    }

    /**
     * Get listener count for event
     */
    public static function getListenerCount(string $event): int
    {
        $count = count(self::$listeners[$event] ?? []);

        foreach (self::$wildcardListeners as $pattern => $listeners) {
            if (self::matchesPattern($event, $pattern)) {
                $count += count($listeners);
            }
        }

        return $count;
    }

    /**
     * Clear all listeners
     */
    public static function clear(): void
    {
        self::$listeners = [];
        self::$wildcardListeners = [];
    }

    /**
     * Clear listeners for specific event
     */
    public static function clearEvent(string $event): void
    {
        unset(self::$listeners[$event]);
    }
}

/**
 * Broadcaster - Broadcast events to various channels
 */
class Broadcaster
{
    private static array $channels = [];

    /**
     * Register a channel
     */
    public static function channel(string $name, callable $callback): void
    {
        self::$channels[$name] = $callback;
    }

    /**
     * Broadcast to a channel
     */
    public static function broadcast(string $channel, Event $event): void
    {
        foreach (self::$channels as $name => $callback) {
            if (self::matchesChannel($channel, $name)) {
                $callback($event);
            }
        }
    }

    /**
     * Check if channel matches
     */
    private static function matchesChannel(string $channel, string $pattern): bool
    {
        $pattern = str_replace(['*', '{?}'], ['.*', '[^.]+'], $pattern);
        return (bool) preg_match("/^{$pattern}$/", $channel);
    }
}

/**
 * EventServiceProvider - Register events
 */
abstract class EventServiceProvider
{
    /**
     * Register events and listeners
     */
    abstract public function register(): void;

    /**
     * Register event listeners
     */
    protected function listen(string $event, callable $listener): void
    {
        EventDispatcher::listen($event, $listener);
    }

    /**
     * Subscribe to events
     */
    protected function subscribe(string $subscriber): void
    {
        // Subscribe to multiple events
        if (method_exists($subscriber, 'subscribe')) {
            $subscriber->subscribe(EventDispatcher::class);
        }
    }
}
