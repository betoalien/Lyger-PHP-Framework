<?php

declare(strict_types=1);

namespace Lyger\Testing;

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Routing\Router;
use Lyger\Container\Container;

/**
 * TestCase - Base test class for all tests
 */
abstract class TestCase
{
    protected Container $container;
    protected Router $router;

    public function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        assert($condition === true, $message ?: 'Expected true but got false');
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        assert($condition === false, $message ?: 'Expected false but got true');
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        assert($expected == $actual, $message ?: "Expected {$expected} but got {$actual}");
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        assert($expected === $actual, $message ?: "Expected {$expected} but got {$actual}");
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        assert($value === null, $message ?: 'Expected null but got a value');
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        assert($value !== null, $message ?: 'Expected not null but got null');
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        assert(in_array($needle, $haystack, true), $message ?: "Expected array to contain {$needle}");
    }

    protected function assertNotContains(mixed $needle, array $haystack, string $message = ''): void
    {
        assert(!in_array($needle, $haystack, true), $message ?: "Expected array to not contain {$needle}");
    }

    protected function assertArrayHasKey(mixed $key, array $array, string $message = ''): void
    {
        assert(array_key_exists($key, $array), $message ?: "Expected array to have key {$key}");
    }

    protected function assertArrayNotHasKey(mixed $key, array $array, string $message = ''): void
    {
        assert(!array_key_exists($key, $array), $message ?: "Expected array to not have key {$key}");
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $count = count($array);
        assert($count === $expected, $message ?: "Expected {$expected} elements but got {$count}");
    }

    protected function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        assert($actual > $expected, $message ?: "Expected {$actual} to be greater than {$expected}");
    }

    protected function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        assert($actual < $expected, $message ?: "Expected {$actual} to be less than {$expected}");
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        assert(str_contains($haystack, $needle), $message ?: "Expected string to contain '{$needle}'");
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        assert(!str_contains($haystack, $needle), $message ?: "Expected string to not contain '{$needle}'");
    }

    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        assert((bool) preg_match($pattern, $string), $message ?: "Expected string to match pattern {$pattern}");
    }

    protected function assertInstanceOf(string $expected, object $actual, string $message = ''): void
    {
        assert($actual instanceof $expected, $message ?: "Expected instance of {$expected}");
    }

    protected function assertJson(string|array $data): void
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }
        json_decode($data);
        assert(json_last_error() === JSON_ERROR_NONE, 'Expected valid JSON string');
    }

    protected function assertJsonStringEqualsJsonString(string $expectedJson, string $actualJson, string $message = ''): void
    {
        $expected = json_decode($expectedJson, true);
        $actual = json_decode($actualJson, true);
        assert($expected == $actual, $message ?: 'JSON strings are not equal');
    }

    protected function fail(string $message = ''): void
    {
        throw new \AssertionError($message ?: 'Test failed');
    }
}

/**
 * HttpTestCase - TestCase for HTTP testing
 */
abstract class HttpTestCase extends TestCase
{
    protected ?Response $response = null;

    protected function get(string $uri, array $headers = []): self
    {
        $request = $this->createRequest('GET', $uri, [], $headers);
        $this->response = $this->router->dispatch($request);
        return $this;
    }

    protected function post(string $uri, array $data = [], array $headers = []): self
    {
        $request = $this->createRequest('POST', $uri, $data, $headers);
        $this->response = $this->router->dispatch($request);
        return $this;
    }

    protected function put(string $uri, array $data = [], array $headers = []): self
    {
        $request = $this->createRequest('PUT', $uri, $data, $headers);
        $this->response = $this->router->dispatch($request);
        return $this;
    }

    protected function delete(string $uri, array $headers = []): self
    {
        $request = $this->createRequest('DELETE', $uri, [], $headers);
        $this->response = $this->router->dispatch($request);
        return $this;
    }

    protected function createRequest(string $method, string $uri, array $data = [], array $headers = []): Request
    {
        $request = Request::capture();

        // Set request method via reflection
        $reflection = new \ReflectionClass($request);
        $serverProp = $reflection->getProperty('server');
        $serverProp->setAccessible(true);
        $server = $serverProp->getValue($request);
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri;
        $serverProp->setValue($request, $server);

        // Set request data
        $reflection = new \ReflectionClass($request);
        $getProp = $reflection->getProperty('get');
        $getProp->setAccessible(true);
        $postProp = $reflection->getProperty('post');
        $postProp->setAccessible(true);
        $bodyProp = $reflection->getProperty('body');
        $bodyProp->setAccessible(true);

        if ($method === 'GET') {
            $getProp->setValue($request, $data);
        } else {
            $postProp->setValue($request, $data);
            $bodyProp->setValue($request, $data);
        }

        return $request;
    }

    public function assertStatus(int $status): self
    {
        assert($this->response !== null, 'No response available');
        assert(
            $this->response->getStatusCode() === $status,
            "Expected status {$status} but got {$this->response->getStatusCode()}"
        );
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertAccepted(): self
    {
        return $this->assertStatus(202);
    }

    public function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    public function assertBadRequest(): self
    {
        return $this->assertStatus(400);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    public function assertServerError(): self
    {
        return $this->assertStatus(500);
    }

    public function seeJsonResponse(string|array $data): self
    {
        assert($this->response !== null, 'No response available');
        $content = $this->response->getHeader('Content-Type', '') ?? '';
        assert(
            str_contains($content, 'application/json'),
            'Expected JSON response'
        );

        $responseData = json_decode($this->getContent(), true);

        if (is_array($data)) {
            assert($responseData == $data, 'JSON response does not match expected data');
        } else {
            $expected = json_decode($data, true);
            assert($responseData == $expected, 'JSON response does not match expected data');
        }
        return $this;
    }

    public function assertJsonValid(string $jsonString): self
    {
        json_decode($jsonString);
        assert(json_last_error() === JSON_ERROR_NONE, 'Expected valid JSON string');
        return $this;
    }

    public function assertJsonStructure(array $structure): self
    {
        $responseData = json_decode($this->getContent(), true);
        $this->validateJsonStructure($structure, $responseData);
        return $this;
    }

    private function validateJsonStructure(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if (is_string($key)) {
                assert(array_key_exists($key, $data), "Expected key '{$key}' in JSON response");
                if (is_array($value)) {
                    $this->validateJsonStructure($value, $data[$key]);
                }
            } elseif (is_array($value)) {
                assert(array_key_exists($value[0], $data), "Expected key '{$value[0]}' in JSON response");
            }
        }
    }

    public function assertHeader(string $header, ?string $value = null): self
    {
        assert($this->response !== null, 'No response available');
        $headerValue = $this->response->getHeader($header);
        assert($headerValue !== null, "Expected header '{$header}' not found");
        if ($value !== null) {
            assert($headerValue === $value, "Expected header '{$header}' to be '{$value}' but got '{$headerValue}'");
        }
        return $this;
    }

    public function getContent(): string
    {
        return $this->response?->getContent() ?? '';
    }

    public function seeJson(array $data): self
    {
        return $this->assertJson($data);
    }

    public function seeJsonStructure(array $structure): self
    {
        return $this->assertJsonStructure($structure);
    }
}
