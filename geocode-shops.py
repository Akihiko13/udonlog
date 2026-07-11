#!/usr/bin/env python3
# ===========================================================================
# shops.csv の各店に緯度・経度（lat/lng）を Google Geocoding API で付与する
# ---------------------------------------------------------------------------
# 「現在地から近い順」検索のために各店の座標が必要。このスクリプトは
# shops.csv を読み、lat/lng が空の店だけを Google にジオコーディングして
# CSV に書き戻す（再実行しても埋まっている店はスキップ＝追加店だけ処理）。
#
# 【前提】Google Cloud で「Geocoding API」を有効化し、APIキーを1つ作る。
#   （うどログは既に Google ログイン用の Cloud プロジェクトがあるので、そこで
#    Geocoding API を有効化＋キー作成でOK。217件なら費用は実質無料枠内）
#
# 【使い方】udonlog フォルダで、キーを環境変数に入れて実行:
#   export GOOGLE_GEOCODING_API_KEY="ここに発行したキー"
#   python3 geocode-shops.py
#   （追加の pip インストールは不要＝標準ライブラリだけで動く）
#   その後: python3 build-shops-data.py で shops-data.js を再生成。
#
# オプション:
#   --force      … 既に lat/lng があっても全店やり直す
#   --limit N    … 先頭 N 件だけ処理（お試し・キー動作確認用）
#   --ids a,b,c  … 指定IDの店だけ取り直す（座標があっても上書き）。店名で正しく
#                  引けない店は下の ADDR_OVERRIDE に住所を書いて --ids で取り直す。
#
# ※ APIキーはコードに書かず、必ず環境変数で渡すこと（キーを git に含めない）。
# ===========================================================================

import csv
import json
import os
import sys
import time
import urllib.parse
import urllib.request

CSV_FILE = 'shops.csv'
API_URL = 'https://maps.googleapis.com/maps/api/geocode/json'
# 香川県の大まかな範囲（bounds）で候補を県内に寄せて誤爆を減らす
KAGAWA_BOUNDS = '34.0,133.4|34.6,134.5'
SLEEP_SEC = 0.12   # レート制限に配慮した待機（約8件/秒）

# 店名では正しく引けない店の「住所で引く」上書き（id → 住所）。
# 店名検索がAPPROXIMATE等になった店をここに書き、`--ids <id>` で取り直す。
ADDR_OVERRIDE = {
    67:  'さぬき市大川町田面2250-2',      # うどん そらいけ
    103: '仲多度郡琴平町718',            # 手打ちうどん てんてこ舞
    136: '三豊市高瀬町比地1583-1',        # 三好うどん（CSVの name は「うどん」）
    112: '高松市上福岡町',               # はなまるうどん TKMT本店（番地未確定・跡地の町名）
}


def geocode(query, api_key):
    """query（店名＋市 か 住所）で照会。(lat, lng, quality, formatted) を返す。見つからなければ None。"""
    params = {
        'address': query,
        'key': api_key,
        'language': 'ja',
        'region': 'jp',
        'bounds': KAGAWA_BOUNDS,
    }
    url = API_URL + '?' + urllib.parse.urlencode(params)
    with urllib.request.urlopen(url, timeout=20) as resp:
        data = json.load(resp)

    status = data.get('status')
    if status == 'ZERO_RESULTS':
        return None
    if status != 'OK':
        # OVER_QUERY_LIMIT / REQUEST_DENIED / INVALID_REQUEST 等はここで止める
        raise RuntimeError(f'API status={status}: {data.get("error_message", "")}')

    r = data['results'][0]
    loc = r['geometry']['location']
    quality = r['geometry'].get('location_type', '')   # ROOFTOP / RANGE_INTERPOLATED / GEOMETRIC_CENTER / APPROXIMATE
    if r.get('partial_match'):
        quality += '/PARTIAL'
    return loc['lat'], loc['lng'], quality, r.get('formatted_address', '')


