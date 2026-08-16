#!/bin/bash

# Titik masuk container produksi flustra-wa.
#
# SATU resource Coolify menjalankan EMPAT proses: web, engine WhatsApp (Node +
# Chromium), worker antrean, dan penjadwal. Sebelum 17 Agustus 2026 ini terbagi
# jadi tiga resource (`flustra-wa`, `flustra-wa-engine`, `flustra-wa-worker`),
# dan alasan penyatuannya sama dengan yang sudah dipakai flustra-erp dan
# flustra-clientportal: **setiap resource Coolify membangun ulang aplikasinya
# sendiri**. Tiga resource berarti tiga kali `composer install` + `npm ci` tiap
# deploy, tiga image tersimpan di disk, dan tiga lonjakan CPU berbarengan di VPS
# 2 vCPU yang juga menampung tujuh aplikasi Flustra lain. Beban proses latarnya
# sendiri kecil; biaya build-nya yang tidak.
#
# docs/ARSITEKTUR.md §2 dulu menuliskan alasan sebaliknya. Dua dari tiga
# alasannya dijawab di berkas ini, satu memang dilepas:
#
#   1. "Chromium sesekali crash dan menjatuhkan aplikasi juga." — tidak lagi.
#      Engine berjalan di dalam loop pengawas di bawah; matinya engine
#      menjalankan engine lagi dalam 3 detik dan tidak menyentuh proses web.
#      Yang tetap benar: Chromium boros RAM, dan di satu container ia berebut
#      RAM dengan PHP. Karena itu WA_MAX_SESSIONS harus disetel jujur terhadap
#      RAM yang ada — lihat docs/DEPLOYMENT.md.
#   2. "Container Laravel harus memasang belasan library sistem." — benar, dan
#      itu memang biayanya (lihat nixpacks.toml). Tapi biaya itu dibayar SEKALI,
#      menggantikan tiga build penuh yang dibayar setiap deploy.
#   3. "Skala keduanya berbeda." — betul secara prinsip, dan tidak berlaku di
#      sini: satu VPS, satu mesin, tidak ada yang bisa diskalakan sendiri-sendiri.
#      Kalau suatu saat trafik menuntutnya, pisahkan lagi engine-nya saja.
#
# Konsekuensi yang harus disadari: kalau proses WEB mati, seluruh container ikut
# berhenti — termasuk engine. Itu disengaja; Coolify menghidupkannya kembali
# dengan Restart Policy `always`, dan sesi WhatsApp pulih sendiri dari cadangan
# di Laravel. Yang TIDAK boleh terjadi adalah container mati tanpa engine sempat
# menyimpan kredensial; itu yang ditangani `matikan()` di bawah.

set -u

cd "$(dirname "$0")" || exit 1

mkdir -p storage/logs

PORT_WEB="${PORT_WEB:-80}"
URL_KESEHATAN="http://127.0.0.1:${PORT_WEB}/up"

# Penanda bahwa container sedang berhenti dengan sengaja. Tanpa ini, loop
# pengawas akan dengan patuh menghidupkan lagi proses yang baru saja kita bunuh.
TANDA_BERHENTI="/tmp/flustra-wa.berhenti"
PIDFILE_ENGINE="/tmp/flustra-wa-engine.pid"
rm -f "$TANDA_BERHENTI" "$PIDFILE_ENGINE"

