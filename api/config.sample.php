<?php
// ===========================================================================
// DB接続設定のテンプレート
// ---------------------------------------------------------------------------
// このファイルを同じフォルダに「config.php」という名前でコピーし、
// Xサーバーで作成したデータベースの値を記入してください。
// ※ config.php は Git に上げません（.gitignore で除外済み）。
//   サーバーに直接アップロード（SFTP）して置きます。
// ===========================================================================
return [
  'db_host' => 'localhost',          // 多くの場合 localhost（XサーバーのDBホスト名）
  'db_name' => 'あなたのDB名',        // 例: xxxx_udonlog
  'db_user' => 'あなたのDBユーザー名', // 例: xxxx_udon
  'db_pass' => 'あなたのDBパスワード', // ← ここは絶対に公開・共有しない

  // Googleログイン用のクライアントID（Google Cloud Consoleで発行）。
  // ※これは公開情報（フロントにも出る）。未設定なら空のままでGoogleログインは無効。
  'google_client_id' => '',          // 例: 1234567890-xxxx.apps.googleusercontent.com

  // Google Maps JavaScript API キー（地図表示用・Googleログインとは別キー推奨）。
  // ※フロントに出るが、Cloud Console側で「HTTPリファラー制限=udolog.com」を必ず設定すること。
  //   API制限は「Maps JavaScript API のみ」に。未設定なら地図トグルは自動で非表示になる。
  'maps_api_key' => '',              // 例: AIzaSy...
];
