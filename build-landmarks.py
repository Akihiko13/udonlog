#!/usr/bin/env python3
# ===========================================================================
# landmarks.csv → landmarks.js（観光地マスタ）を生成するスクリプト
# ---------------------------------------------------------------------------
# 「○○（観光地）に近いうどん屋」検索のための観光地リスト。各観光地の座標を
# Google Geocoding で1回だけ取得して landmarks.csv に書き戻し、landmarks.js を
# 生成する。検索時はこのリストの座標を使うだけなので API 消費はゼロ。
#
# 【使い方】udonlog フォルダで:
#   （座標がまだ空の観光地がある場合＝キーが必要）
#     export GOOGLE_GEOCODING_API_KEY="発行したキー"
#     python3 build-landmarks.py
#   （すべて座標済みなら、キー無しで landmarks.js の再生成だけ実行）
#     python3 build-landmarks.py
#
# オプション: --force（座標があっても全件取り直す）
#
# 追加の pip インストールは不要（標準ライブラリのみ）。
# ※ APIキーはコードに書かず、必ず環境変数で渡すこと（キーを git に含めない）。
# ===========================================================================

import csv
import json
import os
import sys
import time
import urllib.parse
import urllib.request

CSV_FILE = 'landmarks.csv'
OUT_FILE = 'landmarks.js'
API_URL = 'https://maps.googleapis.com/maps/api/geocode/json'
KAGAWA_BOUNDS = '34.0,133.4|34.6,134.5'
SLEEP_SEC = 0.12

# 施設名のままでは引きにくい観光地は、検索クエリを上書き（id → 照会文字列）
QUERY_OVERRIDE = {
    9:  '玉藻公園 高松市',              # 高松城跡・玉藻公園
    20: '金丸座 琴平町',               # 旧金毘羅大芝居（金丸座）
    25: '讃州井筒屋敷 東かがわ市',        # 引田の古い町並み・讃州井筒屋敷
    26: '高松港 フェリーターミナル 高松市',  # 高松港
}

HEADER = '''// ===========================================================================
// うどログ 観光地マスタ（「スポットから近い順」検索用）
// ---------------------------------------------------------------------------
// このファイルは build-landmarks.py によって landmarks.csv から自動生成されます。
// 直接編集せず、landmarks.csv を編集して `python3 build-landmarks.py` を実行して
// ください。座標(lat/lng)は Google Geocoding で取得します。
// ===========================================================================

const LANDMARKS = [
'''
FOOTER = '];\n'


def esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"')


def geocode(query, api_key):
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
        raise RuntimeError(f'API status={status}: {data.get("error_message", "")}')
    r = data['results'][0]
    loc = r['geometry']['location']
    quality = r['geometry'].get('location_type', '')
    if r.get('partial_match'):
        quality += '/PARTIAL'
    return loc['lat'], loc['lng'], quality, r.get('formatted_address', '')


def main():
    force = '--force' in sys.argv
    if not os.path.exists(CSV_FILE):
        print(f'エラー: {CSV_FILE} が見つかりません。')
        sys.exit(1)

    with open(CSV_FILE, encoding='utf-8-sig', newline='') as f:
        reader = csv.DictReader(f)
        fieldnames = list(reader.fieldnames)
        rows = list(reader)

    todo = []
    for row in rows:
        if not (row.get('name') or '').strip():
            continue
        has_coord = (row.get('lat') or '').strip() and (row.get('lng') or '').strip()
        if force or not has_coord:
            todo.append(row)

    # 座標が必要なものがあれば取得（キー必須）
    if todo:
        api_key = os.environ.get('GOOGLE_GEOCODING_API_KEY', '').strip()
        if not api_key:
            print(f'ℹ️ 座標が未取得の観光地が {len(todo)} 件あります。')
            print('   環境変数 GOOGLE_GEOCODING_API_KEY を設定して再実行すると座標を取得します。')
            print('   （今回は座標なしのまま landmarks.js を生成します）')
        else:
            print(f'{len(todo)}件の観光地をジオコーディングします…（Google Geocoding API）\n')
            review, failed = [], []
            try:
                for i, row in enumerate(todo, 1):
                    name = row['name'].strip()
                    try:
                        sid = int((row.get('id') or '').strip())
                    except ValueError:
                        sid = None
                    query = QUERY_OVERRIDE.get(sid, f'{name} 香川県')
                    if '香川' not in query:
                        query += ' 香川県'
                    try:
                        res = geocode(query, api_key)
                    except RuntimeError as e:
                        print(f'\n⛔ 中断: {e}')
                        break
                    if res is None:
                        failed.append(name)
                        print(f'[{i}/{len(todo)}] ❌ 見つからず: {name}')
                    else:
                        lat, lng, quality, formatted = res
                        row['lat'] = f'{lat:.6f}'
                        row['lng'] = f'{lng:.6f}'
                        mark = '✅'
                        if 'PARTIAL' in quality or quality.startswith('APPROXIMATE'):
                            mark = '⚠️ '
                            review.append((name, quality, formatted))
                        print(f'[{i}/{len(todo)}] {mark} {name} → {lat:.5f},{lng:.5f}  [{quality}] {formatted}')
                    time.sleep(SLEEP_SEC)
            finally:
                with open(CSV_FILE, 'w', encoding='utf-8', newline='') as f:
                    writer = csv.DictWriter(f, fieldnames=fieldnames)
                    writer.writeheader()
                    writer.writerows(rows)
            if review:
                print(f'\n⚠️ 精度が粗い/部分一致（目視で確認推奨・{len(review)}件）:')
                for name, q, addr in review:
                    print(f'  - {name}  [{q}]  {addr}')
            if failed:
                print(f'\n❌ 取得できず（手動で lat/lng を入れてください・{len(failed)}件）:')
                for name in failed:
                    print(f'  - {name}')

    # landmarks.js を生成
    lines = []
    n_coord = 0
    for r in rows:
        if not (r.get('name') or '').strip():
            continue
        parts = [
            f'id:{int(r["id"])}',
            f'name:"{esc(r["name"].strip())}"',
            f'kana:"{esc((r.get("kana") or "").strip())}"',
            f'category:"{esc((r.get("category") or "").strip())}"',
        ]
        lat, lng = (r.get('lat') or '').strip(), (r.get('lng') or '').strip()
        if lat and lng:
            parts.append(f'lat:{float(lat):.6f}')
            parts.append(f'lng:{float(lng):.6f}')
            n_coord += 1
        lines.append('  { ' + ', '.join(parts) + ' },')

    with open(OUT_FILE, 'w', encoding='utf-8') as f:
        f.write(HEADER)
        f.write('\n'.join(lines) + '\n')
        f.write(FOOTER)

    total = sum(1 for r in rows if (r.get('name') or '').strip())
    print(f'\n✅ {OUT_FILE} を生成しました（{total}件中 {n_coord}件に座標あり）')
    if n_coord < total:
        print('   座標が空の観光地は検索候補に出しません（キーを設定して再実行で埋まります）。')


if __name__ == '__main__':
    main()
