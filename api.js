// ===========================================================================
// うどログ フロント用 API ヘルパー（全ページ共通）
// ---------------------------------------------------------------------------
// 使い方：
//   await API.refresh();            // 最初に呼ぶ。ログイン状態とCSRFを取得
//   API.user                        // ログイン中ユーザー（未ログインは null）
//   await API.get('my_logs.php')    // GET（読み取り）
//   await API.post('log_add.php', { shop_id: 1, menus:['かけ'] })  // POST（CSRF自動付与）
//
// ※ api/ フォルダと同じサイト（同一オリジン）に置かれている前提。
//   PHPが動くサーバー（エックスサーバー）でのみ機能します。
// ===========================================================================
const API = (function () {
  let _csrf = '';
  let _user = null;
  let _googleClientId = '';
  let _mapsApiKey = '';
  const base = 'api/';

  async function _json(res) {
    let d;
    try { d = await res.json(); } catch (e) { d = { ok: false, error: '通信に失敗しました' }; }
    if (!res.ok || !d.ok) throw new Error(d.error || ('エラー (' + res.status + ')'));
    return d;
  }

  // ログイン状態とCSRFトークンを取得（各ページ読み込み時に最初に呼ぶ）
  // 複数箇所から呼ばれても、最初の1回の結果を共有する（force=trueで再取得）
  let _ready = null;
  function refresh(force) {
    if (!force && _ready) return _ready;
    _ready = (async () => {
      const res = await fetch(base + 'me.php', { credentials: 'same-origin' });
      const d = await _json(res);
      _user = d.user || null;
      _csrf = d.csrf || '';
      if ('googleClientId' in d) _googleClientId = d.googleClientId || '';
      if ('mapsApiKey' in d) _mapsApiKey = d.mapsApiKey || '';
      return d;
    })();
    return _ready;
  }

  async function get(path) {
    const res = await fetch(base + path, { credentials: 'same-origin' });
    return _json(res);
  }

  async function post(path, body) {
    const res = await fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify(body || {}),
    });
    const d = await _json(res);
    if (d.csrf) _csrf = d.csrf;          // ログイン/登録でトークンが更新される
    if ('user' in d) _user = d.user;     // ログイン状態の反映
    return d;
  }

  // ファイルアップロード（multipart）。Content-Typeは指定しない（ブラウザが境界を付与）
  async function upload(path, formData) {
    const res = await fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': _csrf },
      body: formData,
    });
    return _json(res);
  }

  return {
    refresh, get, post, upload,
    get user() { return _user; },
    get csrf() { return _csrf; },
    get googleClientId() { return _googleClientId; },
    get mapsApiKey() { return _mapsApiKey; },
    isLoggedIn() { return !!_user; },
  };
})();
