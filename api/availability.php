<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    $dotenvPath = dirname(__DIR__) . '/.env';
    if (is_file($dotenvPath)) {
        $dotenv = @parse_ini_file($dotenvPath, true, INI_SCANNER_TYPED);
        if (is_array($dotenv) && isset($dotenv[$key])) {
            $value = (string) $dotenv[$key];
            if (trim($value) !== '') {
                return trim($value);
            }
        }
    }

    return trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? $default));
}

function readAvailability(): array
{
    $file = dirname(__DIR__) . '/data/availability.json';
    $default = [
        'pon' => ['free' => ['10:00', '11:00', '12:00', '14:00', '17:00'], 'busy' => ['09:00', '13:00', '15:00', '18:00']],
        'tor' => ['free' => ['10:30', '11:30', '13:00', '16:00', '18:00'], 'busy' => ['09:30', '12:00', '14:30', '17:30']],
        'sre' => ['free' => ['09:00', '11:00', '13:30', '15:00', '18:00'], 'busy' => ['10:00', '12:30', '14:00', '16:00']],
        'cet' => ['free' => ['10:00', '12:00', '14:00', '15:30', '18:30'], 'busy' => ['09:30', '11:30', '13:00', '17:00']],
        'pet' => ['free' => ['09:30', '11:00', '13:00', '16:00', '19:00'], 'busy' => ['10:30', '12:00', '14:30', '18:00']],
        'sob' => ['free' => ['09:00', '10:30', '12:30', '15:00', '18:00'], 'busy' => ['11:00', '13:00', '16:00', '17:30']],
        'ned' => ['free' => ['09:30', '12:00', '14:00', '16:30', '19:00'], 'busy' => ['10:00', '11:30', '13:30', '18:30']],
    ];

    if (!is_file($file)) {
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $json = @file_get_contents($file);
    if ($json === false || trim($json) === '') {
        return $default;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $default;
    }

    foreach ($default as $day => $slots) {
        if (!isset($decoded[$day]) || !is_array($decoded[$day])) {
            $decoded[$day] = $slots;
        }
        $decoded[$day]['free'] = is_array($decoded[$day]['free'] ?? null) ? array_values(array_unique(array_map('trim', $decoded[$day]['free']))) : $slots['free'];
        $decoded[$day]['busy'] = is_array($decoded[$day]['busy'] ?? null) ? array_values(array_unique(array_map('trim', $decoded[$day]['busy']))) : $slots['busy'];
    }

    return $decoded;
}

function saveAvailability(array $data): void
{
    $file = dirname(__DIR__) . '/data/availability.json';
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(readAvailability(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

session_start();
if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$input = trim((string) file_get_contents('php://input'));
$payload = $input === '' ? [] : json_decode($input, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload.']);
    exit;
}

$days = readAvailability();
foreach (['pon', 'tor', 'sre', 'cet', 'pet', 'sob', 'ned'] as $day) {
    $freeValues = $payload[$day]['free'] ?? [];
    $busyValues = $payload[$day]['busy'] ?? [];

    $days[$day]['free'] = array_values(array_unique(array_filter(array_map('trim', (array) $freeValues), static fn ($value) => $value !== '')));
    $days[$day]['busy'] = array_values(array_unique(array_filter(array_map('trim', (array) $busyValues), static fn ($value) => $value !== '')));
}

saveAvailability($days);

echo json_encode(['status' => 'success', 'data' => $days], JSON_UNESCAPED_UNICODE);
