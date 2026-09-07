#!/bin/bash
#
# Empat angka yang menjawab "apakah perbaikan kebocoran 8 September 2026
# benar-benar bekerja". Dijalankan di HOST, bukan di dalam container.
#
# Keempatnya harus MENDATAR, bukan sekadar rendah. Angka rendah yang naik
# perlahan adalah kebocoran yang belum sempat terlihat.
#
# Pemakaian:  ./pantau.sh

set -u

NAMA="${1:-flustra-wa}"
CID="$(docker ps -qf "name=${NAMA}" | head -1)"

if [ -z "$CID" ]; then
    echo "Container yang namanya memuat '${NAMA}' tidak sedang berjalan." >&2
    exit 1
fi

echo "=============================================================="
echo " Pemantauan flustra-wa - $(date -Is)"
echo "=============================================================="
echo ""

# --- 1. Chromium yatim: SATU-SATUNYA angka yang menangkap kebocoran ---------
#
# Sisanya melaporkan sesuatu yang sudah terjadi. Ini melaporkan sesuatu yang
# SEDANG terjadi, berjam-jam sebelum ia jadi server yang tidak bisa di-SSH.
echo "1. CHROMIUM: PROSES PER PROFIL  (duplikat harus tepat 0)"

SEHAT="$(docker exec "$CID" sh -c 'curl -sf --max-time 5 -H "X-Engine-Token: $ENGINE_TOKEN" http://127.0.0.1:${ENGINE_PORT:-3100}/health' 2>/dev/null)"

if [ -z "$SEHAT" ]; then
    echo "   engine tidak menjawab - itu sendiri sudah temuan"
else
    ambil() { echo "$SEHAT" | grep -o "\"$1\":-\?[0-9]*" | cut -d: -f2; }

    SESI="$(ambil sessions)"
    CHR="$(ambil chromium_processes)"
    PROFIL="$(ambil chromium_profiles)"
    DUP="$(ambil chromium_duplicates)"
    YATIM="$(ambil chromium_orphans)"

    echo "   sesi=${SESI:-?}  profil=${PROFIL:-?}  proses=${CHR:-?}"
    echo "   duplikat=${DUP:-tidak diketahui}  yatim=${YATIM:-tidak diketahui}"

    if [ "${DUP:-x}" = "0" ] && [ "${YATIM:-x}" = "0" ]; then
        echo "   -> cocok."
    else
        if [ -n "${DUP:-}" ] && [ "$DUP" -gt 0 ] 2>/dev/null; then
            echo "   -> $DUP PROSES BERLEBIH pada profil yang sama."
            echo "      Dua Chromium pada satu folder kredensial saling menimpa state"
            echo "      WhatsApp Web. Pelanggan melihatnya sebagai scan QR gagal dengan"
            echo "      'Execution context was destroyed', bukan sebagai memori penuh."
        fi
        if [ -n "${YATIM:-}" ] && [ "$YATIM" -gt 0 ] 2>/dev/null; then
            echo "   -> $YATIM profil tanpa sesi: 250-500 MB masing-masing yang"
            echo "      tidak akan kembali sampai container di-restart."
        fi
    fi
fi
echo ""

# --- 2. Zombie ---------------------------------------------------------------
echo "2. ZOMBIE DI HOST  (puluhan wajar; yang penting LAJUnya mendatar)"
echo "   Induknya 'php artisan serve', bukan Chromium dan bukan penjadwal."
echo "   tini di PID 1 tidak menuainya: reaper hanya menuai anak yang induknya"
echo "   sudah mati, dan induk ini masih hidup. Dibiarkan dengan sengaja -"
echo "   pid_max 4.194.304, jadi puluhan zombie tidak berbahaya. Utang teknis."
Z="$(ps -eo stat= 2>/dev/null | grep -c '^Z' || echo 0)"
echo "   $Z"
# Ambangnya longgar dengan sengaja. Yang berbahaya bukan jumlahnya melainkan
# laju yang MELONJAK: itu berarti sesi sering mati-hidup, dan churn Chromium
# adalah gejala yang jauh lebih mahal daripada zombie itu sendiri.
[ "$Z" -gt 500 ] && echo "   -> laju tidak wajar; periksa churn sesi, bukan zombie-nya"
echo ""

# --- 3. Memori container -----------------------------------------------------
#
# Dari cgroup, BUKAN dari jumlah RSS. RSS menghitung ganda memori yang dibagi
# antar proses Chromium dan selalu melaporkan lebih besar dari kenyataan.
echo "3. MEMORI CONTAINER  (lantai tidak boleh naik lintas hari)"
docker exec "$CID" sh -c '
  for f in current peak max; do
    v=$(cat /sys/fs/cgroup/memory.$f 2>/dev/null)
    [ -n "$v" ] && [ "$v" != "max" ] && echo "   memory.$f = $((v / 1048576)) MB" || echo "   memory.$f = $v"
  done
  s=$(cat /sys/fs/cgroup/memory.swap.current 2>/dev/null); [ -n "$s" ] && echo "   swap      = $((s / 1048576)) MB"
    # Berapa kali container MENYENTUH plafonnya. Sinyal yang terlewat pada
    # 8 September 2026: max=1024 dengan oom_kill=0 berarti container dipaksa
    # mereklaim memori seribu kali tanpa pernah dibunuh, dan reklamasi terus-
    # menerus dengan swap penuh persis yang membuat semuanya lambat.
    echo "   --- memory.events ---"
    sed "s/^/   /" /sys/fs/cgroup/memory.events 2>/dev/null
' 2>/dev/null || echo "   cgroup tidak terbaca"
echo ""

# --- 4. Tabel yang tumbuh ----------------------------------------------------
#
# Setelah retensi berjalan, keduanya harus mencapai dataran tetap
# (retensi x laju harian) lalu BERHENTI naik. Masih naik lurus = pemangkasnya
# tidak jalan, atau tidak pernah selesai.
echo "4. TABEL YANG TUMBUH  (mendatar setelah retensi berjalan)"
docker exec "$CID" php artisan tinker --execute="
foreach (['webhook_deliveries','audit_logs','messages','notifications','failed_jobs','jobs','cache'] as \$t) {
    \$r = DB::selectOne('SELECT COUNT(*) n FROM '.\$t);
    echo '   '.str_pad(\$t, 20).number_format(\$r->n).PHP_EOL;
}
" 2>/dev/null | grep -v '^$' || echo "   tidak terbaca"

echo ""
echo "=============================================================="
echo "Tren lantai memori beberapa hari: scripts/ringkas-kapasitas.sh"
echo "=============================================================="
