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

# --- PID 1 yang menuai anak yatim -----------------------------------------
#
# Kalau proses Node mati mendadak (OOM kill / SIGKILL), seluruh subproses
# Chromium-nya di-reparent ke PID 1. Bash sebagai PID 1 hanya menunggu empat pid
# yang dikenalnya, jadi sisanya menumpuk sebagai zombie: 718 di antaranya
# terbaca di server saat insiden 7 September 2026.
#
# Zombie itu sendiri BUKAN yang menjatuhkan server — `kernel.pid_max` di sana
# 4.194.304, jadi 3.111 proses tidak pernah mendekati batas apa pun, dan yang
# menjatuhkannya murni memori. Ia penanda churn Chromium, dan penanda yang
# dibiarkan bertumpuk membuat setiap pengukuran lain lebih sulit dibaca.
#
# Jalur resminya `--init` milik Docker, dan itu tidak tersedia: Coolify 4.3.14
# di server ini tidak punya kolom Custom Docker Options. Jadi start.sh
# menjalankan ulang dirinya sendiri di bawah tini. Keuntungannya ia ikut masuk
# version control, bukan tersimpan di satu kolom UI yang hilang tanpa jejak.
#
# tini meneruskan SIGTERM ke anaknya, jadi `matikan()` di bawah tetap berjalan
# seperti biasa. Kalau tini tidak terpasang, seluruh blok ini dilewati dan
# perilakunya persis seperti sebelumnya — penyapu Chromium di bawah yang
# menanggung sisanya.
#
# `bash "$0"` dan BUKAN `"$0"` saja. Ini sudah mematikan produksi sekali,
# 8 September 2026:
#
#     [FATAL tini (9)] exec ./start.sh failed: Permission denied
#
# Berkas ini bermode 100644 di git — tidak executable — dan selama ini tidak
# pernah jadi masalah karena Nixpacks memanggilnya lewat `bash start.sh`.
# Meng-exec-nya langsung menuntut bit yang tidak pernah ada, dan container masuk
# restart loop sebelum satu baris pun sempat berjalan. Menjalankannya lewat
# `bash` membuat blok ini tidak bergantung pada mode berkas sama sekali.
#
# Mode-nya juga sudah diperbaiki jadi 100755 (`git update-index --chmod=+x`) dan
# dijaga tests/Unit/BerkasSkripTest.php, tapi dua-duanya sengaja: yang satu
# membuat kegagalan mustahil, yang lain membuatnya terlihat kalau mode itu
# hilang lagi.
if [ "$$" = "1" ] && [ -z "${FLUSTRA_TINI:-}" ] && command -v tini >/dev/null 2>&1; then
    export FLUSTRA_TINI=1
    echo "[start.sh] Menjalankan ulang di bawah tini supaya PID 1 menuai proses yatim."
    exec tini -s -- bash "$0" "$@"
fi

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

