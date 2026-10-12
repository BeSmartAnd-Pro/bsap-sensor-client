<?php

declare(strict_types=1);

namespace BeSmartAndPro\BsapSensorClient\Exception;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly ?string $retryAfter = null,
    ) {
        parent::__construct(sprintf('Sensor API returned HTTP %d.', $statusCode), $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRetryAfter(): ?string
    {
        return $this->retryAfter;
    }
}
