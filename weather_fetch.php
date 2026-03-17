<?php
/**
 * weather_fetch.php
 *
 * Fetches current weather from OpenWeatherMap API and stores it in weather_log.
 * Can be called:
 *   - Directly in browser  → returns JSON
 *   - Via Windows Task Scheduler / cron (every 4 hours)
 *   - Included inline by addtocart.php when data is stale
 *
 * Windows Task Scheduler command (run every 4 hours):
 *   php "F:\xampp\htdocs\shopapp\weather_fetch.php"
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once(__DIR__ . '/db.php');
include_once(__DIR__ . '/weather_config.php');

// ── 1. Ensure weather_log table exists ────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS weather_log (
    w_id         INT AUTO_INCREMENT PRIMARY KEY,
    temperature  DECIMAL(5,2)   NOT NULL,
    feels_like   DECIMAL(5,2)   DEFAULT NULL,
    temp_min     DECIMAL(5,2)   DEFAULT NULL,
    temp_max     DECIMAL(5,2)   DEFAULT NULL,
    humidity     INT            NOT NULL,
    pressure     INT            NOT NULL,
    weather_type VARCHAR(100)   NOT NULL,
    weather_icon VARCHAR(20)    DEFAULT NULL,
    wind_speed   DECIMAL(6,2)   DEFAULT NULL,
    location     VARCHAR(100)   NOT NULL,
    recorded_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source       ENUM('api','manual') NOT NULL DEFAULT 'api',
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── 2. Check if a recent record already exists (within the fetch interval) ────
$interval_minutes = (int)(WEATHER_FETCH_INTERVAL / 60);
$recent = $conn->query(
    "SELECT w_id, recorded_at FROM weather_log
     WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL {$interval_minutes} MINUTE)
     ORDER BY w_id DESC LIMIT 1"
);

if ($recent && $recent->num_rows > 0) {
    $row = $recent->fetch_assoc();
    $response = [
        'success'     => true,
        'fetched'     => false,
        'message'     => 'Recent weather data exists, skipping fetch',
        'last_w_id'   => (int)$row['w_id'],
        'recorded_at' => $row['recorded_at'],
    ];
    if (php_sapi_name() !== 'cli' && !defined('WEATHER_CALLED_INTERNALLY')) {
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    return $response;    // allow include() callers to capture the return value
}

// ── 3. Call OpenWeatherMap API ─────────────────────────────────────────────────
if (OWM_API_KEY === 'YOUR_API_KEY_HERE') {
    $err = ['success' => false, 'error' => 'Weather API key not configured. Edit weather_config.php'];
    if (php_sapi_name() !== 'cli' && !defined('WEATHER_CALLED_INTERNALLY')) {
        header('Content-Type: application/json');
        echo json_encode($err);
    }
    return $err;
}

$api_url = sprintf(
    'https://api.openweathermap.org/data/2.5/weather?q=%s&appid=%s&units=%s',
    urlencode(OWM_CITY),
    OWM_API_KEY,
    OWM_UNITS
);

// Use cURL (more reliable than file_get_contents on XAMPP)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,   // XAMPP localhost workaround
]);
$raw      = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($raw === false || $curl_err) {
    $err = ['success' => false, 'error' => 'cURL failed: ' . $curl_err];
    if (php_sapi_name() !== 'cli' && !defined('WEATHER_CALLED_INTERNALLY')) {
        header('Content-Type: application/json');
        echo json_encode($err);
    }
    return $err;
}

$data = json_decode($raw, true);
if (!$data || (int)($data['cod'] ?? 0) !== 200) {
    $err = ['success' => false, 'error' => 'API error: ' . ($data['message'] ?? 'unknown')];
    if (php_sapi_name() !== 'cli' && !defined('WEATHER_CALLED_INTERNALLY')) {
        header('Content-Type: application/json');
        echo json_encode($err);
    }
    return $err;
}

// ── 4. Extract values ──────────────────────────────────────────────────────────
$temperature  = (float)($data['main']['temp']       ?? 0);
$feels_like   = (float)($data['main']['feels_like'] ?? 0);
$temp_min     = (float)($data['main']['temp_min']   ?? 0);
$temp_max     = (float)($data['main']['temp_max']   ?? 0);
$humidity     = (int)  ($data['main']['humidity']   ?? 0);
$pressure     = (int)  ($data['main']['pressure']   ?? 0);
$weather_type = (string)($data['weather'][0]['description'] ?? '');
$weather_icon = (string)($data['weather'][0]['icon']        ?? '');
$wind_speed   = (float)($data['wind']['speed']              ?? 0);
$location     = trim(($data['name'] ?? '') . ', ' . ($data['sys']['country'] ?? ''));
$recorded_at  = date('Y-m-d H:i:s');
$source       = 'api';

// ── 5. Insert into weather_log ─────────────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO weather_log
        (temperature, feels_like, temp_min, temp_max, humidity, pressure,
         weather_type, weather_icon, wind_speed, location, recorded_at, source)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    'ddddiiissdss',
    $temperature, $feels_like, $temp_min, $temp_max,
    $humidity, $pressure,
    $weather_type, $weather_icon, $wind_speed,
    $location, $recorded_at, $source
);
$stmt->execute();
$new_id = $conn->insert_id;
$stmt->close();

// ── 6. Return result ───────────────────────────────────────────────────────────
$response = [
    'success'      => true,
    'fetched'      => true,
    'w_id'         => $new_id,
    'temperature'  => $temperature,
    'feels_like'   => $feels_like,
    'humidity'     => $humidity,
    'pressure'     => $pressure,
    'weather_type' => $weather_type,
    'wind_speed'   => $wind_speed,
    'location'     => $location,
    'recorded_at'  => $recorded_at,
];

if (php_sapi_name() !== 'cli' && !defined('WEATHER_CALLED_INTERNALLY')) {
    header('Content-Type: application/json');
    echo json_encode($response);
}
return $response;
