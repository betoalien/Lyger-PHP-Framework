---
layout: default
title: Jobs & Queues
nav_order: 13
---

# Jobs & Queues

---

## Overview

Lyger's job queue allows you to defer time-consuming tasks — sending emails, processing images, making external API calls — to be executed asynchronously. Jobs are persisted to `storage/queue/` and survive server restarts.

---

## Defining a Job

Create a job class extending `Lyger\Jobs\Job`:

```php
<?php

namespace App\Jobs;

use Lyger\Jobs\Job;

class SendWelcomeEmail extends Job
{
    protected string $queue = 'emails';   // Queue name (default: 'default')
    protected int $tries   = 3;           // Max attempts (default: 3)
    protected int $timeout = 60;          // Seconds before timeout (default: 60)
    protected int $backoff = 5;           // Seconds between retries (default: 5)

    public function handle(): void
    {
        $data = $this->getData();

        // Send email
        mail(
            $data['email'],
            'Welcome to Lyger!',
            "Hi {$data['name']}, thanks for joining."
        );
    }

    public function failed(\Throwable $e): void
    {
        // Optional: log or notify on permanent failure
        error_log("SendWelcomeEmail failed for {$this->getData()['email']}: " . $e->getMessage());
    }
}
```

---

## Dispatching Jobs

### Using the Dispatchable Trait

Add the `Dispatchable` trait to your job for an expressive dispatch syntax:

```php
use Lyger\Jobs\Dispatchable;

class SendWelcomeEmail extends Job
{
    use Dispatchable;
    // ...
}

// Dispatch immediately
$jobId = SendWelcomeEmail::dispatch(['email' => 'alice@test.com', 'name' => 'Alice']);

// Dispatch with delay (seconds)
$jobId = SendWelcomeEmail::dispatchAfter(30, ['email' => 'alice@test.com']);
```

### Using the Queue Directly

```php
use Lyger\Jobs\Queue;

$queue = Queue::getInstance();

// Push immediately
$jobId = $queue->push(new SendWelcomeEmail(['email' => 'alice@test.com']));

// Push with delay
$jobId = $queue->later(60, new ProcessReport(['report_id' => 42]));
```

---

## Processing Jobs

Run the queue worker (processes jobs indefinitely):

```bash
php rawr queue:work
# or use the Queue class directly:
```

```php
$queue = Queue::getInstance();
$queue->work('emails');    // Process the 'emails' queue
$queue->work();            // Process the 'default' queue
```

The worker runs an infinite loop:
1. Pops the next job from the queue
2. Executes `handle()`
3. On success: deletes the job file
4. On failure: retries up to `$tries` times with `$backoff` delay
5. On permanent failure: calls `failed()` and marks job as failed

---

## Queue Inspection

```php
$queue = Queue::getInstance();

// Number of pending jobs
$pending = $queue->size('emails');
$pending = $queue->size();           // Default queue

// Failed jobs
$failed = $queue->getFailed();

// Retry a failed job
$queue->retry($jobId, maxAttempts: 5);

// Delete a job
$queue->delete($jobId);
```

---

## Job Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$queue` | `string` | `'default'` | Queue name this job belongs to |
| `$tries` | `int` | `3` | Maximum number of attempts |
| `$timeout` | `int` | `60` | Seconds before the job is considered timed out |
| `$backoff` | `int` | `5` | Seconds to wait between retry attempts |

---

## Accessing Job Data

Inside `handle()`, call `$this->getData()` to retrieve the payload passed at dispatch:

```php
public function handle(): void
{
    $data = $this->getData();
    // $data is whatever you passed to dispatch() or the constructor
}
```

---

## Job Persistence

Jobs are stored as JSON files in `storage/queue/{queue-name}/`:

```
storage/queue/
├── default/
│   ├── job_1234567890_abc.json
│   └── job_1234567891_xyz.json
├── emails/
│   └── job_1234567892_def.json
└── failed/
    └── job_1234567800_old.json
```

Each file contains:

```json
{
  "id": "1234567890_abc",
  "class": "App\\Jobs\\SendWelcomeEmail",
  "data": {"email": "alice@test.com"},
  "queue": "emails",
  "attempts": 0,
  "created_at": 1741392000,
  "run_at": 1741392000
}
```

---

## Complete Example

```php
// 1. Define the job
class ProcessImageUpload extends Job
{
    use Dispatchable;

    protected string $queue = 'media';
    protected int $tries    = 5;
    protected int $timeout  = 120;

    public function handle(): void
    {
        $data     = $this->getData();
        $filePath = $data['path'];

        // Resize image
        // Generate thumbnails
        // Update database record
    }

    public function failed(\Throwable $e): void
    {
        error_log("Image processing failed: " . $e->getMessage());
    }
}

// 2. Dispatch from a controller
public function upload(Request $request): Response
{
    // ... save file ...
    $jobId = ProcessImageUpload::dispatch(['path' => $savedPath]);

    return Response::json(['queued' => true, 'job_id' => $jobId], 202);
}
```

---

## Method Reference

### Queue

| Method | Description |
|--------|-------------|
| `getInstance(): Queue` | Singleton instance |
| `push(Job $job): string` | Enqueue job, return job ID |
| `later(int $delay, Job $job): string` | Enqueue with delay (seconds) |
| `pop(?string $queue = null): ?array` | Pop next ready job |
| `work(?string $queue = null): void` | Run infinite processing loop |
| `process(array $job): bool` | Execute a single job |
| `retry(string $id, int $attempts): void` | Retry a failed job |
| `delete(string $id): void` | Delete a job |
| `fail(array $job, \Throwable $e): void` | Mark job as failed |
| `getFailed(): array` | Get all failed jobs |
| `size(?string $queue = null): int` | Count pending jobs |

### Dispatchable Trait

| Method | Description |
|--------|-------------|
| `dispatch(mixed ...$args): string` | Dispatch immediately |
| `dispatchAfter(int $delay, mixed ...$args): string` | Dispatch with delay |
