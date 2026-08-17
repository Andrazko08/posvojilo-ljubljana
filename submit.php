<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    $dotenvPath = __DIR__ . '/.env';
    if (is_file($dotenvPath)) {
        $dotenv = @parse_ini_file($dotenvPath, true, INI_SCANNER_TYPED);
        if (is_array($dotenv) && isset($dotenv[$key])) {
            $value = (string) $dotenv[$key];
            if (trim($value) !== '') {
                return trim($value);
            }
        }
    }

    $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    return trim((string) $value);
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

$honeypot = $_POST['website'] ?? '';
if ($honeypot !== '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$required = ['ime', 'priimek', 'telefon', 'znesek'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Vsa polja so obvezna.']);
        exit;
    }
}

$ime = trim((string) $_POST['ime']);
$priimek = trim((string) $_POST['priimek']);
$telefon = trim((string) $_POST['telefon']);
$znesek = trim((string) $_POST['znesek']);
$danKlica = trim((string) ($_POST['dan_klica'] ?? ''));
$uraKlica = trim((string) ($_POST['ura_klica'] ?? ''));
$termin = trim((string) ($_POST['termin'] ?? ''));
if ($termin === '' && $danKlica !== '' && $uraKlica !== '') {
    $termin = $danKlica . ' ' . $uraKlica;
}

$smtpUser = envValue('MAIL_USERNAME');
$smtpPass = envValue('MAIL_PASSWORD');
$adminEmail = envValue('MAIL_TO', 'pevecandraz@gmail.com');

if ($smtpUser === '' || $smtpPass === '') {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Email credentials are not configured on this server.'
    ]);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($smtpUser, 'Posvojilo Ljubljana');
    $mail->addAddress($adminEmail);
    $mail->addReplyTo($smtpUser, 'Posvojilo Ljubljana');

    $mail->Subject = 'Nova vloga - Posvojilo Ljubljana';
    $mail->Body = "Nova vloga za Posvojilo Ljubljana\r\n\r\n" .
        "Ime: {$ime}\r\n" .
        "Priimek: {$priimek}\r\n" .
        "Telefonska številka: {$telefon}\r\n" .
        "Znesek posojila: {$znesek}\r\n" .
        ($termin !== '' ? "Dogovor za klic: {$termin}\r\n" : '') .
        "\r\nPoslana z obrazca na spletni strani.";
    $mail->AltBody = "Nova vloga za Posvojilo Ljubljana\n\nIme: {$ime}\nPriimek: {$priimek}\nTelefonska številka: {$telefon}\nZnesek posojila: {$znesek}" .
        ($termin !== '' ? "\nDogovor za klic: {$termin}" : "");

    if (!$mail->send()) {
        throw new Exception('Pošiljanje e-pošte ni uspelo.');
    }

    echo json_encode(['status' => 'success', 'message' => 'Vloga uspešno oddana']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Pošiljanje e-pošte ni uspelo.',
        'details' => $e->getMessage(),
    ]);
}