def main():
    force = '--force' in sys.argv
    limit = None
    if '--limit' in sys.argv:
        try:
            limit = int(sys.argv[sys.argv.index('--limit') + 1])
        except (IndexError, ValueError):
            print('エラー: --limit の後に件数を指定してください（例: --limit 5）')
            sys.exit(1)
    only_ids = None
    if '--ids' in sys.argv:
        try:
            only_ids = {s.strip() for s in sys.argv[sys.argv.index('--ids') + 1].split(',') if s.strip()}
        except IndexError:
            print('エラー: --ids の後にIDを指定してください（例: --ids 67,103,136,112）')
            sys.exit(1)

    api_key = os.environ.get('GOOGLE_GEOCODING_API_KEY', '').strip()
    if not api_key:
        print('エラー: 環境変数 GOOGLE_GEOCODING_API_KEY が未設定です。')
        print('  export GOOGLE_GEOCODING_API_KEY="発行したキー" を実行してから、もう一度お試しください。')
        sys.exit(1)

    if not os.path.exists(CSV_FILE):
        print(f'エラー: {CSV_FILE} が見つかりません。')
        sys.exit(1)

    with open(CSV_FILE, encoding='utf-8-sig', newline='') as f:
        reader = csv.DictReader(f)
        fieldnames = list(reader.fieldnames)
        rows = list(reader)

    # lat/lng 列が無ければ追加
    for col in ('lat', 'lng'):
        if col not in fieldnames:
            fieldnames.append(col)

    todo = []
    for row in rows:
        if not (row.get('name') or '').strip():
            continue
        if only_ids is not None:
            # 指定IDだけを対象（既に座標があっても取り直す）
            if (row.get('id') or '').strip() in only_ids:
                todo.append(row)
            continue
        if (row.get('status') or '').strip() == '閉店':
            continue
        has_coord = (row.get('lat') or '').strip() and (row.get('lng') or '').strip()
        if force or not has_coord:
            todo.append(row)
    if limit is not None:
        todo = todo[:limit]

    if not todo:
        print('✅ 追加でジオコーディングが必要な店はありません（全店に座標あり）。')
        return

    print(f'{len(todo)}店をジオコーディングします…（Google Geocoding API）\n')
    ok = 0
    review = []   # 要目視確認（精度が粗い・部分一致）
    failed = []   # 取得できなかった店

    try:
        for i, row in enumerate(todo, 1):
            name = row['name'].strip()
            city = (row.get('city') or '').strip()
            # 住所上書きがあれば住所で、無ければ店名＋市で照会
            try:
                sid = int((row.get('id') or '').strip())
            except ValueError:
                sid = None
            if sid in ADDR_OVERRIDE:
                addr = ADDR_OVERRIDE[sid]
                query = addr if '香川' in addr else addr + ' 香川県'
            else:
                query = f'{name} {city} 香川県'
            try:
                res = geocode(query, api_key)
            except RuntimeError as e:
                # キー無効やクォータ超過は続けても無駄なので中断（ここまでの結果は保存する）
                print(f'\n⛔ 中断: {e}')
                break

            if res is None:
                failed.append(name)
                print(f'[{i}/{len(todo)}] ❌ 見つからず: {name}（{city}）')
            else:
                lat, lng, quality, formatted = res
                row['lat'] = f'{lat:.6f}'
                row['lng'] = f'{lng:.6f}'
                ok += 1
                mark = '✅'
                if 'PARTIAL' in quality or quality.startswith('APPROXIMATE'):
                    mark = '⚠️ '
                    review.append((name, quality, formatted))
                print(f'[{i}/{len(todo)}] {mark} {name} → {lat:.5f},{lng:.5f}  [{quality}] {formatted}')
            time.sleep(SLEEP_SEC)
    finally:
        # 途中で止まっても、それまでの結果は必ず書き戻す
        with open(CSV_FILE, 'w', encoding='utf-8', newline='') as f:
            writer = csv.DictWriter(f, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(rows)

    print(f'\n完了: {ok}店に座標を付与しました。shops.csv を更新済み。')
    if review:
        print(f'\n⚠️ 精度が粗い/部分一致（目視で確認推奨・{len(review)}件）:')
        for name, q, addr in review:
            print(f'  - {name}  [{q}]  {addr}')
    if failed:
        print(f'\n❌ 取得できなかった店（手動で lat/lng を入れてください・{len(failed)}件）:')
        for name in failed:
            print(f'  - {name}')
    print('\n次に: python3 build-shops-data.py で shops-data.js を再生成してください。')


if __name__ == '__main__':
    main()
