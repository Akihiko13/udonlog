<?php
// ===========================================================================
// うどログ 管理ダッシュボード（登録数などの確認用）
// ---------------------------------------------------------------------------
// ・アクセスにはパスワードが必要です。パスワードは config.php の 'admin_pass'
//   に設定してください（このファイルには書きません＝GitHubに漏れないため）。
// ・config.php に 'admin_pass' が無い／空の場合、この画面は無効化されます。
// ・検索避けのため <meta name="robots" content="noindex"> を付けています。
// ===========================================================================
require __DIR__ . '/api/lib.php';   // db()・セッション・CSRF などの共通処理

$adminPass = (string)($CONFIG['admin_pass'] ?? '');

// --- ログアウト ---
if (isset($_GET['logout'])) {
  unset($_SESSION['admin_ok']);
  header('Location: admin.php');
  exit;
}

$loginError = '';

// --- ログイン処理（パスワード照合）---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  // CSRF（フォーム埋め込みトークンを検証）
  $sentCsrf = $_POST['csrf'] ?? '';
  if (!is_string($sentCsrf) || !hash_equals(csrf_token(), $sentCsrf)) {
    $loginError = 'セッションが無効です。再読み込みしてください。';
  } elseif ($adminPass === '') {
    $loginError = '管理パスワードが未設定です（config.php を確認してください）。';
  } else {
    usleep(300000); // 総当たり対策の軽いウェイト（0.3秒）
    $input = (string)($_POST['password'] ?? '');
    if (hash_equals($adminPass, $input)) {
      session_regenerate_id(true);
      $_SESSION['admin_ok'] = true;
      header('Location: admin.php');
      exit;
    }
    $loginError = 'パスワードが違います。';
  }
}

$authed = !empty($_SESSION['admin_ok']) && $adminPass !== '';

