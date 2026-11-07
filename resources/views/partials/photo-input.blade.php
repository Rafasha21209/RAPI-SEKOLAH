{{--
    Input foto dengan pilihan KAMERA atau GALERI.
    Pemakaian: @include('partials.photo-input', ['name' => 'photo', 'required' => true])
    Input utama (name) tetap satu, jadi validasi di server tidak berubah.
    Opsional: 'accept' => 'image/*,application/pdf' bila dokumen PDF juga diizinkan
    (tombol "Ambil Foto" tetap hanya memakai kamera; "Pilih dari Galeri / Berkas" membuka galeri & file).

    KAMERA:
     - HP/tablet : memakai kamera bawaan lewat <input capture> (seperti sebelumnya).
     - Desktop   : membuka jendela kamera (webcam) di dalam aplikasi lewat getUserMedia.
                   Catatan browser: kamera desktop hanya boleh dipakai di https:// atau
                   http://localhost. Di alamat lain (mis. http://192.168.x.x) tombol akan
                   membuka pilihan berkas sebagai cadangan.
--}}
@php $pid = 'pi_' . \Illuminate\Support\Str::random(6); @endphp

@once
<style>
    .rcam { position: fixed; inset: 0; z-index: 3500; display: flex; align-items: center; justify-content: center;
        padding: 12px; background: rgba(8, 14, 12, 0.88); }
    .rcam[hidden] { display: none !important; }
    .rcam-card { width: 100%; max-width: 640px; background: #fff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,.35); display: flex; flex-direction: column; }
    .rcam-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 14px;
        border-bottom: 1px solid #e6ebe9; font-weight: 600; color: #16302a; }
    .rcam-stage { position: relative; background: #000; min-height: 200px; }
    .rcam-stage video, .rcam-stage canvas { display: block; width: 100%; max-height: 60vh; object-fit: contain; background: #000; }
    .rcam-msg { padding: 10px 14px; font-size: 0.85rem; color: #9c4238; background: #fdecea; }
    .rcam-msg:empty { display: none; }
    .rcam-bar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; padding: 12px 14px; }
    .rcam-bar select { max-width: 100%; }
    .rcam-bar button { min-width: 110px; }
</style>
<script>
    /* Jendela kamera desktop (dipakai bersama oleh semua input foto di halaman). */
    (function () {
        if (window.RapiCamera) { return; }

        var UA = navigator.userAgent || '';
        var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(UA)
            || (/Macintosh/.test(UA) && navigator.maxTouchPoints > 1);   // iPadOS
        var supported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        var box, video, canvas, msg, btnShot, btnUse, btnRetry, btnCancel, btnClose, sel;
        var stream = null, onPick = null, lastFocus = null, blob = null;

        function build() {
            if (box) { return; }
            box = document.createElement('div');
            box.className = 'rcam';
            box.hidden = true;
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.setAttribute('aria-label', 'Ambil foto dengan kamera');
            box.innerHTML =
                '<div class="rcam-card">' +
                  '<div class="rcam-head"><span>Ambil Foto</span><button type="button" class="rcam-x" aria-label="Tutup">&times;</button></div>' +
                  '<div class="rcam-stage"><video autoplay playsinline muted></video><canvas hidden></canvas></div>' +
                  '<div class="rcam-msg" role="alert"></div>' +
                  '<div class="rcam-bar">' +
                    '<select class="rcam-sel" aria-label="Pilih kamera" hidden></select>' +
                    '<button type="button" class="rcam-shot">&#128247; Ambil Foto</button>' +
                    '<button type="button" class="rcam-retry" hidden>Ulangi</button>' +
                    '<button type="button" class="rcam-use" hidden>Gunakan Foto</button>' +
                    '<button type="button" class="rcam-cancel">Batal</button>' +
                  '</div>' +
                '</div>';
            document.body.appendChild(box);

            video    = box.querySelector('video');
            canvas   = box.querySelector('canvas');
            msg      = box.querySelector('.rcam-msg');
            sel      = box.querySelector('.rcam-sel');
            btnShot  = box.querySelector('.rcam-shot');
            btnUse   = box.querySelector('.rcam-use');
            btnRetry = box.querySelector('.rcam-retry');
            btnCancel = box.querySelector('.rcam-cancel');
            btnClose = box.querySelector('.rcam-x');

            btnShot.addEventListener('click', shoot);
            btnRetry.addEventListener('click', retry);
            btnUse.addEventListener('click', use);
            btnCancel.addEventListener('click', close);
            btnClose.addEventListener('click', close);
            sel.addEventListener('change', function () { start(sel.value); });
            box.addEventListener('mousedown', function (e) { if (e.target === box) { close(); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box && !box.hidden) { close(); } });
        }

        function stop() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
        }

        function setMode(preview) {
            video.hidden = preview;
            canvas.hidden = !preview;
            btnShot.hidden = preview;
            btnRetry.hidden = !preview;
            btnUse.hidden = !preview;
            sel.hidden = preview || sel.options.length < 2;
        }

        function errText(e) {
            var n = e && e.name;
            if (n === 'NotAllowedError' || n === 'SecurityError') { return 'Izin kamera ditolak. Izinkan akses kamera pada browser lalu coba lagi.'; }
            if (n === 'NotFoundError' || n === 'OverconstrainedError') { return 'Kamera tidak ditemukan pada perangkat ini.'; }
            if (n === 'NotReadableError') { return 'Kamera sedang dipakai aplikasi lain.'; }
            return 'Kamera tidak dapat dibuka.';
        }

        function start(deviceId) {
            stop();
            msg.textContent = '';
            var constraints = { video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'user' }, audio: false };
            return navigator.mediaDevices.getUserMedia(constraints).then(function (s) {
                stream = s;
                video.srcObject = s;
                var track = s.getVideoTracks()[0];
                var current = track && track.getSettings ? track.getSettings().deviceId : '';
                return navigator.mediaDevices.enumerateDevices().then(function (list) {
                    var cams = list.filter(function (d) { return d.kind === 'videoinput'; });
                    sel.textContent = '';
                    cams.forEach(function (d, i) {
                        var o = document.createElement('option');
                        o.value = d.deviceId;
                        o.textContent = d.label || ('Kamera ' + (i + 1));
                        if (d.deviceId === current) { o.selected = true; }
                        sel.appendChild(o);
                    });
                    sel.hidden = cams.length < 2;
                });
            }).catch(function (e) {
                msg.textContent = errText(e);
                btnShot.hidden = true;
            });
        }

        function shoot() {
            if (!video.videoWidth) { msg.textContent = 'Kamera belum siap, tunggu sebentar.'; return; }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(function (b) {
                if (!b) { msg.textContent = 'Gagal mengambil foto, coba lagi.'; return; }
                blob = b;
                setMode(true);
            }, 'image/jpeg', 0.92);
        }

        function retry() { blob = null; msg.textContent = ''; setMode(false); }

        function use() {
            if (!blob) { return; }
            var file;
            try {
                file = new File([blob], 'foto-kamera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            } catch (e) { msg.textContent = 'Browser tidak mendukung penyimpanan foto dari kamera. Gunakan "Pilih dari Galeri".'; return; }
            var cb = onPick;
            close();
            if (cb) { cb(file); }
        }

        function close() {
            if (!box) { return; }
            stop();
            blob = null;
            box.hidden = true;
            document.body.classList.remove('rcam-open');
            if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
        }

        /* Dipanggil tombol "Ambil Foto". callback(file) menerima File JPEG hasil jepretan.
           Mengembalikan false bila kamera tidak dapat dipakai (pemanggil memakai cadangan). */
        function open(callback) {
            if (!supported) { return false; }
            build();
            onPick = callback;
            lastFocus = document.activeElement;
            setMode(false);
            btnShot.hidden = false;
            box.hidden = false;
            document.body.classList.add('rcam-open');
            start();
            return true;
        }

        window.RapiCamera = { open: open, isMobile: isMobile, supported: supported };
    })();
</script>
@endonce

<div class="photo-input" id="{{ $pid }}">
    <input type="file" name="{{ $name }}" accept="{{ $accept ?? 'image/*' }}" class="pi-main" @if(!empty($required)) required @endif>
    <input type="file" accept="image/*" capture="environment" class="pi-camera" tabindex="-1" aria-hidden="true">

    <div class="pi-actions">
        <button type="button" class="pi-btn pi-btn-camera">&#128247; Ambil Foto</button>
        <button type="button" class="pi-btn pi-btn-gallery">&#128193; Pilih dari Galeri{{ !empty($accept) && str_contains($accept, 'pdf') ? ' / Berkas' : '' }}</button>
    </div>

    <div class="pi-preview" hidden>
        <img alt="Pratinjau foto">
        <span class="pi-name"></span>
    </div>
</div>
<script>
    (function () {
        var box = document.getElementById('{{ $pid }}');
        var main = box.querySelector('.pi-main');
        var cam = box.querySelector('.pi-camera');
        var prev = box.querySelector('.pi-preview');

        function show() {
            var f = main.files && main.files[0];
            if (!f) { prev.hidden = true; return; }
            var im = prev.querySelector('img');
            if (f.type && f.type.indexOf('image/') === 0) {
                im.src = URL.createObjectURL(f);
                im.hidden = false;
            } else {
                im.hidden = true;           // PDF/dokumen: tampilkan nama berkas saja
            }
            prev.querySelector('.pi-name').textContent = f.name;
            prev.hidden = false;
        }

        // Taruh satu File ke input utama (yang ikut terkirim ke server).
        function setMainFile(file) {
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                main.files = dt.files;
            } catch (e) { /* browser lama: pakai tombol galeri */ }
            show();
        }

        box.querySelector('.pi-btn-camera').addEventListener('click', function () {
            var rc = window.RapiCamera;
            // HP/tablet: kamera bawaan. Desktop: jendela webcam; bila tidak didukung, buka pilihan berkas.
            if (rc && !rc.isMobile && rc.open(setMainFile)) { return; }
            cam.click();
        });
        box.querySelector('.pi-btn-gallery').addEventListener('click', function () { main.click(); });

        // Foto dari kamera HP dipindahkan ke input utama.
        cam.addEventListener('change', function () {
            if (!cam.files.length) { return; }
            setMainFile(cam.files[0]);
        });
        main.addEventListener('change', show);
    })();
</script>
