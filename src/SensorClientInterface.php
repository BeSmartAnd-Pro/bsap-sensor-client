<?php

declare(strict_types=1);

namespace BeSmartAndPro\BsapSensorClient;

use BeSmartAndPro\BsapSensorClient\Model\SensorMeasurement;

interface SensorClientInterface
{
    /** @return list<SensorMeasurement> */
    public function getSensorHistory(string $sensorId): array;
}
