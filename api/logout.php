<?php
// ログアウト
require __DIR__ . '/lib.php';
require_post();
require_csrf();

clear_remember_token();   // ログイン保持トークンも失効（このデバイス）

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
json_out(['ok' => true]);
