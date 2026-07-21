<?php

declare(strict_types=1);

namespace Lyger\Jobs;

/**
 * Job - Base class for all jobs
 */
abstract class Job
{
    protected int $tries = 3;
    protected int $timeout = 60;
    protected int $backoff = 60;
    protected ?string $queue = 'default';
    protected ?array $data = null;

    public function handle(): void
    {
        // Override in subclass
    }

    public function failed(\Throwable $exception): void
    {
        // Override in subclass to handle failure
    }

    public function retryUntil(): int
    {
        return time() + 3600; // Default: 1 hour
    }

    public function getTries(): int
    {
        return $this->tries;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getBackoff(): int
    {
        return $this->backoff;
    }

    public function getQueueManager(): string
    {
        return $this->queue ?? 'default';
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public static function dispatch(self $job): void
    {
        QueueManagerManagerpush($job);
    }

    public static function dispatchSync(self $job): void
    {
        $job->handle();
    }

    public static function dispatchLater(self $job, int $delay): void
    {
        QueueManagerManagerlater($delay, $job);
    }
}

/**
 * QueueManagerManager - Simple in-memory queue implementation
 */
class QueueManagerManager
{
    private static array $jobs = [];
    private static array $failed = [];
    private static bool $syncMode = false;

    public static function push(Job $job): void
    {
        if (self::$syncMode) {
            $job->handle();
            return;
        }

        self::$jobs[$job->getQueueManager()][] = [
            'job' => $job,
            'attempts' => 0,
            'available_at' => time(),
        ];
    }

    public static function later(int $delay, Job $job): void
    {
        if (self::$syncMode) {
            $job->handle();
            return;
        }

        self::$jobs[$job->getQueueManager()][] = [
            'job' => $job,
            'attempts' => 0,
            'available_at' => time() + $delay,
        ];
    }

    public static function work(string $queue = 'default'): void
    {
        if (!isset(self::$jobs[$queue]) || empty(self::$jobs[$queue])) {
            return;
        }

        foreach (self::$jobs[$queue] as $index => $item) {
            if ($item['available_at'] > time()) {
                continue;
            }

            $job = $item['job'];
            $attempts = $item['attempts'];

            try {
                $job->handle();
                unset(self::$jobs[$queue][$index]);
            } catch (\Throwable $e) {
                $attempts++;

                if ($attempts >= $job->getTries()) {
                    $job->failed($e);
                    self::$failed[] = [
                        'job' => $job,
                        'exception' => $e,
                        'failed_at' => time(),
                    ];
                    unset(self::$jobs[$queue][$index]);
                } else {
                    self::$jobs[$queue][$index]['attempts'] = $attempts;
                    self::$jobs[$queue][$index]['available_at'] = time() + $job->getBackoff();
                }
            }
        }

        self::$jobs[$queue] = array_values(self::$jobs[$queue]);
    }

    public static function pop(?string $queue = null): ?Job
    {
        $queue = $queue ?? 'default';

        if (!isset(self::$jobs[$queue]) || empty(self::$jobs[$queue])) {
            return null;
        }

        $item = array_shift(self::$jobs[$queue]);
        return $item['job'];
    }

    public static function size(string $queue = 'default'): int
    {
        return count(self::$jobs[$queue] ?? []);
    }

    public static function failed(): array
    {
        return self::$failed;
    }

    public static function flushFailed(): void
    {
        self::$failed = [];
    }

    public static function setSyncMode(bool $sync = true): void
    {
        self::$syncMode = $sync;
    }

    public static function isSyncMode(): bool
    {
        return self::$syncMode;
    }
}

/**
 * Dispatcher - Job dispatcher
 */
class Dispatcher
{
    public function dispatch(Job $job): void
    {
        QueueManagerManagerpush($job);
    }

    public function dispatchSync(Job $job): void
    {
        QueueManagerManagersetSyncMode(true);
        $job->handle();
        QueueManagerManagersetSyncMode(false);
    }

    public function dispatchLater(Job $job, int $delay): void
    {
        QueueManagerManagerlater($delay, $job);
    }

    public function chain(array $jobs): Chain
    {
        return new Chain($jobs);
    }
}

/**
 * Chain - Chain multiple jobs together
 */
class Chain
{
    private array $jobs;

    public function __construct(array $jobs)
    {
        $this->jobs = $jobs;
    }

    public function dispatch(): void
    {
        foreach ($this->jobs as $job) {
            $job->handle();
        }
    }
}

/**
 * ScheduledTask - Simple scheduler for recurring tasks
 */
class Scheduler
{
    private static array $tasks = [];

    public static function schedule(string $expression, callable $task): void
    {
        self::$tasks[] = [
            'expression' => $expression,
            'task' => $task,
            'last_run' => 0,
        ];
    }

    public static function runPending(): void
    {
        $now = time();

        foreach (self::$tasks as $index => $task) {
            if (self::shouldRun($task['expression'], $task['last_run'])) {
                try {
                    ($task['task'])();
                    self::$tasks[$index]['last_run'] = $now;
                } catch (\Throwable $e) {
                    // Log error but continue
                }
            }
        }
    }

    private static function shouldRun(string $expression, int $lastRun): bool
    {
        $now = time();

        // Simple cron-like expressions
        // minute, hour, day, month, weekday
        $parts = explode(' ', $expression);

        if (count($parts) < 5) {
            return false;
        }

        $minute = (int) date('i');
        $hour = (int) date('H');
        $day = (int) date('d');
        $month = (int) date('m');
        $weekday = (int) date('w');

        return self::matches($parts[0], $minute)
            && self::matches($parts[1], $hour)
            && self::matches($parts[2], $day)
            && self::matches($parts[3], $month)
            && self::matches($parts[4], $weekday);
    }

    private static function matches(string $pattern, int $value): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_contains($pattern, '/')) {
            [$range, $step] = explode('/', $pattern);
            $step = (int) $step;
            return $value % $step === 0;
        }

        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern);
            return $value >= (int) $start && $value <= (int) $end;
        }

        if (str_contains($pattern, ',')) {
            $values = array_map('intval', explode(',', $pattern));
            return in_array($value, $values, true);
        }

        return (int) $pattern === $value;
    }

    public static function everyMinute(callable $task): void
    {
        self::schedule('* * * * *', $task);
    }

    public static function everyFiveMinutes(callable $task): void
    {
        self::schedule('*/5 * * * *', $task);
    }

    public static function hourly(callable $task): void
    {
        self::schedule('0 * * * *', $task);
    }

    public static function daily(callable $task): void
    {
        self::schedule('0 0 * * *', $task);
    }

    public static function weekly(callable $task): void
    {
        self::schedule('0 0 * * 0', $task);
    }

    public static function monthly(callable $task): void
    {
        self::schedule('0 0 1 * *', $task);
    }
}
