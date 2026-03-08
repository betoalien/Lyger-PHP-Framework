<?php

declare(strict_types=1);

namespace Lyger\Events;

/**
 * ShouldBroadcast - Interface for events that should be broadcast
 */
interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array;

    /**
     * Get the event name
     */
    public function broadcastAs(): string;

    /**
     * Get the broadcast data
     */
    public function broadcastWith(): array;
}

/**
 * Channel - Represents a broadcast channel
 */
class Channel
{
    private string $name;
    private ?string $auth = null;

    public function __construct(string $name, ?string $auth = null)
    {
        $this->name = $name;
        $this->auth = $auth;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuth(): ?string
    {
        return $this->auth;
    }

    public function toString(): string
    {
        return $this->name;
    }
}

/**
 * PrivateChannel - Private broadcast channel
 */
class PrivateChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('private-' . $name);
    }
}

/**
 * PresenceChannel - Presence broadcast channel
 */
class PresenceChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('presence-' . $name);
    }
}

/**
 * BroadcastManager - Manages broadcasting
 */
class BroadcastManager
{
    private static array $connections = [];

    /**
     * Register a broadcast driver
     */
    public static function driver(string $name, callable $driver): void
    {
        self::$connections[$name] = $driver;
    }

    /**
     * Broadcast an event
     */
    public static function event(Event $event): BroadcastEvent
    {
        return new BroadcastEvent($event);
    }

    /**
     * Send to a channel
     */
    public static function send(array $channels, string $event, array $data = []): void
    {
        $payload = [
            'event' => $event,
            'data' => $data,
            'channels' => array_map(fn($c) => $c instanceof Channel ? $c->getName() : $c, $channels),
        ];

        // Broadcast to registered connections
        foreach (self::$connections as $name => $driver) {
            $driver($payload);
        }
    }
}

/**
 * BroadcastEvent - Event wrapper for broadcasting
 */
class BroadcastEvent
{
    private Event $event;
    private array $channels = [];
    private string $name;

    public function __construct(Event $event)
    {
        $this->event = $event;
        $this->name = $event->getName();
    }

    public function on(Channel ...$channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function send(): void
    {
        BroadcastManager::send(
            $this->channels,
            $this->name,
            $this->event->getPayload()
        );
    }
}

/**
 * FakeBroadcast - Mock for testing
 */
class FakeBroadcast
{
    private static array $events = [];

    public static function assertDispatched(string $event): bool
    {
        return in_array($event, self::$events, true);
    }

    public static function assertNotDispatched(string $event): bool
    {
        return !in_array($event, self::$events, true);
    }

    public static function record(string $event): void
    {
        self::$events[] = $event;
    }

    public static function reset(): void
    {
        self::$events = [];
    }

    public static function events(): array
    {
        return self::$events;
    }
}
