{{--
    Input foto dengan pilihan KAMERA atau GALERI (nyaman di HP).
    Pemakaian: @include('partials.photo-input', ['name' => 'photo', 'required' => true])
    Input utama (name) tetap satu, jadi validasi di server tidak berubah.
    Opsional: 'accept' => 'image/*,application/pdf' bila dokumen PDF juga diizinkan
    (tombol "Ambil Foto" tetap hanya memakai kamera; "Pilih dari Galeri / Berkas" membuka galeri & file).
--}}
@php $pid = 'pi_' . \Illuminate\Support\Str::random(6); @endphp
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

        box.querySelector('.pi-btn-camera').addEventListener('click', function () { cam.click(); });
        box.querySelector('.pi-btn-gallery').addEventListener('click', function () { main.click(); });

        // Foto dari kamera dipindahkan ke input utama (yang ikut terkirim ke server).
        cam.addEventListener('change', function () {
            if (!cam.files.length) { return; }
            try {
                var dt = new DataTransfer();
                dt.items.add(cam.files[0]);
                main.files = dt.files;
            } catch (e) { /* browser lama: pakai tombol galeri */ }
            show();
        });
        main.addEventListener('change', show);
    })();
</script>