# Membunuh Chromium yang tertinggal dari proses engine sebelumnya.
#
# INVARIAN YANG MEMBUAT INI AMAN: fungsi ini hanya dipanggil tepat sebelum
# `node engine/src/server.js` dijalankan, yaitu saat tidak ada satu pun engine
# yang hidup. Pada saat itu, setiap Chromium yang masih berjalan dengan profil
# kita SUDAH PASTI yatim — tidak ada lagi proses yang memegang referensinya dan
# tidak akan pernah ada yang menutupnya. Jangan pernah memanggilnya dari tempat
# lain; dipanggil saat engine hidup, ia memutus sesi pelanggan.
#
# Polanya sengaja sesempit mungkin: `--user-data-dir` yang memuat `wwebjs_auth`.
# VPS ini menampung 20+ container lain, dan walau container terisolasi, pola
# `pkill chrome` adalah kebiasaan yang cepat atau lambat dijalankan di tempat
# yang salah.
#
# Dua tahap. Yang pertama membunuh proses browser (satu-satunya yang membawa
# --user-data-dir). Renderer biasanya ikut mati sendiri begitu kanal IPC-nya
# tertutup; tahap kedua menyapu yang tidak, dengan syarat induknya sudah PID 1
# — tanda pasti bahwa ia yatim, bukan anak sah dari engine yang baru menyala.
sapu_chromium_yatim() {
    local jumlah=0
    # Akar /proc bisa diarahkan ke pohon tiruan untuk memverifikasi pemilihannya
    # tanpa Chromium sungguhan. Produksi tidak pernah menyetel variabel ini.
    local proc="${PROC_ROOT:-/proc}"

    if command -v pkill >/dev/null 2>&1; then
        pkill -9 -f -- '--user-data-dir=[^ ]*wwebjs_auth' 2>/dev/null && jumlah=1
    fi

    # Tahap dua: sisa proses Chromium yatim (PPID 1) di bawah folder puppeteer.
    local pid ppid cmd
    for pid in $(ls "$proc" 2>/dev/null | grep -E '^[0-9]+$'); do
        [ -r "$proc/$pid/cmdline" ] || continue

        cmd="$(tr '\0' ' ' < "$proc/$pid/cmdline" 2>/dev/null)"

        case "$cmd" in
            *puppeteer*chrome*|*wwebjs_auth*) ;;
            *) continue ;;
        esac

        ppid="$(awk '/^PPid:/{print $2}' "$proc/$pid/status" 2>/dev/null)"

        [ "$ppid" = "1" ] || continue

        kill -9 "$pid" 2>/dev/null && jumlah=$((jumlah + 1))
    done

    if [ "$jumlah" -gt 0 ]; then
        echo "[start.sh] Menyapu Chromium yatim dari proses engine sebelumnya."
    fi
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
        # Sebelum menyalakan engine, bukan sesudah: pada titik ini tidak ada
        # engine yang hidup, jadi Chromium mana pun yang cocok pasti yatim.
        sapu_chromium_yatim

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
# `schedule:run` dalam loop bash, BUKAN `schedule:work`.
#
# Keduanya melakukan hal yang sama; bedanya siapa yang jadi induk proses.
# `ScheduleWorkCommand` (vendor/laravel/framework/.../ScheduleWorkCommand.php:61)
# menelurkan satu proses PHP anak tiap menit lewat
# `Process::fromShellCommandline(...)->start()`. Dalam bentuk di bawah, induknya
# bash, dan bash memanggil wait() untuk setiap perintah yang dijalankannya.
#
# JANGAN membaca ini sebagai perbaikan zombie. Zombie yang terekam di produksi
# 8 September 2026 induknya `php artisan serve`, bukan penjadwal — sudah
# diidentifikasi langsung dari server:
#
#     25 zombie <- pid 2082    php artisan serve --host=0.0.0.0 --port=80
#     13 zombie <- pid 510122  php artisan serve --host=0.0.0.0 --port=80
#
# Sumbernya sengaja TIDAK dikejar dalam perubahan ini: dengan `pid_max`
# 4.194.304 di server itu, 38 zombie tidak berbahaya, dan deploy yang sudah
# besar bukan tempat menambah perubahan lagi. Tercatat sebagai utang teknis di
# docs/CUTOVER.md.
#
# Yang benar-benar dibeli bentuk di bawah karena itu bukan hilangnya zombie
# melainkan satu lapis proses yang berkurang: induk yang menuai anaknya sendiri,
# dan satu proses PHP lebih sedikit per menit di container yang RAM-nya sedang
# diperebutkan.
#
# Atribusi awal kami — "zombie = Chromium yang di-reparent ke PID 1" — juga
# keliru. `tini` tetap dipertahankan karena ia murah dan tetap benar untuk yatim
# sungguhan, tapi ia tidak menyentuh zombie yang induknya masih hidup: reaper
# PID 1 hanya menuai anak yang induknya sudah mati.
#
# Menunggu sampai detik ke-00 dan bukan `sleep 60`: `schedule:run` memutuskan
# apa yang jatuh tempo dari jam saat ia berjalan, dan `sleep 60` menghanyut
# beberapa detik tiap putaran sampai satu menit terlewat sama sekali. Yang
# hilang karena itu bukan pekerjaan kecil melainkan satu giliran penuh
# SyncSessionStatusJob.
supervisi_penjadwal() {
    while [ ! -f "$TANDA_BERHENTI" ]; do
        # Tidur sampai pergantian menit berikutnya.
        sleep $((60 - $(date +%-S)))

        [ -f "$TANDA_BERHENTI" ] && break

        # Dijalankan di latar lalu ditunggu, supaya penanda berhenti tetap bisa
        # diperiksa tanpa menunggu `schedule:run` yang kebetulan lama selesai.
        php artisan schedule:run >/dev/null 2>&1 &
        wait $! || true
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
# ============================================================================
# Migrasi database — DIJALANKAN DI SINI, bukan diketik manusia setelah deploy.
# ============================================================================
#
# Sampai 6 September 2026 migrasi adalah langkah manual di terminal Coolify
# (docs/DEPLOYMENT.md §Deploy). Itu bekerja selama perubahannya kecil, dan
# gagal total pada rilis yang menambah kolom yang dibaca hampir tiap
# permintaan: kalau langkahnya terlewat satu kali, container menyala normal,
# lolos health check, lalu SETIAP halaman jatuh dengan "column not found" —
# termasuk halaman masuk. Aplikasi terlihat hidup dari luar dan mati total dari
# dalam, dan yang menemukannya pelanggan.
#
# Aman dijalankan tiap boot karena satu tahap = satu container: tidak ada dua
# proses yang bisa bermigrasi bersamaan. `--force` wajib di produksi — Laravel
# menolak jalan tanpa konfirmasi interaktif.
#
# `--isolated` sengaja TIDAK dipakai walau terlihat lebih aman. Ia mengambil
# kunci lewat cache, dan `CACHE_STORE=database` berarti kuncinya disimpan di
# tabel `cache_locks` — tabel yang justru baru dibuat oleh migrasi ini sendiri.
# Di database yang masih kosong hasilnya ayam-telur: migrasi gagal dengan
# "Table 'cache_locks' doesn't exist" sebelum satu tabel pun sempat dibuat.
# Sudah diuji langsung di MySQL kosong, bukan dugaan.
#
# Kalau GAGAL, container berhenti di sini dan tidak menyalakan apa pun. Itu
# disengaja: aplikasi yang melayani permintaan dengan skema database setengah
# jadi jauh lebih berbahaya daripada container yang jelas-jelas mati — yang
# pertama merusak data, yang kedua cuma perlu diperbaiki lalu di-deploy ulang.
echo "[start.sh] Menjalankan migrasi database."

if ! php artisan migrate --force; then
    cat >&2 <<'GAGAL'
[start.sh] ============================================================
[start.sh] MIGRASI GAGAL. Container dihentikan sebelum melayani apa pun.
[start.sh]
[start.sh] Aplikasi TIDAK dinyalakan dengan sengaja: melayani permintaan
[start.sh] dengan skema database setengah jadi merusak data, sementara
[start.sh] container yang mati cuma perlu diperbaiki lalu di-deploy ulang.
[start.sh]
[start.sh] Periksa: DB_HOST/DB_DATABASE/DB_USERNAME di environment resource,
[start.sh] dan baris galat tepat di atas kotak ini.
[start.sh] ============================================================
GAGAL
    exit 1
fi

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