# --- Engine WhatsApp (Node + Chromium) ------------------------------------
#
# Engine menanyakan daftar sesi ke Laravel saat boot (`/internal/engine/bootstrap`)
# dan TIDAK pernah mengulang panggilan itu kalau gagal — ia cuma mencatat
# "engine tetap jalan tanpa sesi" lalu diam. Selama engine jadi resource
# terpisah hal itu jarang menggigit karena Laravel sudah lama hidup duluan.
# Dalam satu container justru sebaliknya: keduanya lahir berbarengan, dan tanpa
# penantian di bawah ini setiap deploy dimulai dengan nol sesi aktif sampai ada
# yang menekan tombol Hubungkan — persis hal yang gateway ini dibuat untuk
# menghilangkan.
#
# Penantiannya dibatasi 120 detik supaya web yang benar-benar rusak tidak
# membuat engine ikut diam selamanya; sesudah itu engine jalan apa adanya dan
# `bootstrap()` di sisi Node punya percobaan ulangnya sendiri.
#
# Chromium diunduh Puppeteer saat `npm ci` di dalam engine/, dan secara bawaan
# mendarat di $HOME/.cache/puppeteer — yaitu /root/.cache/puppeteer. Selama
# engine punya resource sendiri, folder itu ikut masuk ke image akhir. Sejak
# base directory-nya jadi `/`, yang dibawa Nixpacks ke image akhir hanya /app;
# unduhan di /root/.cache hilang, dan engine baru menyadarinya saat sesi
# pertama dijalankan.
#
# Gejalanya buruk untuk produk yang dijual: pesan Puppeteer berbahasa Inggris
# muncul apa adanya di kartu sesi milik pelanggan ("Could not find Chrome ..."),
# sementara log tidak menyebut apa-apa saat boot. Pemeriksaan di bawah
# memindahkan kegagalan itu ke tempat yang benar — saat container menyala,
# dengan perbaikannya disebutkan langsung.
#
# Sengaja TIDAK mengunduh sendiri sebagai penyelamat: itu menyembunyikan build
# yang salah dan menambah ±170 MB unduhan pada setiap restart container.
periksa_chromium() {
    local jalur
    jalur="$(cd engine && node -e 'console.log(require("puppeteer").executablePath())' 2>/dev/null)"

    if [ -n "$jalur" ] && [ -x "$jalur" ]; then
        echo "[start.sh] Chromium ditemukan: $jalur"
        return 0
    fi

    cat >&2 <<'PERINGATAN'
[start.sh] ============================================================
[start.sh] CHROMIUM TIDAK DITEMUKAN. Seluruh sesi WhatsApp akan gagal.
[start.sh]
[start.sh] Web, API, dan dashboard tetap jalan — yang mati cuma kemampuan
[start.sh] menyambung ke WhatsApp. Kredensial sesi yang sudah ada AMAN di
[start.sh] cadangan Laravel; ia pulih sendiri begitu Chromium ada.
[start.sh]
[start.sh] Perbaikannya di Coolify, dua kolom:
[start.sh]
[start.sh]   Environment  : PUPPETEER_CACHE_DIR=/app/engine/.puppeteer
[start.sh]   Install Cmd  : ... && PUPPETEER_CACHE_DIR=/app/engine/.puppeteer \
[start.sh]                  npm --prefix engine ci --omit=dev
[start.sh]
[start.sh] Tanpa PUPPETEER_CACHE_DIR, Puppeteer mengunduh Chromium ke
[start.sh] /root/.cache/puppeteer — folder yang TIDAK ikut ke image akhir.
[start.sh] ============================================================
PERINGATAN

    return 1
}

supervisi_engine() {
    local tunggu=0

    until curl -sfo /dev/null --max-time 2 "$URL_KESEHATAN"; do
        [ -f "$TANDA_BERHENTI" ] && return

        tunggu=$((tunggu + 1))

        if [ "$tunggu" -ge 120 ]; then
            echo "[start.sh] Web belum menjawab setelah 120 detik; engine dijalankan apa adanya."
            break
        fi

        sleep 1
    done

    periksa_chromium || true

    while [ ! -f "$TANDA_BERHENTI" ]; do
        node engine/src/server.js &
        echo $! > "$PIDFILE_ENGINE"
        wait $! || true

        rm -f "$PIDFILE_ENGINE"

        [ -f "$TANDA_BERHENTI" ] && break

        echo "[start.sh] engine berhenti, dijalankan ulang dalam 3 detik."
        sleep 3
    done
}

# --- Worker antrean -------------------------------------------------------
#
# WAJIB ADA. QUEUE_CONNECTION=database, jadi tanpa worker setiap pesan berhenti
# di status `queued` selamanya dan tidak ada satu pun webhook yang terkirim.
#
# `--max-time=3600` membuatnya berhenti terjadwal tiap jam lalu dijalankan ulang
# oleh loop — cara paling murah menahan kebocoran memori proses yang hidup
# berhari-hari. `--timeout=240` menyamai ENGINE_SEND_TIMEOUT ditambah kelonggaran
# untuk antrean anti-ban engine.
supervisi_worker() {
    while [ ! -f "$TANDA_BERHENTI" ]; do
        php artisan queue:work --sleep=3 --tries=3 --timeout=240 --max-time=3600

        [ -f "$TANDA_BERHENTI" ] && break

        echo "[start.sh] queue:work berhenti, dijalankan ulang dalam 2 detik."
        sleep 2
    done
}

