#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
#  build-install.sh — Tüm SQL migration'larını TEK dosyada toplar: install.sql
#
#  İki geçişli (sıra bağımlılığını tamamen ortadan kaldırır):
#    BÖLÜM 1: TÜM "CREATE TABLE" blokları (tekilleştirilmiş — her tablo bir kez,
#             ilk/en tam tanım kazanır; full_setup.sql önce gelir).
#    BÖLÜM 2: Geri kalan her şey (ALTER, index, INSERT) — tüm tablolar artık
#             var olduğundan ALTER'lar güvenle çalışır.
#
#  İdempotent: CREATE'ler IF NOT EXISTS, ADD COLUMN'lar IF NOT EXISTS'e
#  normalize edilir, INSERT'ler zaten IGNORE/ON DUPLICATE. FOREIGN_KEY_CHECKS=0
#  ile sarılır. Tekrar tekrar çalıştırmak güvenlidir.
#
#  Yeni migration eklediğinde tekrar çalıştır:  cd sql && bash build-install.sh
# ═══════════════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")"

OUT="install.sql"
TMP="$(mktemp)"

# Dosya sırası: full_setup önce (en tam core tanımlar), sonra diğerleri alfabetik.
# schema.sql hariç (full_setup'ın alt kümesi), install.sql ve build script hariç.
# İNDEXLER (100_) ve VERİ-DÜZELTMELERİ (101_) EN SONA alınır: bunlar başka
# migration'ların ALTER ile eklediği kolonlara (coupon_code, loyalty_tier...)
# bağlıdır; tüm kolon eklemeleri bittikten sonra çalışmalıdır.
LAST_FILES=("100_performance_indexes.sql" "101_fix_deleted_products_active.sql")
ORDER=("full_setup.sql")
while IFS= read -r f; do
  case "$f" in
    full_setup.sql|schema.sql|install.sql) continue ;;
    100_performance_indexes.sql|101_fix_deleted_products_active.sql) continue ;;
  esac
  ORDER+=("$f")
done < <(ls *.sql | LC_ALL=C sort)
for f in "${LAST_FILES[@]}"; do [ -f "$f" ] && ORDER+=("$f"); done

# ── awk: BÖLÜM 1 — CREATE TABLE bloklarını çıkar, tablo adına göre tekilleştir ──
read -r -d '' AWK_CREATES <<'AWK' || true
{
  if (inblk==0 && $0 ~ /CREATE TABLE/) {
    t=$0
    sub(/.*CREATE TABLE[ \t]+IF[ \t]+NOT[ \t]+EXISTS[ \t]+/, "", t)
    sub(/.*CREATE TABLE[ \t]+/, "", t)
    sub(/[ \t(`].*/, "", t); gsub(/`/, "", t)
    curtbl=t; inblk=1; buf=$0 ORS; next
  }
  if (inblk==1) {
    buf=buf $0 ORS
    if ($0 ~ /^\)/) { if(!(curtbl in seen)){printf "%s\n",buf; seen[curtbl]=1} inblk=0; buf="" }
    next
  }
}
AWK

# ── awk: BÖLÜM 2 — CREATE TABLE blokları HARİÇ her şey (+ kontrol satırlarını at) ──
read -r -d '' AWK_REST <<'AWK' || true
{
  if (inblk==0 && $0 ~ /CREATE TABLE/) { inblk=1; next }
  if (inblk==1) { if ($0 ~ /^\)/) inblk=0; next }
  if ($0 ~ /^[ \t]*SET[ \t]+FOREIGN_KEY_CHECKS/) next
  if ($0 ~ /^[ \t]*SET[ \t]+NAMES/) next
  print
}
AWK

{
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo "-- DemoStore — TEK DOSYALIK KURULUM  (otomatik üretildi: build-install.sh)"
  echo "--"
  echo "-- Boş bir veritabanına tek seferde çalıştırın (phpMyAdmin > SQL sekmesi)."
  echo "-- İdempotent: tekrar çalıştırmak güvenlidir (mevcut tablo/kolonları atlar)."
  echo "-- Üretim: $(date '+%Y-%m-%d %H:%M')"
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo ""
  echo "SET NAMES utf8mb4;"
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  echo ""
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo "-- BÖLÜM 1 — TABLOLAR (CREATE TABLE IF NOT EXISTS)"
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo ""
  awk "$AWK_CREATES" "${ORDER[@]}"

  echo ""
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo "-- BÖLÜM 2 — KOLON EKLEMELERİ · İNDEXLER · VARSAYILAN VERİ"
  echo "-- ═══════════════════════════════════════════════════════════════════"
  echo ""
  # ADD COLUMN'ları idempotent (IF NOT EXISTS) yap + kolon-konumu cümlelerini at.
  # "AFTER <kolon>" / "FIRST" yalnızca kozmetik kolon sırasıdır; başka migration'ın
  # eklediği henüz-var-olmayan kolona referans verince hata üretir → kaldırılır.
  # (Kolon adlarıyla çalışıldığı için sıranın işlevsel önemi yoktur.)
  awk "$AWK_REST" "${ORDER[@]}" | perl -pe '
    s/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+/ADD COLUMN /ig;
    s/ADD\s+COLUMN\s+/ADD COLUMN IF NOT EXISTS /ig;
    s/\s+AFTER\s+`?[A-Za-z_][A-Za-z0-9_]*`?(?=\s*,|\s*;|\s*'"'"'|\s*\)|\s*$)//ig;
    s/\s+FIRST(?=\s*,|\s*;|\s*'"'"'|\s*\)|\s*$)//ig;
  '

  echo ""
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} > "$TMP"

mv "$TMP" "$OUT"
echo "✓ $OUT üretildi ($(wc -l < "$OUT" | tr -d ' ') satır, $(grep -c 'CREATE TABLE' "$OUT") tablo)"
