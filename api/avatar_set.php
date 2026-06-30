<?php
// プロフィール写真をアップロードして設定（ログイン必須）
// multipart/form-data でファイル項目 "avatar" を受け取る。
// 安全のため画像を検証し、256x256の正方形JPEGに再エンコードして保存する。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

// --- GD（画像処理ライブラリ）が使えるか確認 ---
if (!function_exists('imagecreatetruecolor')) {
  json_error('サーバーで画像処理が利用できません（GD未対応）', 500);
}

// --- アップロードの基本チェック ---
if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
  json_error('画像が送信されていません');
}
$f = $_FILES['avatar'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  json_error('アップロードに失敗しました（サイズが大きすぎる可能性があります）');
}
if (($f['size'] ?? 0) > 8 * 1024 * 1024) {       // 8MBまで
  json_error('画像が大きすぎます（8MBまで）');
}
if (!is_uploaded_file($f['tmp_name'])) {
  json_error('不正なアップロードです');
}

// --- 画像として正しいか検証し、種類を判定 ---
$info = @getimagesize($f['tmp_name']);
if ($info === false) {
  json_error('画像ファイルとして読み込めませんでした');
}
[$srcW, $srcH] = $info;
$type = $info[2];   // IMAGETYPE_*

switch ($type) {
  case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($f['tmp_name']); break;
  case IMAGETYPE_PNG:  $src = @imagecreatefrompng($f['tmp_name']);  break;
  case IMAGETYPE_GIF:  $src = @imagecreatefromgif($f['tmp_name']);  break;
  case IMAGETYPE_WEBP:
    $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false;
    break;
  default:
    json_error('対応していない画像形式です（JPEG / PNG / GIF / WEBP に対応）');
}
if (!$src) {
  json_error('画像の読み込みに失敗しました');
}

// --- 中央を正方形に切り出して 256x256 にリサイズ ---
$size = 256;
$side = min($srcW, $srcH);
$sx = (int)(($srcW - $side) / 2);
$sy = (int)(($srcH - $side) / 2);

$dst = imagecreatetruecolor($size, $size);
// 透過PNG等は白背景で塗りつぶしてからJPEG化（黒抜け防止）
$white = imagecolorallocate($dst, 255, 255, 255);
imagefilledrectangle($dst, 0, 0, $size, $size, $white);
imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
imagedestroy($src);

// --- 保存先ディレクトリを用意（無ければ作成し、PHP実行を禁止）---
$dir = __DIR__ . '/../uploads/avatars';
if (!is_dir($dir)) {
  if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
    imagedestroy($dst);
    json_error('保存先フォルダを作成できませんでした', 500);
  }
}
$ht = $dir . '/.htaccess';
if (!file_exists($ht)) {
  // このフォルダではスクリプトを実行させない（FastCGI/LiteSpeedでも500にならない書き方）
  @file_put_contents($ht,
    "<FilesMatch \"\\.(php|phtml|php[0-9]|pl|py|cgi|asp|sh)$\">\n" .
    "  <IfModule mod_authz_core.c>\n" .
    "    Require all denied\n" .
    "  </IfModule>\n" .
    "  <IfModule !mod_authz_core.c>\n" .
    "    Order allow,deny\n" .
    "    Deny from all\n" .
    "  </IfModule>\n" .
    "</FilesMatch>\n"
  );
}

// --- ファイル名は会員IDで固定（更新は上書き）---
$filename = $u['id'] . '.jpg';
$path = $dir . '/' . $filename;
if (!imagejpeg($dst, $path, 85)) {
  imagedestroy($dst);
  json_error('画像の保存に失敗しました', 500);
}
imagedestroy($dst);

// --- DBに記録 ---
$st = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$st->execute([$filename, $u['id']]);

json_out([
  'ok'        => true,
  'avatarUrl' => avatar_url($filename),
]);
