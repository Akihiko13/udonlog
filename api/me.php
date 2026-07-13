<?php
// 現在のログイン状態とCSRFトークンを返す。
// 各ページ読み込み時に最初に呼び、ログイン中ならユーザー情報、未ログインならnull。
// 返ってきた csrf を、以降のPOST（登録/ログイン/記録など）のヘッダ X-CSRF-Token に付ける。
require __DIR__ . '/lib.php';
$me = current_user();
json_out([
  'ok'   => true,
  'user' => $me,   // 未ログインなら null
  'csrf' => csrf_token(),
  'googleClientId' => $CONFIG['google_client_id'] ?? '',   // Googleログイン用（公開情報）
  'mapsApiKey' => $CONFIG['maps_api_key'] ?? '',           // 地図表示用（リファラー制限前提で公開）
  'notifUnread' => $me ? unread_notif_count((int)$me['id']) : 0,   // 未読通知数（ヘッダーのバッジ用）
]);
