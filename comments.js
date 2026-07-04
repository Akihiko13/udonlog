// ===========================================================================
// うどログ 共通コメント機能（feed.html / shop-detail.html などで共用）
// ---------------------------------------------------------------------------
// 使い方：
//   1. <head> で api.js の後に <script src="comments.js"></script> を読み込む
//   2. 投稿カードの「いいね」の隣にコメントボタンを、カード内に展開枠を置く：
//        <div class="ulog-cmt-btn" onclick="ulogCmtToggle(LOGID)">
//          <i class="ti ti-message-circle"></i>
//          <span id="ulog-cmt-count-LOGID">件数</span>
//        </div>
//        <div class="ulog-cmt-box" id="ulog-cmt-box-LOGID" hidden></div>
//   3. あとはこのファイルが開閉・読み込み・投稿・削除をすべて面倒みます。
// トースト(showToast)があれば失敗時に使い、無ければ何もしません。
// ===========================================================================
(function () {
  if (window.ulogCmtToggle) return;   // 二重読み込みガード

  // --- スタイル（他ページと衝突しないよう ulog-cmt- 接頭辞）---
  var css =
    '.ulog-cmt-btn{display:flex;align-items:center;gap:4px;font-size:13px;color:#888;' +
      'cursor:pointer;width:fit-content;user-select:none;}' +
    '.ulog-cmt-btn:hover{color:#8A5510;}' +
    '.ulog-cmt-btn i{font-size:16px;line-height:1;}' +
    '.ulog-post-actions{display:flex;align-items:center;gap:20px;margin-top:10px;}' +
    '.ulog-post-actions .post-likes{margin-top:0 !important;}' +
    '.ulog-cmt-box{margin-top:12px;border-top:0.5px solid #eee;padding-top:10px;}' +
    '.ulog-cmt-loading,.ulog-cmt-empty{font-size:13px;color:#999;padding:6px 2px;}' +
    '.ulog-cmt-item{display:flex;gap:10px;padding:8px 0;}' +
    '.ulog-cmt-avatar{width:30px;height:30px;flex-shrink:0;border-radius:50%;background:#BA7517;' +
      'color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;' +
      'overflow:hidden;text-decoration:none;}' +
    '.ulog-cmt-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;}' +
    '.ulog-cmt-main{flex:1;min-width:0;}' +
    '.ulog-cmt-head{display:flex;align-items:baseline;gap:8px;}' +
    '.ulog-cmt-name{font-size:13px;font-weight:600;color:#1a1a1a;text-decoration:none;}' +
    'a.ulog-cmt-name:hover{color:#8A5510;text-decoration:underline;}' +
    '.ulog-cmt-date{font-size:11px;color:#aaa;}' +
    '.ulog-cmt-body{font-size:14px;color:#1a1a1a;line-height:1.55;white-space:pre-wrap;word-break:break-word;margin-top:1px;}' +
    '.ulog-cmt-del{flex-shrink:0;background:none;border:none;color:#c9938f;font-size:15px;cursor:pointer;' +
      'padding:2px 4px;line-height:1;border-radius:6px;}' +
    '.ulog-cmt-del:hover{background:#fbeceb;color:#d9534f;}' +
    '.ulog-cmt-form{display:flex;gap:8px;margin-top:8px;}' +
    '.ulog-cmt-input{flex:1;min-width:0;border:0.5px solid #d0d0ca;border-radius:99px;' +
      'padding:8px 14px;font-size:14px;color:#1a1a1a;background:#fff;outline:none;font-family:inherit;}' +
    '.ulog-cmt-input:focus{border-color:#BA7517;box-shadow:0 0 0 3px rgba(186,117,23,0.1);}' +
    '.ulog-cmt-send{flex-shrink:0;background:#8A5510;color:#fff;border:none;border-radius:99px;' +
      'padding:8px 16px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;}' +
    '.ulog-cmt-send:hover{background:#6F440C;}' +
    '.ulog-cmt-send:disabled{background:#b79a6a;cursor:default;}' +
    '.ulog-cmt-login{display:inline-block;margin-top:8px;font-size:13px;font-weight:600;' +
      'color:#8A5510;text-decoration:none;}' +
    '.ulog-cmt-login:hover{text-decoration:underline;}';

  var style = document.createElement('style');
  style.textContent = css;
  (document.head || document.documentElement).appendChild(style);

  var loaded = {};   // logId -> 読み込み済みフラグ

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }

  function fmtDate(d) {
    if (!d) return '';
    var dt = new Date((d + '').replace(' ', 'T'));   // "YYYY-MM-DD HH:MM:SS" もSafariで解釈できるように
    if (isNaN(dt)) return '';
    return dt.getFullYear() + '年' + (dt.getMonth() + 1) + '月' + dt.getDate() + '日';
  }

  function fail(msg) {
    if (window.showToast) showToast(msg, { error: true });
  }

  function loginUrl() {
    return 'login.html?next=' + encodeURIComponent(location.pathname + location.search);
  }

  function rowHtml(logId, c) {
    var nick = c.nickname || 'うどん好き';
    var inner = c.avatarUrl
      ? '<img src="' + esc(c.avatarUrl) + '" alt="">'
      : esc((nick.charAt(0) || 'う'));
    var avatar = c.username
      ? '<a class="ulog-cmt-avatar" href="/' + encodeURIComponent(c.username) + '">' + inner + '</a>'
      : '<span class="ulog-cmt-avatar">' + inner + '</span>';
    var name = c.username
      ? '<a class="ulog-cmt-name" href="/' + encodeURIComponent(c.username) + '">' + esc(nick) + '</a>'
      : '<span class="ulog-cmt-name">' + esc(nick) + '</span>';
    var del = c.canDelete
      ? '<button class="ulog-cmt-del" title="削除" onclick="ulogCmtDelete(' + logId + ',' + c.id + ')"><i class="ti ti-trash"></i></button>'
      : '';
    return '<div class="ulog-cmt-item" id="ulog-cmt-item-' + c.id + '">' +
        avatar +
        '<div class="ulog-cmt-main">' +
          '<div class="ulog-cmt-head">' + name +
            '<span class="ulog-cmt-date">' + esc(fmtDate(c.createdAt)) + '</span></div>' +
          '<div class="ulog-cmt-body">' + esc(c.body) + '</div>' +
        '</div>' + del +
      '</div>';
  }

  function render(logId, comments) {
    var box = document.getElementById('ulog-cmt-box-' + logId);
    if (!box) return;
    var list = (comments || []).map(function (c) { return rowHtml(logId, c); }).join('');
    var loggedIn = (typeof API !== 'undefined') && API.isLoggedIn && API.isLoggedIn();
    var footer = loggedIn
      ? '<div class="ulog-cmt-form">' +
          '<input type="text" class="ulog-cmt-input" id="ulog-cmt-input-' + logId + '" ' +
            'maxlength="140" placeholder="コメントを書く…" enterkeyhint="send">' +
          '<button class="ulog-cmt-send" onclick="ulogCmtSubmit(' + logId + ')">送信</button>' +
        '</div>'
      : '<a class="ulog-cmt-login" href="' + loginUrl() + '">ログインしてコメント</a>';
    box.innerHTML = '<div class="ulog-cmt-list" id="ulog-cmt-list-' + logId + '">' +
      (list || '<div class="ulog-cmt-empty">最初のコメントを書いてみよう</div>') +
      '</div>' + footer;
    var inp = document.getElementById('ulog-cmt-input-' + logId);
    if (inp) inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); ulogCmtSubmit(logId); }
    });
  }

  function load(logId) {
    var box = document.getElementById('ulog-cmt-box-' + logId);
    if (!box) return;
    box.innerHTML = '<div class="ulog-cmt-loading">読み込み中…</div>';
    if (typeof API === 'undefined') { box.innerHTML = ''; return; }
    API.get('comment_list.php?log_id=' + logId).then(function (d) {
      loaded[logId] = true;
      render(logId, d.comments || []);
    }).catch(function () {
      box.innerHTML = '<div class="ulog-cmt-loading">コメントを読み込めませんでした</div>';
    });
  }

  function setCount(logId, count) {
    var el = document.getElementById('ulog-cmt-count-' + logId);
    if (el && typeof count === 'number') el.textContent = count;
  }

  // --- 公開関数（投稿カードの onclick から呼ばれる）---
  window.ulogCmtToggle = function (logId) {
    var box = document.getElementById('ulog-cmt-box-' + logId);
    if (!box) return;
    if (box.hasAttribute('hidden')) {
      box.removeAttribute('hidden');
      if (!loaded[logId]) load(logId);
    } else {
      box.setAttribute('hidden', '');
    }
  };

  window.ulogCmtSubmit = function (logId) {
    var inp = document.getElementById('ulog-cmt-input-' + logId);
    if (!inp) return;
    var text = inp.value.trim();
    if (!text) return;
    inp.disabled = true;
    Promise.resolve(API.refresh()).then(function () {
      if (!API.isLoggedIn()) { location.href = loginUrl(); return null; }
      return API.post('comment_add.php', { log_id: logId, body: text });
    }).then(function (d) {
      if (!d) return;
      inp.value = ''; inp.disabled = false; inp.focus();
      var listEl = document.getElementById('ulog-cmt-list-' + logId);
      if (listEl) {
        var empty = listEl.querySelector('.ulog-cmt-empty');
        if (empty) empty.remove();
        listEl.insertAdjacentHTML('beforeend', rowHtml(logId, d.comment));
      }
      setCount(logId, d.count);
    }).catch(function (e) {
      inp.disabled = false;
      fail((e && e.message) || 'コメントに失敗しました');
    });
  };

  window.ulogCmtDelete = function (logId, id) {
    if (!confirm('このコメントを削除しますか？')) return;
    Promise.resolve(API.refresh()).then(function () {
      return API.post('comment_delete.php', { id: id });
    }).then(function (d) {
      var item = document.getElementById('ulog-cmt-item-' + id);
      if (item) item.remove();
      setCount(logId, d.count);
      var listEl = document.getElementById('ulog-cmt-list-' + logId);
      if (listEl && !listEl.querySelector('.ulog-cmt-item')) {
        listEl.innerHTML = '<div class="ulog-cmt-empty">最初のコメントを書いてみよう</div>';
      }
    }).catch(function (e) {
      fail((e && e.message) || '削除に失敗しました');
    });
  };
})();