// --- 統計の取得（認証済みのときだけ）---
$stats = null;
if ($authed) {
  $pdo = db();
  $one = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();

  $stats = [
    'users_total'  => $one('SELECT COUNT(*) FROM users'),
    'users_today'  => $one('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()'),
    'users_7d'     => $one('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() - INTERVAL 6 DAY'),
    'logs_total'   => $one('SELECT COUNT(*) FROM logs'),
    'logs_today'   => $one('SELECT COUNT(*) FROM logs WHERE created_at >= CURDATE()'),
    'logs_public'  => $one('SELECT COUNT(*) FROM logs WHERE is_public = 1'),
  ];

  // 直近14日の日別 新規登録数（登録が無い日も 0 で埋める）
  $rows = $pdo->query(
    'SELECT DATE(created_at) d, COUNT(*) c
       FROM users
      WHERE created_at >= CURDATE() - INTERVAL 13 DAY
      GROUP BY DATE(created_at)'
  )->fetchAll();
  $byDay = [];
  foreach ($rows as $r) { $byDay[$r['d']] = (int)$r['c']; }

  $daily = [];
  for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $daily[] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
  }
  $stats['daily']    = $daily;
  $stats['dailyMax'] = max(1, ...array_column($daily, 'count'));
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>管理ダッシュボード — うどログ</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --amber: #BA7517; --amber-dark: #8A5510;
      --bg: #FAFAF7; --white: #fff; --border: #E8E8E2;
      --text: #111; --text-sub: #666;
    }
    body {
      font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--bg); color: var(--text); line-height: 1.7;
      padding: 2rem 1.25rem 4rem;
    }
    .wrap { max-width: 760px; margin: 0 auto; }
    h1 { font-size: 22px; font-weight: 700; margin-bottom: 0.25rem; }
    .sub { color: var(--text-sub); font-size: 13px; margin-bottom: 2rem; }

    /* ログインフォーム */
    .login-card {
      max-width: 380px; margin: 4rem auto 0;
      background: var(--white); border: 0.5px solid var(--border);
      border-radius: 14px; padding: 2rem;
    }
    .login-card h1 { font-size: 18px; text-align: center; margin-bottom: 1.5rem; }
    .login-card input[type=password] {
      width: 100%; padding: 12px 14px; font-size: 15px;
      border: 1px solid var(--border); border-radius: 10px; margin-bottom: 12px;
    }
    .login-card button {
      width: 100%; padding: 12px; font-size: 15px; font-weight: 700;
      color: var(--white); background: var(--amber-dark);
      border: none; border-radius: 10px; cursor: pointer;
    }
    .login-card button:hover { background: #6F440C; }
    .err { color: #C0392B; font-size: 13px; margin-bottom: 12px; text-align: center; }

    /* 数字カード */
    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1rem; }
    .card {
      background: var(--white); border: 0.5px solid var(--border);
      border-radius: 14px; padding: 1.25rem;
    }
    .card .n { font-size: 30px; font-weight: 700; color: var(--amber); line-height: 1.1; }
    .card .l { font-size: 12px; color: var(--text-sub); margin-top: 4px; }
    .card .sm { font-size: 11px; color: #999; margin-top: 2px; }

    /* 日別推移 */
    .panel {
      background: var(--white); border: 0.5px solid var(--border);
      border-radius: 14px; padding: 1.25rem 1.5rem; margin-top: 1rem;
    }
    .panel h2 { font-size: 14px; font-weight: 700; margin-bottom: 1rem; }
    .bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; font-size: 12px; }
    .bar-date { width: 56px; color: var(--text-sub); flex-shrink: 0; }
    .bar-track { flex: 1; background: #F0EEE8; border-radius: 6px; height: 18px; overflow: hidden; }
    .bar-fill { height: 100%; background: var(--amber); border-radius: 6px; min-width: 2px; }
    .bar-num { width: 28px; text-align: right; flex-shrink: 0; }

    .topbar { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    .logout { font-size: 13px; color: var(--text-sub); text-decoration: none; }
    .logout:hover { text-decoration: underline; }

    @media (max-width: 560px) {
      .cards { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
<div class="wrap">
<?php if (!$authed): ?>

  <form class="login-card" method="post" autocomplete="off">
    <h1>🍜 管理ダッシュボード</h1>
    <?php if ($loginError): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
    <input type="password" name="password" placeholder="管理パスワード" autofocus>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <button type="submit">ログイン</button>
  </form>

<?php else: ?>

  <div class="topbar">
    <div>
      <h1>管理ダッシュボード</h1>
      <div class="sub"><?= h(date('Y年n月j日 H:i')) ?> 時点</div>
    </div>
    <a class="logout" href="admin.php?logout=1">ログアウト</a>
  </div>

  <div class="cards">
    <div class="card">
      <div class="n"><?= number_format($stats['users_total']) ?></div>
      <div class="l">総会員数</div>
    </div>
    <div class="card">
      <div class="n"><?= number_format($stats['users_today']) ?></div>
      <div class="l">今日の新規登録</div>
      <div class="sm">直近7日：<?= number_format($stats['users_7d']) ?>人</div>
    </div>
    <div class="card">
      <div class="n"><?= number_format($stats['logs_total']) ?></div>
      <div class="l">総記録数</div>
      <div class="sm">今日：<?= number_format($stats['logs_today']) ?>件／公開：<?= number_format($stats['logs_public']) ?>件</div>
    </div>
  </div>

  <div class="panel">
    <h2>新規登録の推移（直近14日）</h2>
    <?php foreach ($stats['daily'] as $d):
      $pct = round($d['count'] / $stats['dailyMax'] * 100);
      $label = date('n/j', strtotime($d['date']));
    ?>
      <div class="bar-row">
        <div class="bar-date"><?= h($label) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width: <?= $d['count'] > 0 ? $pct : 0 ?>%"></div></div>
        <div class="bar-num"><?= (int)$d['count'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>
</div>
</body>
</html>
