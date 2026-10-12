<?php

declare(strict_types=1);

namespace BeSmartAndPro\BsapSensorClient;

use BeSmartAndPro\BsapSensorClient\Exception\ApiException;
use BeSmartAndPro\BsapSensorClient\Exception\InvalidResponseException;
use BeSmartAndPro\BsapSensorClient\Model\SensorMeasurement;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SensitiveParameter;
use stdClass;

final class SensorClient implements SensorClientInterface
{
    public const string DEFAULT_BASE_URI = 'https://sensor.besmartand.pro';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        #[SensitiveParameter]
        private readonly string $apiToken,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        if (trim($apiToken) === '' || preg_match('/[\x00-\x20\x7f]/', $apiToken) === 1) {
            throw new InvalidArgumentException('API token must be non-empty and contain no whitespace or control characters.');
        }

        $uri = parse_url($baseUri);

        if (
            $uri === false
            || !in_array($uri['scheme'] ?? '', ['https', 'http'], true)
            || empty($uri['host'])
            || isset($uri['user'])
            || isset($uri['query'])
            || isset($uri['fragment'])
            || preg_match('/[\x00-\x20\x7f]/', $baseUri) === 1
        ) {
            throw new InvalidArgumentException('Base URI must be an absolute HTTP(S) URL without credentials, query or fragment.');
        }
    }

    /** @return list<SensorMeasurement> */
    public function getSensorHistory(string $sensorId): array
    {
        if (trim($sensorId) === '') {
            throw new InvalidArgumentException('Sensor ID must not be empty.');
        }

        $request = $this->requestFactory
            ->createRequest(
                'GET',
                rtrim($this->baseUri, '/') . '/api/client/sensors/' . rawurlencode($sensorId) . '/history',
            )
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Api-Token', $this->apiToken);

        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $retryAfter = $response->getHeaderLine('Retry-After');

            throw new ApiException($statusCode, $retryAfter !== '' ? $retryAfter : null);
        }

        try {
            $payload = json_decode((string) $response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException('Sensor API returned invalid JSON.', 0, $exception);
        }

        if (!$payload instanceof stdClass || !isset($payload->items) || !is_array($payload->items)) {
            throw new InvalidResponseException('Sensor API response must contain an items list.');
        }

        $measurements = [];

        foreach ($payload->items as $item) {
            if (!$item instanceof stdClass) {
                throw new InvalidResponseException('Sensor API returned an invalid measurement.');
            }

            $measurements[] = $this->decodeMeasurement($item);
        }

        return $measurements;
    }

    private function decodeMeasurement(stdClass $item): SensorMeasurement
    {
        if (
            !is_string($item->sensor ?? null)
            || (!is_int($item->temperature ?? null) && !is_float($item->temperature ?? null))
            || !is_finite((float) $item->temperature)
            || !property_exists($item, 'humidity')
            || ($item->humidity !== null && !is_int($item->humidity) && !is_float($item->humidity))
            || ($item->humidity !== null && !is_finite((float) $item->humidity))
            || !property_exists($item, 'datetime')
            || ($item->datetime !== null && !is_string($item->datetime))
        ) {
            throw new InvalidResponseException('Sensor API returned an invalid measurement.');
        }

        $datetime = null;

        if ($item->datetime !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $item->datetime) !== 1) {
                throw new InvalidResponseException('Sensor API returned an invalid measurement datetime.');
            }

            $datetime = DateTimeImmutable::createFromFormat(DATE_ATOM, $item->datetime);
            $errors = DateTimeImmutable::getLastErrors();

            if ($datetime === false || $errors !== false) {
                throw new InvalidResponseException('Sensor API returned an invalid measurement datetime.');
            }
        }

        return new SensorMeasurement(
            $item->sensor,
            (float) $item->temperature,
            $item->humidity !== null ? (float) $item->humidity : null,
            $datetime,
        );
    }
}
