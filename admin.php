<?php
session_start();

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

    return trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? $default));
}

function readAvailability(): array
{
    $file = __DIR__ . '/data/availability.json';
    if (!is_file($file)) {
        return [
            'pon' => ['free' => ['10:00', '11:00', '12:00', '14:00', '17:00'], 'busy' => ['09:00', '13:00', '15:00', '18:00']],
            'tor' => ['free' => ['10:30', '11:30', '13:00', '16:00', '18:00'], 'busy' => ['09:30', '12:00', '14:30', '17:30']],
            'sre' => ['free' => ['09:00', '11:00', '13:30', '15:00', '18:00'], 'busy' => ['10:00', '12:30', '14:00', '16:00']],
            'cet' => ['free' => ['10:00', '12:00', '14:00', '15:30', '18:30'], 'busy' => ['09:30', '11:30', '13:00', '17:00']],
            'pet' => ['free' => ['09:30', '11:00', '13:00', '16:00', '19:00'], 'busy' => ['10:30', '12:00', '14:30', '18:00']],
            'sob' => ['free' => ['09:00', '10:30', '12:30', '15:00', '18:00'], 'busy' => ['11:00', '13:00', '16:00', '17:30']],
            'ned' => ['free' => ['09:30', '12:00', '14:00', '16:30', '19:00'], 'busy' => ['10:00', '11:30', '13:30', '18:30']],
        ];
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function saveAvailability(array $data): void
{
    $file = __DIR__ . '/data/availability.json';
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function normalizeList($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    $clean = [];
    foreach ($items as $item) {
        $trimmed = trim((string) $item);
        if ($trimmed !== '') {
            $clean[] = $trimmed;
        }
    }

    return array_values(array_unique($clean));
}

$adminUser = envValue('ADMIN_USERNAME', 'admin');
$adminPass = envValue('ADMIN_PASSWORD', 'adminljubljana230428');
$days = readAvailability();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        if ((string) ($_POST['username'] ?? '') === $adminUser && (string) ($_POST['password'] ?? '') === $adminPass) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $adminUser;
        } else {
            $error = 'Neveljavno uporabniško ime ali geslo.';
        }
    }

    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: admin.php');
        exit;
    }

    if ($_POST['action'] === 'save' && ($_SESSION['admin_logged_in'] ?? false)) {
        $payload = $_POST['slots'] ?? [];
        if (is_array($payload)) {
            foreach (['pon', 'tor', 'sre', 'cet', 'pet', 'sob', 'ned'] as $day) {
                $days[$day]['free'] = normalizeList($payload[$day]['free'] ?? '');
                $days[$day]['busy'] = normalizeList($payload[$day]['busy'] ?? '');
            }
            saveAvailability($days);
            $notice = 'Ure so uspešno shranjene.';
        } else {
            $error = 'Neveljaven zapis ur.';
        }
    }
}

