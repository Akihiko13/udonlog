// ===========================================================================
// うどログ 共通サイトヘッダー（全ページ共通）
// ---------------------------------------------------------------------------
// 各ページの <head> 内で <script src="header.js"></script> を読み込むと、
// ページ最上部に共通ヘッダー（ロゴ＋ナビ）が表示されます。
// ※ <head> で読むことで、ヘッダー分の余白(padding-top)をCSSで最初から確保し、
//   本文がガクッと下がる（レイアウトシフト）のを防ぎます。
// ヘッダーの内容・デザインを変えたいときは、このファイル1つを編集すればOK。
// ===========================================================================
(function () {
  // 前回のログイン状態を覚えておくキー（ナビの初期表示に使う）
  var AUTH_CACHE = 'ulog_authed';

  // お店選びシート用の状態（最近の店キャッシュ／SHOPS遅延読み込み管理）
  var recentCache = null;
  var shopsLoading = false;
  var shopsWaiters = [];

  // --- ヘッダー用スタイル（他ページのCSSと衝突しないよう ulog- 接頭辞で統一）---
  // body の padding-top をここで確保しておくことで、ヘッダー挿入時に本文が
  // ガクッと下がるのを防ぐ（JSでの後付けをやめCSSで最初から確保）。
  var css = `
    body { padding-top: 54px; }
    .ulog-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 500;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      height: 54px;
      padding: 0 1.25rem;
      background: rgba(250,250,248,0.92);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 0.5px solid #e5e5e0;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    }
    .ulog-logo {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 16px;
      font-weight: 700;
      color: #1a1a1a;
      text-decoration: none;
      flex-shrink: 0;
    }
    .ulog-nav {
      display: flex;
      align-items: center;
      gap: 1.25rem;
      /* ページ独自の nav{} 指定（padding/背景/高さ等）が漏れてこないようリセット */
      margin: 0;
      padding: 0;
      height: auto;
      background: none;
      border: none;
      box-shadow: none;
      position: static;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
    }
    .ulog-nav a {
      font-size: 13px;
      color: #555;
      text-decoration: none;
      white-space: nowrap;
    }
    .ulog-nav a:hover { color: #1a1a1a; }
    .ulog-cta {
      background: #8A5510;
      color: #fff !important;
      padding: 7px 16px;
      border-radius: 99px;
      font-weight: 600;
    }
    .ulog-cta:hover { background: #6F440C; }

    /* ヘッダー右側（通知ベル＋ナビ）。ベルはモバイルでも表示する */
    .ulog-header-right { display: flex; align-items: center; gap: 1.25rem; }
    .ulog-bell {
      position: relative; display: none; align-items: center;
      color: #555; text-decoration: none; font-size: 21px; line-height: 1;
    }
    .ulog-bell:hover { color: #1a1a1a; }
    .ulog-bell-badge {
      position: absolute; top: -5px; right: -6px;
      min-width: 16px; height: 16px; padding: 0 4px; border-radius: 99px;
      background: #d9534f; color: #fff; font-size: 10px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; line-height: 1;
    }
    /* display:flex が hidden属性(display:none)を打ち消さないよう、未読0では確実に隠す */
    .ulog-bell-badge[hidden] { display: none; }

    /* --- 共通フッター（全ページ共通） --------------------------------------
       ページ独自の footer{} 指定が漏れないよう主要プロパティは明示する。*/
    .ulog-footer {
      box-sizing: border-box;
      width: 100%;
      margin: 2.5rem 0 0;
      padding: 2rem 1.25rem;
      border-top: 0.5px solid #e5e5e0;
      background: none;
      color: #999;
      text-align: center;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
      font-size: 12px;
      line-height: 1.7;
    }
    .ulog-footer-links {
      display: flex; flex-wrap: wrap; justify-content: center;
      gap: 8px 16px; margin-bottom: 12px;
    }
    .ulog-footer-links a { color: #8A5510; text-decoration: none; font-size: 13px; white-space: nowrap; }
    .ulog-footer-links a:hover { text-decoration: underline; }
    .ulog-footer-copy { color: #aaa; }

    @media (max-width: 560px) {
      .ulog-nav { gap: 0.85rem; }
      .ulog-nav a:not(.ulog-cta) { font-size: 12px; }
      .ulog-cta { padding: 6px 13px; }
    }
    @media (max-width: 380px) {
      .ulog-nav a.ulog-hide-sm { display: none; }
    }

    /* --- 下部タブバー（モバイルのみ表示） --------------------------------- */
    .ulog-tabbar {
      position: fixed;
      /* ページ独自の nav{ position:sticky; top:54px } 等が漏れて上部に出ないよう
         top/margin/padding/border を明示的にリセットする（.ulog-nav と同じ方針）*/
      top: auto;
      left: 0;
      right: 0;
      bottom: 0;
      margin: 0;
      z-index: 500;
      display: none;                 /* 既定は非表示。モバイル幅でのみ表示 */
      justify-content: space-around;
      align-items: center;
      /* box-sizing はページ側の *{border-box} に依存せず自前で固定。
         高さはアイコン用の58px＋セーフエリア分を加算し、その分を padding-bottom で
         確保する。こうしないと（border-box時）セーフエリア分だけ中身が縮み、
         アイコンが上にはみ出る。 */
      box-sizing: border-box;
      height: calc(58px + env(safe-area-inset-bottom));
      padding: 0 0 env(safe-area-inset-bottom);   /* 下部はiPhoneのホームバー分 */
      background: rgba(250,250,248,0.95);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: none;
      border-top: 0.5px solid #e5e5e0;
      box-shadow: none;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    }
    .ulog-tab {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      height: 100%;
      min-width: 0;
      color: #9a9a90;
      text-decoration: none;
      font-size: 10px;
      font-weight: 600;
    }
    .ulog-tab i { font-size: 23px; line-height: 1; }
    .ulog-tab span { line-height: 1; }
    .ulog-tab.active { color: #8A5510; }
    .ulog-tab:active { opacity: 0.6; }
    /* 通知タブの未読バッジ */
    .ulog-tab-iconwrap { position: relative; display: inline-flex; line-height: 1; }
    .ulog-tab-badge {
      position: absolute; top: -4px; right: -7px;
      min-width: 15px; height: 15px; padding: 0 3px; border-radius: 99px;
      background: #d9534f; color: #fff; font-size: 9px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; line-height: 1;
    }
    .ulog-tab-badge[hidden] { display: none; }

    /* 記録用の浮動ボタン（FAB）。タブバーの上・右下に浮かせる（X本家と同じ発想）。
       表示可否は renderTabbar が .ulog-fab-on の付け外しで制御する。*/
    .ulog-fab {
      display: none;                 /* 既定は非表示 */
      position: fixed;
      right: 16px;
      bottom: calc(58px + env(safe-area-inset-bottom) + 16px);  /* タブバーの少し上 */
      z-index: 501;                  /* タブバー(500)より前 */
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: #BA7517;
      color: #fff !important;
      text-decoration: none;
      box-shadow: 0 4px 14px rgba(186,117,23,0.45);
    }
    .ulog-fab i { font-size: 28px; line-height: 1; color: #fff; }
    .ulog-fab:active { background: #8A5510; }

    /* --- 記録：お店選びシート（FABから開く） ------------------------------ */
    .ulog-sheet-overlay {
      position: fixed;
      inset: 0;
      z-index: 600;
      background: rgba(0,0,0,0.4);
      display: none;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .ulog-sheet-overlay.show { display: block; opacity: 1; }
    body.ulog-sheet-open { overflow: hidden; }
    .ulog-sheet {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
      flex-direction: column;
      max-height: 82vh;
      background: #FAFAF8;
      border-radius: 18px 18px 0 0;
      padding: 10px 16px calc(14px + env(safe-area-inset-bottom));
      box-shadow: 0 -6px 24px rgba(0,0,0,0.18);
      transform: translateY(100%);
      transition: transform 0.25s ease;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    }
    .ulog-sheet-overlay.show .ulog-sheet { transform: translateY(0); }
    .ulog-sheet-handle {
      width: 40px; height: 4px; border-radius: 99px;
      background: #d8d3c8; margin: 2px auto 12px;
    }
    .ulog-sheet-title { font-size: 16px; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; }
    .ulog-sheet-search {
      display: flex; align-items: center; gap: 8px;
      background: #fff; border: 0.5px solid #e5e5e0; border-radius: 10px;
      padding: 10px 12px; margin-bottom: 12px; flex-shrink: 0;
    }
    .ulog-sheet-search i { color: #999; font-size: 18px; }
    .ulog-sheet-search input {
      flex: 1; min-width: 0; border: none; outline: none; background: none;
      font-size: 15px; color: #1a1a1a; font-family: inherit;
    }
    .ulog-sheet-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
    .ulog-sheet-label { font-size: 12px; font-weight: 700; color: #999; margin: 4px 2px 6px; }
    .ulog-sheet-row {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 8px; border-radius: 10px; text-decoration: none; color: inherit;
    }
    .ulog-sheet-row:active { background: #f0ece3; }
    .ulog-sheet-row-icon {
      width: 38px; height: 38px; flex-shrink: 0; border-radius: 9px;
      background: #FFF8EE; display: flex; align-items: center; justify-content: center;
    }
    .ulog-sheet-row-icon i { color: #BA7517; font-size: 19px; }
    .ulog-sheet-row-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .ulog-sheet-row-name {
      font-size: 15px; font-weight: 600; color: #1a1a1a;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ulog-sheet-row-meta { font-size: 12px; color: #888; }
    .ulog-sheet-row-add { color: #BA7517; font-size: 20px; flex-shrink: 0; }
    .ulog-sheet-empty { text-align: center; color: #999; font-size: 14px; padding: 24px 12px; line-height: 1.6; }
    .ulog-sheet-all {
      display: flex; align-items: center; justify-content: center; gap: 4px;
      flex-shrink: 0; margin-top: 8px; padding: 13px;
      border-top: 0.5px solid #e5e5e0;
      color: #8A5510; font-size: 14px; font-weight: 600; text-decoration: none;
    }
    .ulog-sheet-all:active { opacity: 0.6; }

    @media (max-width: 640px) {
      .ulog-tabbar { display: flex; }
      /* タブバーの高さぶん本文下部に余白を確保（バーを出すページだけ） */
      body.ulog-has-tabbar { padding-bottom: calc(62px + env(safe-area-inset-bottom)); }
      /* モバイルでは上部ヘッダーのナビを隠して「うどログ」ロゴだけ残す。
         通常ページはナビを下部タブバーへ集約。認証ページ(タブバー無し)も
         ログイン等に集中できるようナビは出さない（ロゴからホームへは戻れる）。*/
      .ulog-nav { display: none; }
      /* 記録FABはモバイルかつ表示ONのときだけ出す */
      .ulog-fab.ulog-fab-on { display: flex; }
      /* モバイルは通知タブに集約するので、ヘッダー右上のベルは隠す
         （renderBellのinline display:flexを上書きするため!important）*/
      .ulog-bell { display: none !important; }

      /* iOS Safariは16px未満の入力欄にフォーカスすると画面を自動ズームし、
         ズームが戻らないことも多い。モバイルの入力欄は一律16pxにして防ぐ
         （各ページの個別指定(13〜15px)より優先させるため!important）*/
      input[type="text"], input[type="email"], input[type="password"],
      input[type="search"], input[type="number"], input[type="date"],
      input:not([type]), textarea, select {
        font-size: 16px !important;
      }
    }
  `;

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  // --- ヘッダー本体を body 先頭に挿入。<head> から読まれて body が未構築の
  //     場合は DOMContentLoaded を待ってから挿入する ---
  function insertHeader() {
    var header = document.createElement('header');
    header.className = 'ulog-header';
    header.innerHTML =
      '<a href="/" class="ulog-logo">うどログ</a>' +
      '<div class="ulog-header-right">' +
        '<a href="notifications.html" class="ulog-bell" id="ulog-bell" aria-label="通知">' +
          '<i class="ti ti-bell"></i>' +
          '<span class="ulog-bell-badge" id="ulog-bell-badge" hidden></span>' +
        '</a>' +
        '<nav class="ulog-nav" id="ulog-nav"></nav>' +
      '</div>';
    document.body.insertBefore(header, document.body.firstChild);

    // 下部タブバー（モバイル用）を body 末尾に挿入。認証系ページでは出さない。
    insertTabbar();

    // 共通フッターを body 末尾に挿入（全ページ共通）
    insertFooter();

    // 前回のログイン状態をキャッシュから復元して即描画（チラつき防止）。
    // その後 API で実際の状態を確認し、変わっていれば差し替える。
    var cached = null;
    try { cached = localStorage.getItem(AUTH_CACHE); } catch (e) {}
    var cachedUser = cached === '1' ? {} : null;   // {} は「ログイン中レイアウト」用のダミー
    renderNav(cachedUser);
    renderTabbar(cachedUser);
    renderBell(cachedUser, null);   // キャッシュ時は数はまだ分からない（ベルだけ出す）

    if (typeof API !== 'undefined') {
      API.refresh().then(function (d) {
        renderNav(d.user);
        renderTabbar(d.user);
        renderBell(d.user, d.notifUnread);
        try { localStorage.setItem(AUTH_CACHE, d.user ? '1' : '0'); } catch (e) {}
      }).catch(function () {});
    }
  }

  // --- 通知ベル（ログイン中のみ・未読数バッジ）---
  function renderBell(user, unread) {
    var label = (unread && unread > 0) ? (unread > 99 ? '99+' : unread) : null;
    // ヘッダーのベル（PC用。モバイルはCSSで非表示にし通知タブへ集約）
    var bell = document.getElementById('ulog-bell');
    if (bell) {
      bell.style.display = user ? 'flex' : 'none';
      setBadge('ulog-bell-badge', user ? label : null);
    }
    // 下部タブバーの通知バッジ（モバイル用）
    setBadge('ulog-tab-notif-badge', user ? label : null);
  }

  function setBadge(id, label) {
    var b = document.getElementById(id);
    if (!b) return;
    if (label) { b.textContent = label; b.removeAttribute('hidden'); }
    else b.setAttribute('hidden', '');
  }

  // 通知ページで既読にした後にバッジを消すための公開関数
  window.ulogClearNotifBadge = function () {
    setBadge('ulog-bell-badge', null);
    setBadge('ulog-tab-notif-badge', null);
  };

  // --- 共通フッター ---------------------------------------------------------
  function insertFooter() {
    if (document.getElementById('ulog-footer')) return;
    var f = document.createElement('footer');
    f.className = 'ulog-footer';
    f.id = 'ulog-footer';
    f.innerHTML =
      '<div class="ulog-footer-links">' +
        '<a href="/news.html">お知らせ</a>' +
        '<a href="/terms.html">利用規約</a>' +
        '<a href="/privacy.html">プライバシーポリシー</a>' +
        '<a href="mailto:info@udolog.com">お問い合わせ</a>' +
        '<a href="https://x.com/udolog_com" target="_blank" rel="noopener">公式X</a>' +
        '<a href="https://kagawan.com/" target="_blank" rel="noopener">カガワン（香川県情報ブログ）</a>' +
      '</div>' +
      '<div class="ulog-footer-copy">&copy; 2026 うどログ. All rights reserved.</div>';
    document.body.appendChild(f);
  }

  // --- 下部タブバー ---------------------------------------------------------
  // ログイン/登録などの集中フローでは邪魔になるので出さない
  function isAuthPage() {
    return /(^|\/)(login|register|forgot-password|reset-password)\.html$/.test(location.pathname);
  }

  // 現在ページがどのタブに当たるか（アクティブ表示用）
  function currentTab() {
    var p = location.pathname;
    if (/(^|\/)feed\.html$/.test(p)) return 'feed';
    if (/(^|\/)(shops|shop-detail)\.html$/.test(p) || /(^|\/)shops\//.test(p)) return 'shops';
    if (/(^|\/)record\.html$/.test(p)) return 'record';
    if (/(^|\/)notifications\.html$/.test(p)) return 'notif';
    if (/(^|\/)(mypage|profile-edit|account)\.html$/.test(p)) return 'mypage';
    return '';
  }

  function insertTabbar() {
    if (isAuthPage()) return;
    if (document.getElementById('ulog-tabbar')) return;
    var bar = document.createElement('nav');
    bar.className = 'ulog-tabbar';
    bar.id = 'ulog-tabbar';
    bar.setAttribute('aria-label', 'メインメニュー');
    document.body.appendChild(bar);
    document.body.classList.add('ulog-has-tabbar');   // 本文下部の余白を有効化

    // 記録用の浮動ボタン（FAB）。表示可否は renderTabbar で切り替える。
    var fab = document.createElement('a');
    fab.className = 'ulog-fab';
    fab.id = 'ulog-fab';
    fab.href = 'shops.html';
    fab.setAttribute('aria-label', 'うどんを記録する');
    fab.innerHTML = '<i class="ti ti-plus"></i>';
    // タップでお店選びシートを開く（JS無効時は href=shops.html にフォールバック）
    fab.addEventListener('click', function (e) { e.preventDefault(); openSheet(); });
    document.body.appendChild(fab);

    setupSheet();
  }

  // === お店選びシート ========================================================
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }

  // 記録ページへのリンク（お店情報をURLに載せる。record.html が読む形式）
  function recordHref(s) {
    return 'record.html?id=' + s.id +
      '&slug=' + encodeURIComponent(s.slug || '') +
      '&name=' + encodeURIComponent(s.name) +
      '&city=' + encodeURIComponent(s.city) +
      '&type=' + encodeURIComponent(s.type);
  }

  function shopRowHtml(s) {
    return '<a class="ulog-sheet-row" href="' + recordHref(s) + '">' +
      '<span class="ulog-sheet-row-icon"><i class="ti ti-bowl-chopsticks"></i></span>' +
      '<span class="ulog-sheet-row-main">' +
        '<span class="ulog-sheet-row-name">' + esc(s.name) + '</span>' +
        '<span class="ulog-sheet-row-meta">' + esc(s.city) + ' ・ ' + esc(s.type) + '</span>' +
      '</span>' +
      '<i class="ti ti-plus ulog-sheet-row-add"></i></a>';
  }

  function shopById(id) {
    if (typeof SHOPS === 'undefined') return null;
    for (var i = 0; i < SHOPS.length; i++) { if (SHOPS[i].id === id) return SHOPS[i]; }
    return null;
  }

  // 検索・最近の店に必要な SHOPS を（無ければ）遅延読み込みしてから cb を呼ぶ
  function ensureShops(cb) {
    if (typeof SHOPS !== 'undefined') { cb(); return; }
    shopsWaiters.push(cb);
    if (shopsLoading) return;
    shopsLoading = true;
    var sc = document.createElement('script');
    sc.src = 'shops-data.js';
    sc.onload = function () {
      shopsLoading = false;
      var w = shopsWaiters; shopsWaiters = [];
      w.forEach(function (f) { f(); });
    };
    sc.onerror = function () {
      shopsLoading = false; shopsWaiters = [];
      var body = document.getElementById('ulog-sheet-body');
      if (body) body.innerHTML = '<div class="ulog-sheet-empty">お店データの読み込みに失敗しました</div>';
    };
    document.head.appendChild(sc);
  }

  // 最近記録したお店（重複を除いた直近6軒）を取得。SHOPS読み込み後に呼ぶこと。
  function loadRecent(cb) {
    if (recentCache) { cb(recentCache); return; }
    if (typeof API === 'undefined') { cb([]); return; }
    API.get('my_logs.php').then(function (d) {
      var logs = (d && d.logs) || [];
      var recent = [], seen = {};
      // my_logs.php は id 昇順（古い順）。末尾から見て新しい順に拾う。
      for (var i = logs.length - 1; i >= 0 && recent.length < 6; i--) {
        var sid = logs[i].shopId;
        if (seen[sid]) continue;
        seen[sid] = 1;
        var s = shopById(sid);
        if (s && s.status !== '閉店') recent.push(s);
      }
      recentCache = recent;
      cb(recent);
    }).catch(function () { cb([]); });
  }

  function renderRecent() {
    var body = document.getElementById('ulog-sheet-body');
    if (!body) return;
    loadRecent(function (list) {
      var input = document.getElementById('ulog-sheet-input');
      if (input && input.value.trim()) return;   // 途中で検索し始めていたら上書きしない
      if (!list.length) {
        body.innerHTML = '<div class="ulog-sheet-empty">店名を検索するか、<br>「お店一覧から探す」からお店を選んでください</div>';
        return;
      }
      body.innerHTML = '<div class="ulog-sheet-label">最近記録したお店</div>' +
        list.map(shopRowHtml).join('');
    });
  }

  function doSearch(q) {
    var body = document.getElementById('ulog-sheet-body');
    if (!body) return;
    ensureShops(function () {
      var res = SHOPS.filter(function (s) {
        return s.status !== '閉店' &&
          (((s.name || '').indexOf(q) >= 0) || ((s.kana || '').indexOf(q) >= 0));
      }).slice(0, 30);
      if (!res.length) {
        body.innerHTML = '<div class="ulog-sheet-empty">「' + esc(q) + '」に一致するお店がありません</div>';
        return;
      }
      body.innerHTML = res.map(shopRowHtml).join('');
    });
  }

  function openSheet() {
    var overlay = document.getElementById('ulog-sheet-overlay');
    if (!overlay) return;
    var input = document.getElementById('ulog-sheet-input');
    if (input) input.value = '';
    var body = document.getElementById('ulog-sheet-body');
    if (body) body.innerHTML = '<div class="ulog-sheet-empty">読み込み中…</div>';
    overlay.classList.add('show');
    document.body.classList.add('ulog-sheet-open');
    ensureShops(function () { renderRecent(); });
  }

  function closeSheet() {
    var overlay = document.getElementById('ulog-sheet-overlay');
    if (!overlay) return;
    overlay.classList.remove('show');
    document.body.classList.remove('ulog-sheet-open');
  }

  function setupSheet() {
    if (document.getElementById('ulog-sheet-overlay')) return;
    var ov = document.createElement('div');
    ov.className = 'ulog-sheet-overlay';
    ov.id = 'ulog-sheet-overlay';
    ov.innerHTML =
      '<div class="ulog-sheet" role="dialog" aria-label="お店を選んで記録">' +
        '<div class="ulog-sheet-handle"></div>' +
        '<div class="ulog-sheet-title">どのお店で食べた？</div>' +
        '<div class="ulog-sheet-search"><i class="ti ti-search"></i>' +
          '<input type="text" id="ulog-sheet-input" placeholder="店名で検索…" autocomplete="off" enterkeyhint="search"></div>' +
        '<div class="ulog-sheet-body" id="ulog-sheet-body"></div>' +
        '<a class="ulog-sheet-all" href="shops.html">お店一覧から探す <i class="ti ti-chevron-right"></i></a>' +
      '</div>';
    document.body.appendChild(ov);
    // 背景（オーバーレイ自身）タップで閉じる。シート内は閉じない。
    ov.addEventListener('click', function (e) { if (e.target === ov) closeSheet(); });
    var input = document.getElementById('ulog-sheet-input');
    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (q) doSearch(q); else renderRecent();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSheet();
    });
  }

  function renderTabbar(user) {
    var bar = document.getElementById('ulog-tabbar');
    if (!bar) return;
    var cur = currentTab();
    function tab(href, key, icon, label) {
      var active = key === cur ? ' active' : '';
      return '<a class="ulog-tab' + active + '" href="' + href + '">' +
             '<i class="ti ti-' + icon + '"></i><span>' + label + '</span></a>';
    }
    if (user) {
      // ログイン中は 新着／探す／通知／マイページ。記録は右下のFAB（下記）に分離。
      var notifActive = cur === 'notif' ? ' active' : '';
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        '<a class="ulog-tab' + notifActive + '" href="notifications.html">' +
          '<span class="ulog-tab-iconwrap"><i class="ti ti-bell"></i>' +
            '<span class="ulog-tab-badge" id="ulog-tab-notif-badge" hidden></span></span>' +
          '<span>通知</span></a>' +
        tab('mypage.html', 'mypage', 'user', 'マイページ');
    } else {
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        tab('login.html', 'login', 'login', 'ログイン') +
        tab('register.html', 'register', 'user-plus', '登録');
    }

    // 記録FAB：ログイン中かつ「探す/記録」ページ以外で表示（重複回避）。
    var fab = document.getElementById('ulog-fab');
    if (fab) {
      var showFab = !!user && cur !== 'shops' && cur !== 'record';
      fab.classList.toggle('ulog-fab-on', showFab);
    }
  }

  // --- ナビの描画（ログイン状態で出し分け）---
  function renderNav(user) {
    var nav = document.getElementById('ulog-nav');
    if (!nav) return;
    if (user) {
      nav.innerHTML =
        '<a href="feed.html">新着</a>' +
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="ranking.html" class="ulog-hide-sm">ランキング</a>' +
        '<a href="mypage.html">マイページ</a>' +
        '<a href="#" id="ulog-logout">ログアウト</a>';
      var lo = document.getElementById('ulog-logout');
      lo.addEventListener('click', function (e) {
        e.preventDefault();
        (async function () {
          try { await API.refresh(); await API.post('logout.php', {}); } catch (_) {}
          try { localStorage.setItem(AUTH_CACHE, '0'); } catch (_) {}
          location.href = '/';
        })();
      });
    } else {
      nav.innerHTML =
        '<a href="feed.html">新着</a>' +
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="ranking.html" class="ulog-hide-sm">ランキング</a>' +
        '<a href="login.html">ログイン</a>' +
        '<a href="register.html" class="ulog-cta">無料登録</a>';
    }
  }

  if (document.body) {
    insertHeader();
  } else {
    document.addEventListener('DOMContentLoaded', insertHeader);
  }

  // --- うどまるの季節衣替え（全ページ共通）---
  // 空状態などの udomaru-hungry を、夏(7〜8月)は「ひやかけ」、年末年始(12/25〜1/7)は
  // 「年越し」うどまるに自動で差し替える。動的に挿入される空状態にも対応。
  (function seasonalUdomaru() {
    var d = new Date(), m = d.getMonth() + 1, day = d.getDate();
    var file = null;
    if (m === 7 || m === 8) file = 'udomaru-summer.svg';
    else if ((m === 12 && day >= 25) || (m === 1 && day <= 7)) file = 'udomaru-newyear.svg';
    if (!file) return;   // 通常期はそのまま（hungry）

    function swapIn(node) {
      if (node.nodeType !== 1) return;
      var imgs = [];
      if (node.matches && node.matches('img[src*="udomaru-hungry"]')) imgs = [node];
      else if (node.querySelectorAll) imgs = node.querySelectorAll('img[src*="udomaru-hungry"]');
      for (var i = 0; i < imgs.length; i++) {
        imgs[i].src = imgs[i].src.replace('udomaru-hungry.svg', file);
      }
    }
    function start() {
      swapIn(document.body);   // 既にあるもの
      // これから挿入される空状態も差し替える
      new MutationObserver(function (muts) {
        for (var j = 0; j < muts.length; j++) {
          var added = muts[j].addedNodes;
          for (var k = 0; k < added.length; k++) swapIn(added[k]);
        }
      }).observe(document.body, { childList: true, subtree: true });
    }
    if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
  })();
})();
