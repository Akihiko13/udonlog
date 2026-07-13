// ===========================================================================
// うどログ 地図共通部品（UdonMap）
// ---------------------------------------------------------------------------
// 地図まわりを「地図プロバイダに依存しない自前API」で包む層。
// いまは Google Maps JavaScript API を使うが、将来 Leaflet+OSM 等に乗り換える
// 時は【このファイルの中身だけ】を差し替えれば、呼び出し側(shops.html /
// shop-detail.html)には手を入れずに済む設計にしている。
//
// APIキーは config.php → me.php 経由（API.refresh() の d.mapsApiKey）で受け取る。
// キーはHTTPリファラー制限(udolog.com)前提なのでフロント露出して問題ない。
// 未設定なら UdonMap.isAvailable() が false を返し、呼び出し側は地図UIを隠す。
//
// 公開API:
//   await UdonMap.ensureLoaded()      … 地図ライブラリを一度だけ読み込む（キーが無ければ false）
//   UdonMap.isAvailable()             … キーが取得できて地図が使えるか
//   const h = UdonMap.create(el, {center, zoom})   … el に地図を作る（ハンドルを返す）
//   UdonMap.setMarkers(h, markers)    … ピンを一括設置（markers: [{lat,lng,title,html,onClick}]）
//   UdonMap.clearMarkers(h)           … ピンを全消去
//   UdonMap.setUserMarker(h, {lat,lng}) … 現在地マーカー（青丸）を置く／更新
//   UdonMap.fitBounds(h, points)      … 全点が入るよう表示範囲を調整（points: [{lat,lng}]）
//   UdonMap.panTo(h, {lat,lng}, zoom) … 指定地点へ移動
// ===========================================================================
(function () {
  let loadPromise = null;   // 読み込みは一度だけ
  let apiKey = null;        // me.php から取得したキー（空文字＝未設定）

  // me.php からキーを取得（api.js の API.refresh を利用）。取得済みなら再取得しない。
  async function fetchKey() {
    if (apiKey !== null) return apiKey;
    try {
      const d = await API.refresh();
      apiKey = (d && d.mapsApiKey) || '';
    } catch (e) {
      apiKey = '';
    }
    return apiKey;
  }

  // Google Maps JS API を動的読み込み（公式の推奨する async ローダー相当）
  function injectScript(key) {
    return new Promise(function (resolve, reject) {
      if (window.google && google.maps && google.maps.Map) return resolve();
      // Google公式のブートストラップ（loading=async 対応）
      window.__udonMapInit = function () { resolve(); };
      const s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js'
        + '?key=' + encodeURIComponent(key)
        + '&callback=__udonMapInit'
        + '&language=ja&region=JP'
        + '&loading=async';
      s.async = true;
      s.onerror = function () { reject(new Error('maps load error')); };
      document.head.appendChild(s);
    });
  }

  // 色付きのピン画像（SVGデータURI）。記録済み/未記録などの塗り分けに使う。
  // 外部リクエストなしで色を自由に指定でき、ピン形状（しずく型）も保てる。
  function coloredPinIcon(color) {
    const svg =
      '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 24 34">' +
        '<path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 22 12 22s12-13 12-22C24 5.4 18.6 0 12 0z" ' +
          'fill="' + color + '" stroke="#fff" stroke-width="1.5"/>' +
        '<circle cx="12" cy="12" r="4.3" fill="#fff"/>' +
      '</svg>';
    return {
      url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
      scaledSize: new google.maps.Size(28, 40),
      anchor: new google.maps.Point(14, 40),   // しずくの先端を座標に合わせる
    };
  }

  const UdonMap = {
    // 地図ライブラリを読み込む。キーが無い/読み込み失敗なら false を返す（例外は投げない）。
    ensureLoaded: async function () {
      if (loadPromise) return loadPromise;
      loadPromise = (async function () {
        const key = await fetchKey();
        if (!key) return false;
        try {
          await injectScript(key);
          return !!(window.google && google.maps && google.maps.Map);
        } catch (e) {
          return false;
        }
      })();
      return loadPromise;
    },

    // キーが取得済みで使える見込みか（ensureLoaded 済みでなくても、fetchKey 後なら判定可）
    isAvailable: function () {
      return apiKey !== null ? !!apiKey : true;   // 未取得なら「たぶん使える」楽観。実体は ensureLoaded の戻り値で判定を。
    },

    // 地図を生成。el は表示先要素。opts.center {lat,lng} / opts.zoom
    create: function (el, opts) {
      opts = opts || {};
      const center = opts.center || { lat: 34.34, lng: 134.04 };   // 既定=高松あたり
      const map = new google.maps.Map(el, {
        center: center,
        zoom: opts.zoom || 11,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        clickableIcons: false,
        gestureHandling: 'greedy',   // 1本指でスクロール地図移動（スマホで使いやすく）
      });
      return {
        map: map,
        markers: [],
        userMarker: null,
        infoWindow: new google.maps.InfoWindow(),
      };
    },

    // ピンを一括設置。既存ピンは消してから置き直す。
    // markers: [{lat,lng,title,color(省略可・ピンの色),html(省略可・InfoWindowのHTML),onClick(省略可)}]
    setMarkers: function (h, markers) {
      UdonMap.clearMarkers(h);
      (markers || []).forEach(function (m) {
        if (m.lat == null || m.lng == null) return;
        const opts = {
          position: { lat: m.lat, lng: m.lng },
          map: h.map,
          title: m.title || '',
        };
        if (m.color) opts.icon = coloredPinIcon(m.color);   // 色指定があれば色付きピン、無ければ既定
        const marker = new google.maps.Marker(opts);
        marker.addListener('click', function () {
          if (m.html) {
            h.infoWindow.setContent(m.html);
            h.infoWindow.open(h.map, marker);
          }
          if (typeof m.onClick === 'function') m.onClick(m);
        });
        h.markers.push(marker);
      });
    },

    clearMarkers: function (h) {
      (h.markers || []).forEach(function (mk) { mk.setMap(null); });
      h.markers = [];
      if (h.infoWindow) h.infoWindow.close();
    },

    // 現在地マーカー（青丸）。呼ぶたびに位置を更新。
    setUserMarker: function (h, pos) {
      if (!pos) return;
      const icon = {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 8,
        fillColor: '#1a73e8',
        fillOpacity: 1,
        strokeColor: '#fff',
        strokeWeight: 3,
      };
      if (h.userMarker) {
        h.userMarker.setPosition(pos);
      } else {
        h.userMarker = new google.maps.Marker({
          position: pos, map: h.map, icon: icon, title: '現在地', zIndex: 9999,
        });
      }
    },

    // 全点が収まるよう表示範囲を調整
    fitBounds: function (h, points) {
      const pts = (points || []).filter(function (p) { return p && p.lat != null && p.lng != null; });
      if (pts.length === 0) return;
      if (pts.length === 1) { h.map.setCenter(pts[0]); h.map.setZoom(15); return; }
      const b = new google.maps.LatLngBounds();
      pts.forEach(function (p) { b.extend({ lat: p.lat, lng: p.lng }); });
      h.map.fitBounds(b, 48);   // 余白48px
    },

    // 地図上に自前のコントロール（DOM要素）を配置する。
    // position は 'RIGHT_BOTTOM' 等（google.maps.ControlPosition のキー名）。
    // el はGoogleのコントロール層に移設され、既存コントロール（ズーム等）と自動で整列する。
    addControl: function (h, el, position) {
      const CP = google.maps.ControlPosition;
      const pos = (position && CP[position] != null) ? CP[position] : CP.RIGHT_BOTTOM;
      h.map.controls[pos].push(el);
    },

    panTo: function (h, pos, zoom) {
      if (!pos) return;
      h.map.panTo(pos);
      if (zoom) h.map.setZoom(zoom);
    },
  };

  window.UdonMap = UdonMap;
})();
