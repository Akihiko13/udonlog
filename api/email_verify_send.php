<?php
// 新規登録：メール確認コード（6桁）を送信する。アカウント作成前の所有確認用。
require __DIR__ . '/lib.php';
require_post();
require_csrf();

// === 設定 ===
const VERIFY_MAIL_FROM      = 'info@udolog.com';   // 差出人（実在アドレス）
const VERIFY_MAIL_FROM_NAME = 'udolog';
const VERIFY_EXPIRE_MIN     = 10;                  // コードの有効時間（分）
const VERIFY_RESEND_SEC     = 30;                  // 連続再送の最短間隔（秒）

$in    = body();
$email = trim((string)($in['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_error('メールアドレスの形式が正しくありません');
}

// 既に登録済みなら送らない（登録フローなので明示してよい・register.phpと同じ挙動）
$st = db()->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_error('このメールアドレスは既に登録されています', 409);

// 連続再送の抑制（短時間の大量送信を防ぐ）
$st = db()->prepare('SELECT created_at FROM email_verifications WHERE email = ?');
$st->execute([$email]);
$exist = $st->fetch();
if ($exist) {
  $st = db()->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS sec FROM email_verifications WHERE email = ?');
  $st->execute([$email]);
  $row = $st->fetch();
  if ($row && (int)$row['sec'] < VERIFY_RESEND_SEC) {
    json_error('コードを送信しました。しばらく待ってから再度お試しください。', 429);
  }
}

// 6桁コードを発行（ハッシュで保存。生はメールにのみ）
$code = (string)random_int(100000, 999999);
$hash = hash('sha256', $code);

// メール単位で1件（上書き）。試行回数はリセット。
$st = db()->prepare(
  'INSERT INTO email_verifications (email, code_hash, expires_at, attempts, created_at)
   VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0, NOW())
   ON DUPLICATE KEY UPDATE
     code_hash = VALUES(code_hash),
     expires_at = VALUES(expires_at),
     attempts = 0,
     created_at = NOW()'
);
$st->execute([$email, $hash, VERIFY_EXPIRE_MIN]);

// メール送信
$subject = '【うどログ】確認コード：' . $code;
$bodyText =
  "うどログの登録ありがとうございます。\n\n" .
  "確認コードは次の6桁です。\n\n" .
  "　　" . $code . "\n\n" .
  "登録画面にこのコードを入力してください。\n" .
  "（有効期限は " . VERIFY_EXPIRE_MIN . "分です）\n\n" .
  "※このメールに心当たりがない場合は破棄してください。\n\n" .
  "──────────────\nうどログ\nhttps://udolog.com\n";

$headers =
  'From: ' . VERIFY_MAIL_FROM_NAME . ' <' . VERIFY_MAIL_FROM . ">\r\n" .
  'Reply-To: ' . VERIFY_MAIL_FROM;

mb_language('Japanese');
mb_internal_encoding('UTF-8');
@mb_send_mail($email, $subject, $bodyText, $headers, '-f ' . VERIFY_MAIL_FROM);

json_out(['ok' => true]);
