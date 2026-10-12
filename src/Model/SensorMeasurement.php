<?php

declare(strict_types=1);

namespace BeSmartAndPro\BsapSensorClient\Model;

use DateTimeImmutable;

final readonly class SensorMeasurement
{
    public function __construct(
        private string $sensor,
        private float $temperature,
        private ?float $humidity,
        private ?DateTimeImmutable $datetime,
    ) {
    }

    public function getSensor(): string
    {
        return $this->sensor;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getHumidity(): ?float
    {
        return $this->humidity;
    }

    public function getDatetime(): ?DateTimeImmutable
    {
        return $this->datetime;
    }
}
