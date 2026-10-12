<?php

declare(strict_types=1);

namespace BeSmartAndPro\BsapSensorClient\Tests;

use BeSmartAndPro\BsapSensorClient\Exception\ApiException;
use BeSmartAndPro\BsapSensorClient\Exception\InvalidResponseException;
use BeSmartAndPro\BsapSensorClient\SensorClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SensorClientTest extends TestCase
{
    public function testHistoryAndRequest(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'items' => [
                ['sensor' => 'Freezer', 'temperature' => -18.5, 'humidity' => 51, 'datetime' => '2026-10-12T12:00:00+02:00'],
                ['sensor' => 'Freezer', 'temperature' => 0, 'humidity' => null, 'datetime' => null],
            ],
        ], JSON_THROW_ON_ERROR))]));
        $stack->push(Middleware::history($history));
        $client = new SensorClient(new Client(['handler' => $stack]), new Psr17Factory(), 'test.token', 'https://example.com/');
        $items = $client->getSensorHistory('sensor /?#');

        self::assertCount(2, $items);
        self::assertSame(-18.5, $items[0]->getTemperature());
        self::assertSame(51.0, $items[0]->getHumidity());
        self::assertSame('Freezer', $items[0]->getSensor());
        self::assertSame('2026-10-12T12:00:00+02:00', $items[0]->getDatetime()?->format(DATE_ATOM));
        self::assertSame(0.0, $items[1]->getTemperature());
        self::assertNull($items[1]->getHumidity());
        self::assertNull($items[1]->getDatetime());
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame('https://example.com/api/client/sensors/sensor%20%2F%3F%23/history', (string) $history[0]['request']->getUri());
        self::assertSame('test.token', $history[0]['request']->getHeaderLine('X-Api-Token'));
        self::assertSame('application/json', $history[0]['request']->getHeaderLine('Accept'));
    }

    public function testEmptyHistory(): void
    {
        self::assertSame([], $this->client(new Response(200, [], '{"items":[]}'))->getSensorHistory('id'));
    }

    #[DataProvider('statusCodes')]
    public function testHttpErrors(int $statusCode): void
    {
        try {
            $this->client(new Response($statusCode, ['Retry-After' => '60'], '<html>error</html>'))->getSensorHistory('id');
            self::fail('An API exception was expected.');
        } catch (ApiException $exception) {
            self::assertSame($statusCode, $exception->getStatusCode());
            self::assertSame($statusCode, $exception->getCode());
            self::assertSame('60', $exception->getRetryAfter());
            self::assertStringNotContainsString('<html>', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{int}> */
    public static function statusCodes(): iterable
    {
        foreach ([301, 401, 403, 404, 429, 500, 503] as $code) {
            yield (string) $code => [$code];
        }
    }

    public function testNoRetryHeader(): void
    {
        try {
            $this->client(new Response(429))->getSensorHistory('id');
            self::fail('An API exception was expected.');
        } catch (ApiException $exception) {
            self::assertNull($exception->getRetryAfter());
        }
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidResponse(string $body): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->client(new Response(200, [], $body))->getSensorHistory('id');
    }

    /** @return iterable<array{string}> */
    public static function invalidPayloads(): iterable
    {
        foreach (['invalid', 'null', '{}', '{"items":null}', '{"items":{}}', '{"items":"wrong"}', '{"items":{"x":{}}}', '{"items":[null]}'] as $body) {
            yield [$body];
        }

        $item = ['sensor' => 'Freezer', 'temperature' => 4.2, 'humidity' => null, 'datetime' => null];

        foreach (array_keys($item) as $key) {
            $invalidItem = $item;
            unset($invalidItem[$key]);
            yield [json_encode(['items' => [$invalidItem]], JSON_THROW_ON_ERROR)];
        }

        foreach (['sensor' => 123, 'temperature' => '4.2', 'humidity' => '51', 'datetime' => 'tomorrow'] as $key => $value) {
            yield [json_encode(['items' => [array_replace($item, [$key => $value])]], JSON_THROW_ON_ERROR)];
        }

        foreach (['2026-02-30T12:00:00+02:00', '', "2026-10-12T12:00:00+02:00\0"] as $datetime) {
            yield [json_encode(['items' => [array_replace($item, ['datetime' => $datetime])]], JSON_THROW_ON_ERROR)];
        }
    }

    public function testNetworkExceptionIsPreserved(): void
    {
        $exception = new ConnectException('Connection failed.', new Request('GET', 'https://example.com'));
        $stack = HandlerStack::create(new MockHandler([$exception]));
        $client = new SensorClient(new Client(['handler' => $stack]), new Psr17Factory(), 'test.token');
        $this->expectException(ConnectException::class);
        $client->getSensorHistory('id');
    }

    #[DataProvider('invalidConfiguration')]
    public function testInvalidConfiguration(string $token, string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SensorClient(new Client(), new Psr17Factory(), $token, $uri);
    }

    /** @return iterable<array{string, string}> */
    public static function invalidConfiguration(): iterable
    {
        yield ['', 'https://example.com'];
        yield ["token\r\nInjected: value", 'https://example.com'];
        yield ['test.token', 'relative'];
        yield ['test.token', 'ftp://example.com'];
        yield ['test.token', 'https://user@example.com'];
        yield ['test.token', 'https://example.com?token=secret'];
        yield ['test.token', 'https://example.com#fragment'];
    }

    public function testEmptySensorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client(new Response(200))->getSensorHistory(' ');
    }

    private function client(Response $response): SensorClient
    {
        $stack = HandlerStack::create(new MockHandler([$response]));

        return new SensorClient(new Client(['handler' => $stack]), new Psr17Factory(), 'test.token');
    }
}