# --- Penjadwal ------------------------------------------------------------
#
# Menjalankan SyncSessionStatusJob tiap menit dan PruneOldRecordsJob tiap dini
# hari (routes/console.php). Yang pertama adalah jaring pengaman status sesi:
# kalau engine mati mendadak, tidak ada event `disconnected` yang terkirim dan
# dashboard akan terus menampilkan sesi sebagai terhubung padahal sudah tidak.
#
# Menggantikan Scheduled Task di Coolify. Scheduled Task berjalan di container
# yang dibuat khusus untuk itu — build ulang kecil tiap menit; ini cuma satu
# proses PHP yang tidur di antara pemeriksaan.
supervisi_penjadwal() {
    while [ ! -f "$TANDA_BERHENTI" ]; do
        php artisan schedule:work

        [ -f "$TANDA_BERHENTI" ] && break

        echo "[start.sh] schedule:work berhenti, dijalankan ulang dalam 2 detik."
        sleep 2
    done
}

# --- Penghentian yang rapi ------------------------------------------------
#
# Ini bagian yang tidak boleh disederhanakan. `client.destroy()` TIDAK menyimpan
# apa pun pada RemoteAuth — ia cuma menghentikan timer backup — jadi
# satu-satunya kesempatan menyimpan kredensial yang berubah sejak backup berkala
# terakhir adalah SIGTERM yang sampai ke proses Node. Kalau shell ini mati tanpa
# meneruskan sinyalnya, engine kena SIGKILL dan perubahan sampai lima menit
# terakhir hilang.
#
# Engine didahulukan dan ditunggu. Batasnya 10 detik karena Docker hanya memberi
# ~10 detik sebelum SIGKILL, dan engine sendiri sudah membatasi penyimpanan 8
# detik per sesi. Kalau Coolify disetel dengan grace period lebih panjang, angka
# ini boleh ikut dinaikkan.
matikan() {
    trap '' TERM INT

    echo "[start.sh] Sinyal berhenti diterima; menyimpan kredensial sesi lebih dulu."

    touch "$TANDA_BERHENTI"

    local pid_engine
    pid_engine="$(cat "$PIDFILE_ENGINE" 2>/dev/null || true)"

    if [ -n "$pid_engine" ]; then
        kill -TERM "$pid_engine" 2>/dev/null || true

        for _ in $(seq 1 10); do
            kill -0 "$pid_engine" 2>/dev/null || break
            sleep 1
        done
    fi

    kill -TERM "${PID_WEB:-}" "${PID_WORKER:-}" "${PID_PENJADWAL:-}" "${PID_ENGINE:-}" 2>/dev/null || true

    exit 0
}

# --- Menyalakan -----------------------------------------------------------
#
# Web dijalankan di latar belakang, bukan lewat `exec`, supaya shell ini tetap
# hidup sebagai PID 1 dan bisa menangkap SIGTERM untuk `matikan()` di atas.
# (flustra-erp dan flustra-clientportal memakai `exec` karena tidak punya proses
# yang butuh penghentian rapi; di sini engine punya.)
#
# `--no-reload` bukan sekadar mematikan pengawas berkas. Laravel MENOLAK
# menghormati PHP_CLI_SERVER_WORKERS tanpa flag ini — `ServeCommand::initialize()`
# mengembalikan `false` dan mencetak peringatan yang hanya muncul sekali di awal
# log, lalu server bawaan PHP jalan dengan satu proses seperti biasa. Gejalanya
# tidak ada: aplikasinya berfungsi normal, cuma melayani satu permintaan pada
# satu waktu, dan callback engine (qr, ready, pesan masuk, ack) antre di
# belakang halaman dashboard yang sedang dibuka orang.
#
# Yang dilepas dengan mematikan pengawas itu: server tidak lagi restart sendiri
# saat `.env` berubah. Di produksi berkas itu memang tidak pernah berubah saat
# container hidup — perubahan env datang lewat redeploy Coolify.
echo "[start.sh] Menyalakan web, engine WhatsApp, worker, dan penjadwal dalam satu container."

php artisan serve --host=0.0.0.0 --port="$PORT_WEB" --no-reload &
PID_WEB=$!

supervisi_engine &
PID_ENGINE=$!

supervisi_worker &
PID_WORKER=$!

supervisi_penjadwal &
PID_PENJADWAL=$!

trap matikan TERM INT

# Menunggu proses web. Kalau ia mati, tidak ada gunanya container tetap hidup:
# engine tanpa Laravel tidak bisa memulihkan sesi, dan worker tanpa Laravel
# tidak punya apa-apa untuk dikerjakan. Biarkan container berhenti supaya
# Restart Policy Coolify membangunkan semuanya bersih-bersih.
wait "$PID_WEB"

echo "[start.sh] Proses web berhenti; menutup sisa proses."
matikan
