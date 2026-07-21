<?php

declare(strict_types=1);

namespace Lyger\Jobs;

/**
 * Queue - Async job processing
 */
class Queue
{
    private static ?self $instance = null;
    private array $jobs = [];
    private array $failed = [];
    private string $storagePath;

    private function __construct()
    {
        $this->storagePath = dirname(__DIR__, 2) . '/storage/queue';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function push(Job $job): string
    {
        $id = uniqid('job_', true);
        $jobData = [
            'id' => $id,
            'class' => get_class($job),
            'data' => $job->getData(),
            'queue' => $job->getQueue(),
            'tries' => $job->getTries(),
            'timeout' => $job->getTimeout(),
            'backoff' => $job->getBackoff(),
            'attempts' => 0,
            'created_at' => time(),
        ];

        $this->jobs[$id] = $jobData;
        $this->persistJob($jobData);

        return $id;
    }

    public function later(int $delay, Job $job): string
    {
        $id = uniqid('job_', true);
        $jobData = [
            'id' => $id,
            'class' => get_class($job),
            'data' => $job->getData(),
            'queue' => $job->getQueue(),
            'tries' => $job->getTries(),
            'timeout' => $job->getTimeout(),
            'backoff' => $job->getBackoff(),
            'attempts' => 0,
            'available_at' => time() + $delay,
            'created_at' => time(),
        ];

        $this->jobs[$id] = $jobData;
        $this->persistJob($jobData);

        return $id;
    }

    public function pop(?string $queue = null): ?array
    {
        $now = time();

        foreach ($this->jobs as $id => $job) {
            if ($queue !== null && $job['queue'] !== $queue) {
                continue;
            }

            if (isset($job['available_at']) && $job['available_at'] > $now) {
                continue;
            }

            return $job;
        }

        return null;
    }

    public function work(?string $queue = null): void
    {
        while (true) {
            $job = $this->pop($queue);

            if ($job === null) {
                sleep(1);
                continue;
            }

            $this->process($job);
        }
    }

    public function process(array $job): bool
    {
        $class = $job['class'];
        $data = $job['data'];

        if (!class_exists($class)) {
            $this->fail($job, new \RuntimeException("Class {$class} not found"));
            return false;
        }

        $instance = new $class($data);
        $attempts = $job['attempts'] + 1;

        try {
            $instance->handle();
            $this->delete($job['id']);
            return true;
        } catch (\Throwable $e) {
            if ($attempts >= $job['tries']) {
                $instance->failed($e);
                $this->fail($job, $e);
                return false;
            }

            $this->retry($job['id'], $attempts);
            return false;
        }
    }

    public function retry(string $id, int $attempts): void
    {
        if (isset($this->jobs[$id])) {
            $this->jobs[$id]['attempts'] = $attempts;
            if (isset($this->jobs[$id]['backoff'])) {
                $this->jobs[$id]['available_at'] = time() + ($this->jobs[$id]['backoff'] * $attempts);
            }
            $this->persistJob($this->jobs[$id]);
        }
    }

    public function delete(string $id): void
    {
        unset($this->jobs[$id]);
        $file = $this->storagePath . '/' . $id . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function fail(array $job, \Throwable $exception): void
    {
        $this->failed[$job['id']] = [
            'job' => $job,
            'exception' => $exception->getMessage(),
            'failed_at' => time(),
        ];
    }

    public function getFailed(): array
    {
        return $this->failed;
    }

    private function persistJob(array $job): void
    {
        $file = $this->storagePath . '/' . $job['id'] . '.json';
        file_put_contents($file, json_encode($job));
    }

    public function loadJobs(): void
    {
        $files = glob($this->storagePath . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->jobs[$data['id']] = $data;
            }
        }
    }

    public function size(?string $queue = null): int
    {
        if ($queue === null) {
            return count($this->jobs);
        }

        return count(array_filter($this->jobs, fn($job) => $job['queue'] === $queue));
    }
}

/**
 * Dispatchable - Trait for dispatching jobs
 */
trait Dispatchable
{
    public static function dispatch(...$args): string
    {
        $job = new static(...$args);
        return Queue::getInstance()->push($job);
    }

    public static function dispatchAfter(int $delay, ...$args): string
    {
        $job = new static(...$args);
        return Queue::getInstance()->later($delay, $job);
    }
}