if (!($_SESSION['admin_logged_in'] ?? false)) {
    echo '<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin login – Posvojilo Ljubljana</title>
  <style>
    :root { --bg:#0a0d13; --panel:rgba(15,23,42,0.8); --gold:#f4d48b; --gold-2:#d6a84d; --text:#f8fafc; --muted:#b7c0d0; --line:rgba(255,255,255,0.08); }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, Arial, sans-serif;
      background: radial-gradient(circle at top, rgba(214,168,77,0.2), transparent 28%), linear-gradient(180deg, #05080d 0%, #0b1220 100%);
      color: var(--text);
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }
    .login-box {
      width: min(440px, 100%);
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 24px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.3);
      padding: 28px 22px;
    }
    h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.4rem); }
    .subtitle { margin: 0 0 22px; color: var(--muted); }
    .field { display: grid; gap: 8px; margin-bottom: 16px; }
    label { color: var(--muted); font-weight: 700; }
    input {
      width: 100%;
      background: rgba(255,255,255,0.02);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      padding: 12px 14px;
      color: var(--text);
      font: inherit;
    }
    .btn {
      width: 100%;
      border: 0;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--gold), var(--gold-2), #b8821d);
      color: #1a1206;
      font-weight: 800;
      padding: 14px 18px;
      cursor: pointer;
    }
    .error {
      background: rgba(255,136,105,0.12);
      color: #ffb29a;
      border: 1px solid rgba(255,136,105,0.36);
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 18px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h1>Admin</h1>
    <p class="subtitle">Posvojilo Ljubljana – upravljanje urnika</p>
    ' . ($error !== '' ? '<div class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
    <form method="post">
      <input type="hidden" name="action" value="login" />
      <div class="field">
        <label for="username">Uporabniško ime</label>
        <input id="username" name="username" type="text" required />
      </div>
      <div class="field">
        <label for="password">Geslo</label>
        <input id="password" name="password" type="password" required />
      </div>
      <button class="btn" type="submit">Prijava</button>
    </form>
  </div>
</body>
</html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin – Posvojilo Ljubljana</title>
  <style>
    :root {
      --bg: #0a0d13;
      --bg-soft: #111827;
      --panel: rgba(15, 23, 42, 0.74);
      --gold-1: #f4d48b;
      --gold-2: #d6a84d;
      --text: #f8fafc;
      --muted: #b7c0d0;
      --success: #7ef0b2;
      --danger: #ff8869;
      --line: rgba(255,255,255,0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, Arial, sans-serif;
      background: radial-gradient(circle at top, rgba(214,168,77,0.18), transparent 30%), linear-gradient(180deg, #05080d 0%, #0b1220 100%);
      color: var(--text);
    }
    .wrap {
      max-width: 1240px;
      margin: 0 auto;
      padding: 32px 18px 60px;
    }
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
    }
    .brand { font-size: clamp(1.7rem, 3vw, 2.5rem); font-weight: 900; }
    .brand span { color: var(--gold-1); }
    .logout {
      border: 1px solid rgba(244,212,139,0.4);
      background: linear-gradient(135deg, var(--gold-1), var(--gold-2), var(--gold-3));
      color: #1a1206;
      padding: 10px 18px;
      border-radius: 999px;
      font-weight: 800;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .notice, .error {
      padding: 12px 14px;
      border-radius: 12px;
      margin-bottom: 18px;
      font-weight: 600;
    }
    .notice { background: rgba(126,240,178,0.12); color: var(--success); border: 1px solid rgba(126,240,178,0.4); }
    .error { background: rgba(255,136,105,0.12); color: var(--danger); border: 1px solid rgba(255,136,105,0.4); }
    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }
    .card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 22px;
      padding: 18px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.25);
    }
    .day-card h2 {
      margin: 0 0 12px;
      font-size: 1.2rem;
      color: var(--heading, #fff);
    }
    .field {
      display: grid;
      gap: 7px;
      margin-bottom: 12px;
    }
    label { color: var(--muted); font-weight: 700; }
    textarea {
      width: 100%;
      min-height: 78px;
      border-radius: 12px;
      background: rgba(255,255,255,0.02);
      color: var(--text);
      border: 1px solid rgba(255,255,255,0.08);
      padding: 12px 14px;
      resize: vertical;
      font: inherit;
    }
    .submit-btn {
      border: 0;
      border-radius: 12px;
      padding: 12px 18px;
      background: linear-gradient(135deg, var(--gold-1), var(--gold-2), var(--gold-3));
      color: #1a1206;
      font-weight: 800;
      cursor: pointer;
      margin-top: 10px;
    }
    @media (max-width: 860px) {
      .grid { grid-template-columns: 1fr; }
      .topbar { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">Posvojilo <span>Ljubljana</span> Admin</div>
      <form method="post">
        <input type="hidden" name="action" value="logout" />
        <button class="logout" type="submit">Odjava</button>
      </form>
    </div>

    <?php if ($notice !== ''): ?><div class="notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="save" />
      <div class="grid">
        <?php
        $labels = [
            'pon' => 'Ponedeljek',
            'tor' => 'Torek',
            'sre' => 'Sreda',
            'cet' => 'Četrtek',
            'pet' => 'Petek',
            'sob' => 'Sobota',
            'ned' => 'Nedelja',
        ];
        foreach ($labels as $dayKey => $label):
            $freeList = implode(', ', $days[$dayKey]['free'] ?? []);
            $busyList = implode(', ', $days[$dayKey]['busy'] ?? []);
        ?>
        <div class="card day-card">
          <h2><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="field">
            <label for="free-<?= $dayKey ?>">Proste ure</label>
            <textarea id="free-<?= $dayKey ?>" name="slots[<?= $dayKey ?>][free]" placeholder="10:00, 11:00, 12:00"><?= htmlspecialchars($freeList, ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div class="field">
            <label for="busy-<?= $dayKey ?>">Zasedene ure</label>
            <textarea id="busy-<?= $dayKey ?>" name="slots[<?= $dayKey ?>][busy]" placeholder="09:00, 13:00"><?= htmlspecialchars($busyList, ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="submit-btn" type="submit">Shrani urnik</button>
    </form>
  </div>
</body>
</html>
