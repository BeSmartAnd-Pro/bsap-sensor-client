# BeSmartAnd.Pro Sensor Client

Klient publicznego REST API `sensor.besmartand.pro` dla PHP 8.4+. Korzysta z transportu PSR-18 i fabryki żądań PSR-17 dostarczonych przez aplikację.

## Instalacja

```bash
composer require besmartand-pro/bsap-sensor-client guzzlehttp/guzzle nyholm/psr7
```

## Użycie w czystym PHP

```php
use BeSmartAndPro\BsapSensorClient\SensorClient;
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

require 'vendor/autoload.php';

$client = new SensorClient(
    new Client(['timeout' => 10, 'connect_timeout' => 5]),
    new Psr17Factory(),
    $_ENV['SENSOR_API_TOKEN'],
);

foreach ($client->getSensorHistory('SENSOR_UUID') as $measurement) {
    echo $measurement->getSensor() . ': ' . $measurement->getTemperature() . PHP_EOL;
}
```

## Zakres API

`getSensorHistory(string $sensorId): array` wywołuje `GET /api/client/sensors/{id}/history` z nagłówkiem `X-Api-Token`. Zwraca listę obiektów `SensorMeasurement`:

| Metoda | Typ zwracany |
| --- | --- |
| `getSensor()` | `string` — etykieta sensora |
| `getTemperature()` | `float` |
| `getHumidity()` | `?float` |
| `getDatetime()` | `?DateTimeImmutable` |

Serwer zwraca maksymalnie 200 ostatnich pomiarów. Kolejność odpowiedzi jest zachowywana. Obecny endpoint nie udostępnia paginacji ani filtrów dat. Token organizacji jest dostępny w panelu w sekcji **Tokeny API**, a identyfikator sensora w sekcji **Sensory**.

Biblioteka obsługuje publiczne API klienta. Endpointy urządzeń, parowania i administratora mają inne mechanizmy autoryzacji i pozostają poza jej zakresem.

## Błędy

- `ApiException`: odpowiedź inna niż HTTP 200. Metody `getStatusCode()` i `getRetryAfter()` zwracają kod i opcjonalny nagłówek `Retry-After` (sekundy lub data HTTP). Dotyczy m.in. 401, 403, 404, 429 oraz 5xx. Treść błędnej odpowiedzi nie jest dodawana do wyjątku.
- `InvalidResponseException`: niepoprawny JSON, struktura danych lub data pomiaru.
- `Psr\Http\Client\ClientExceptionInterface`: błąd transportu przekazywany bez zmian.
- `InvalidArgumentException`: niepoprawna konfiguracja albo pusty identyfikator sensora.

Klient nie ponawia żądań automatycznie. PSR-18 nie podąża automatycznie za przekierowaniami; przekazana implementacja transportu powinna przestrzegać tego kontraktu. Konfigurację timeoutu ustawia aplikacja. Nagłówek `X-Api-Token` nie powinien trafiać do logów.

## Development

```bash
composer install
composer qa
```

Testy używają odpowiedzi HTTP w pamięci. Nie kontaktują się z produkcyjnym API.

Licencja MIT.
