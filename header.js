// ===========================================================================
// うどログ 共通サイトヘッダー（全ページ共通）
// ---------------------------------------------------------------------------
// 各ページの </body> 直前で <script src="header.js"></script> を読み込むと、
// ページ最上部に共通ヘッダー（ロゴ＋ナビ）が表示されます。
// ヘッダーの内容・デザインを変えたいときは、このファイル1つを編集すればOK。
// ===========================================================================
(function () {
  // --- ヘッダー用スタイル（他ページのCSSと衝突しないよう ulog- 接頭辞で統一）---
  var css = `
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
    }
    .ulog-nav a {
      font-size: 13px;
      color: #555;
      text-decoration: none;
      white-space: nowrap;
    }
    .ulog-nav a:hover { color: #1a1a1a; }
    .ulog-cta {
      background: #BA7517;
      color: #fff !important;
      padding: 7px 16px;
      border-radius: 99px;
      font-weight: 600;
    }
    .ulog-cta:hover { background: #9A6010; }

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

  // --- ヘッダー本体（ナビは後でログイン状態に応じて差し替え）---
  var header = document.createElement('header');
  header.className = 'ulog-header';
  header.innerHTML =
    '<a href="index.html" class="ulog-logo">うどログ</a>' +
    '<nav class="ulog-nav" id="ulog-nav"></nav>';

  document.body.insertBefore(header, document.body.firstChild);
  document.body.style.paddingTop = '54px';   // 固定ヘッダー(54px)分の余白

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
          location.href = 'index.html';
        })();
      });
    } else {
      nav.innerHTML =
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="login.html">ログイン</a>' +
        '<a href="register.html" class="ulog-cta">無料登録</a>';
    }
  }

  // まず未ログイン表示 → APIでログイン状態が分かれば差し替え
  renderNav(null);
  if (typeof API !== 'undefined') {
    API.refresh().then(function (d) { renderNav(d.user); }).catch(function () {});
  }
})();
