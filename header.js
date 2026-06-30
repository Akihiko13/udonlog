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

    @media (max-width: 560px) {
      .ulog-nav { gap: 0.85rem; }
      .ulog-nav a:not(.ulog-cta) { font-size: 12px; }
      .ulog-cta { padding: 6px 13px; }
    }
    @media (max-width: 380px) {
      .ulog-nav a.ulog-hide-sm { display: none; }
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
      '<nav class="ulog-nav" id="ulog-nav"></nav>';
    document.body.insertBefore(header, document.body.firstChild);

    // 前回のログイン状態をキャッシュから復元して即描画（チラつき防止）。
    // その後 API で実際の状態を確認し、変わっていれば差し替える。
    var cached = null;
    try { cached = localStorage.getItem(AUTH_CACHE); } catch (e) {}
    renderNav(cached === '1' ? {} : null);   // {} は「ログイン中レイアウト」を出すためのダミー

    if (typeof API !== 'undefined') {
      API.refresh().then(function (d) {
        renderNav(d.user);
        try { localStorage.setItem(AUTH_CACHE, d.user ? '1' : '0'); } catch (e) {}
      }).catch(function () {});
    }
  }

  // --- ナビの描画（ログイン状態で出し分け）---
  function renderNav(user) {
    var nav = document.getElementById('ulog-nav');
    if (!nav) return;
    if (user) {
      nav.innerHTML =
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
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
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="login.html">ログイン</a>' +
        '<a href="register.html" class="ulog-cta">無料登録</a>';
    }
  }

  if (document.body) {
    insertHeader();
  } else {
    document.addEventListener('DOMContentLoaded', insertHeader);
  }
})();
