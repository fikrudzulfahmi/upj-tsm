#!/usr/bin/env bash
# Smoke test end-to-end API (jalur bisnis utama) — dipakai untuk verifikasi lokal.
set -u
BASE="http://127.0.0.1:8000/api/v1"
OUT="$(dirname "$0")/.smoke-out.json"

api() { # api METHOD PATH [BODY]
  local method="$1" path="$2" body="${3:-}"
  if [ -n "$body" ]; then
    curl -s -m 30 -X "$method" "$BASE$path" \
      -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
      -H "Content-Type: application/json" -d "$body" -o "$OUT" -w "%{http_code}"
  else
    curl -s -m 30 -X "$method" "$BASE$path" \
      -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -o "$OUT" -w "%{http_code}"
  fi
}

show() { /c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo json_encode($j, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);' "$OUT" | head -c "${2:-400}"; echo; }

echo "== 1. login owner =="
TOKEN=$(curl -s -m 20 -X POST "$BASE/auth/login" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"login":"owner@bengkel.test","password":"owner12345"}' | /c/php83/php.exe -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["data"]["token"] ?? "";')
echo "token: ${TOKEN:0:18}..."

echo "== 2. master: mekanik + jasa + sparepart =="
api POST /mechanics '{"name":"Budi Santoso","phone":"081234567890"}' >/dev/null; echo "mekanik: $(show 120)"
api POST /services '{"name":"Servis Ringan","price":50000}' >/dev/null; echo "jasa: $(show 160)"
api POST /spareparts '{"sku":"OLI-001","name":"Oli Mesin 10W-40","unit":"liter","buy_price":45000,"sell_price":60000,"min_stock":5}' >/dev/null; echo "part: $(show 200)"
PART_ID=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["id"] ?? "";' "$OUT")
JASA_ID=$(curl -s -m 20 "$BASE/services/pilihan?search=Ringan" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | /c/php83/php.exe -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["data"][0]["id"] ?? "";')

echo "== 3. stok awal lewat input sparepart (barang masuk) =="
api POST /part-purchases "{\"purchase_date\":\"$(date +%F)\",\"supplier_name\":\"Toko Oli Jaya\",\"items\":[{\"sparepart_id\":$PART_ID,\"qty\":12,\"buy_price\":45000}]}" >/dev/null
echo "pembelian: $(show 250)"

echo "== 4. pelanggan + kendaraan =="
api POST /customers '{"name":"Andi Pratama","gender":"L","phone":"0812-1111-2222","address":"Jl. Merdeka 1","vehicles":[{"plate_number":"ab 1234 cd","type":"motor","brand":"Honda","model":"Beat","year":2021}]}' >/dev/null
echo "pelanggan: $(show 300)"
CUST=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["id"] ?? "";' "$OUT")
VEH=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["vehicles"][0]["id"] ?? "";' "$OUT")
NOPOL=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["vehicles"][0]["plate_number"] ?? "";' "$OUT")
echo "customer=$CUST vehicle=$VEH nopol=$NOPOL (harus uppercase tanpa spasi)"

echo "== 5. check up (draft) =="
TEMPLATE=$(curl -s -m 20 "$BASE/checkup-templates/untuk-kendaraan?vehicle_type=motor" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -o "$OUT" -w "%{http_code}")
TPL_ID=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["id"] ?? "";' "$OUT")
echo "template=$TPL_ID"
HASIL=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); $out=[]; foreach($j["data"]["items"] as $i){ $out[]=["category"=>$i["category"],"item_name"=>$i["name"],"status"=>"ok","sort_order"=>$i["sort_order"]]; } echo json_encode($out);' "$OUT")
api POST /checkups "{\"customer_id\":$CUST,\"vehicle_id\":$VEH,\"checkup_template_id\":$TPL_ID,\"checkup_date\":\"$(date +%F)\",\"odometer\":12500,\"complaint\":\"Mesin berisik saat digas\",\"results\":$(printf '%s' "$HASIL" | sed 's/"rusak"/"perlu_perhatian"/')}" >/dev/null
echo "checkup: $(show 260)"
CHECKUP=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["id"] ?? "";' "$OUT")
echo "checkup_id=$CHECKUP"

echo "== 6. finish check up -> lanjut service (buat SA otomatis) =="
api POST "/checkups/$CHECKUP/finish" '{"result":"continue_service"}' >/dev/null
echo "$(show 300)"
SA=$(/c/php83/php.exe -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["data"]["service_order_id"] ?? "";' "$OUT")
echo "SA otomatis=$SA"

echo "== 7. lengkapi SA (jasa + part) =="
MEK=$(curl -s -m 20 "$BASE/mechanics" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | /c/php83/php.exe -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["data"][0]["id"] ?? "";')
api PUT "/service-orders/$SA" "{\"customer_id\":$CUST,\"vehicle_id\":$VEH,\"mechanic_id\":$MEK,\"fuel_level\":5,\"services\":[{\"service_id\":$JASA_ID,\"qty\":1}],\"parts\":[{\"sparepart_id\":$PART_ID,\"qty\":2}]}" >/dev/null
echo "$(show 420)"

echo "== 8. start -> finish (stok keluar) =="
api POST "/service-orders/$SA/start" >/dev/null; echo "start: $(show 120)"
api POST "/service-orders/$SA/finish" >/dev/null; echo "finish: $(show 200)"
curl -s -m 20 "$BASE/spareparts/$PART_ID" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | /c/php83/php.exe -r '$j=json_decode(stream_get_contents(STDIN),true); echo "stok setelah finish: ".$j["data"]["stock"]."\n";'

echo "== 9. bayar (income + poin) =="
api POST "/service-orders/$SA/pay" '{"payment_method":"cash"}' >/dev/null; echo "$(show 320)"

echo "== 10. jadikan member + tukar poin =="
api POST "/customers/$CUST/membership" >/dev/null; echo "member: $(show 300)"
