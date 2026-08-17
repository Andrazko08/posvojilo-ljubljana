<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$raw = trim((string) file_get_contents('php://input'));
$payload = $raw === '' ? [] : json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request body.']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is required.']);
    exit;
}

function fallbackReply(string $message): string
{
    $text = strtolower($message);

    if (str_contains($text, 'posojilo') || str_contains($text, 'kako deluje') || str_contains($text, 'postopek')) {
        return 'Postopek je enostaven: najprej oddate vlogo, nato se dogovorimo o znesku, obroku in terminu za klic. V večini primerov dobite odgovor isti dan.';
    }

    if (str_contains($text, 'znesek') || str_contains($text, 'koliko') || str_contains($text, 'dobim')) {
        return 'Na voljo je posojilo do 1.750 € z možnimi obroki že od 50 € mesečno. Končni znesek in obrok sta odvisna od vašega profila in dogovora.';
    }

    if (str_contains($text, 'termin') || str_contains($text, 'klic') || str_contains($text, 'hitro')) {
        return 'Najhitrejši termin je običajno takoj v naslednjih prostih urah. Izberite dan in uro v obrazcu ali nas kontaktirajte neposredno za klic.';
    }

    if (str_contains($text, 'obresti') || str_contains($text, 'cena')) {
        return 'Ponujamo posojilo z jasno dogovorjenim obrokom, brez skritih stroškov in brez dodatnih obresti. Podrobnosti se uredijo individualno.';
    }

    if (str_contains($text, 'kontakt') || str_contains($text, 'telefon') || str_contains($text, 'kdo')) {
        return 'Kontaktirate nas na telefonski številki 041 473 133. Lahko pa oddate vlogo na spletni strani in se z nami dogovorimo za klic.';
    }

    return 'Lahko vam pomagam pri vprašanju o posojilu, postopku, obroku ali terminu. Napišite kratek opis, kaj želite.';
}

$apiKey = envValue('OPENAI_API_KEY');
if ($apiKey === '') {
    echo json_encode([
        'status' => 'success',
        'reply' => fallbackReply($message),
        'fallback' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$systemPrompt = "Si AI pomočnik za spletno stran Posvojilo Ljubljana. Odgovarjaj v slovenščini, kratek in profesionalen. Pomoči pri posojilu, postopku, zneskih, obrokih, urniku in terminom za klic. Če ni jasno, prosim vprašaj eno kratko vprašanje. Nikoli ne izmisli podatkov o cenah, obrestih ali zneskih, ki niso navedeni. Podatki o ponudbi: do 1.750 € z obroki že od 50 € in brez obresti.";

$curl = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ],
        'temperature' => 0.6,
        'max_tokens' => 220,
    ], JSON_UNESCAPED_UNICODE),
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($response === false || $httpCode >= 400 || $curlError !== '') {
    echo json_encode([
        'status' => 'success',
        'reply' => fallbackReply($message),
        'fallback' => true,
        'http_code' => $httpCode,
        'error' => $curlError !== '' ? $curlError : $response,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);
$reply = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
if ($reply === '') {
    echo json_encode([
        'status' => 'success',
        'reply' => fallbackReply($message),
        'fallback' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'success', 'reply' => $reply], JSON_UNESCAPED_UNICODE);
