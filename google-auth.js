// ===========================================================================
// Googleログイン共通処理（register.html / login.html で使用）
// Google Identity Services の公式ボタンを描画し、取得したIDトークンを
// api/google_auth.php に送ってログイン／新規登録する。
// クライアントIDは me.php 経由（config.php）で受け取るので、ここには書かない。
// ===========================================================================
(function () {
  // GISスクリプトを必要時に読み込む
  function loadGis() {
    return new Promise(function (resolve) {
      if (window.google && google.accounts && google.accounts.id) return resolve();
      var s = document.createElement('script');
      s.src = 'https://accounts.google.com/gsi/client';
      s.async = true; s.defer = true;
      s.onload = function () { resolve(); };
      s.onerror = function () { resolve(); };
      document.head.appendChild(s);
    });
  }

  // containerId にGoogleボタンを描画。onDone は成功時の処理（省略時はトップへ）。
  window.setupGoogleSignIn = async function (containerId, onDone) {
    var el = document.getElementById(containerId);
    if (!el) return;

    var d;
    try { d = await API.refresh(); } catch (e) { return; }
    var clientId = (d && d.googleClientId) || API.googleClientId;
    if (!clientId) { el.style.display = 'none'; return; }   // 未設定なら何も出さない

    await loadGis();
    if (!(window.google && google.accounts && google.accounts.id)) return;

    google.accounts.id.initialize({
      client_id: clientId,
      callback: async function (resp) {
        try {
          await API.refresh();
          var d = await API.post('google_auth.php', { credential: resp.credential });
          // 新規登録の人は、まずプロフィール（公開ニックネーム）確認画面へ
          if (d && d.isNew) { location.href = 'profile-edit.html?welcome=1'; return; }
          if (typeof onDone === 'function') onDone();
          else location.href = '/';
        } catch (e) {
          alert((e && e.message) || 'Googleログインに失敗しました');
        }
      },
    });

    google.accounts.id.renderButton(el, {
      theme: 'outline',
      size: 'large',
      shape: 'rectangular',
      text: 'continue_with',
      logo_alignment: 'center',
      width: Math.min(el.offsetWidth || 320, 400),
      locale: 'ja',
    });
  };
})();
