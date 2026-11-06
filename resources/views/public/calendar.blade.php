@extends('layouts.app')

@section('content')
@php
    $typeMeta = \App\Models\Event::TYPE_META;
    // 'Y-m-d' => nama hari libur (untuk menandai tanggal merah di sel kalender).
    $holidayNames = collect(config('holidays', []))->map(fn ($h) => $h[0])->all();
@endphp
<div style="padding: 20px;">
    <h2>Kalender Agenda & Fasilitas Sekolah</h2>
    <p style="font-size: 12px; color: #555;">
        Kalender ini hanya menampilkan agenda yang <strong>sudah disetujui</strong> Waka/Admin.
        Pengajuan yang masih menunggu persetujuan tidak akan muncul di sini.
        Hari libur nasional mengikuti SKB 3 Menteri; hari Sabtu, Minggu, dan tanggal merah ditandai dengan warna merah.
    </p>

    {{-- Keterangan jenis event: warna + ikon --}}
    <ul class="calendar-legend" aria-label="Keterangan jenis event">
        @foreach($typeMeta as $key => $m)
            <li><i class="legend-dot" style="background: {{ $m['color'] }};"></i>{{ $m['label'] }}</li>
        @endforeach
        <li><i class="legend-dot legend-weekend"></i> Sabtu / Minggu</li>
    </ul>

    <p class="calendar-hint">Ketuk tanggal untuk melihat daftar agenda hari itu, atau ketuk agenda untuk melihat detailnya.</p>

    <div id="rapi-calendar" style="max-width: 100%; background: #fff;"></div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('rapi-calendar');
        var HOLIDAYS = @json($holidayNames);

        // Batas lebar layar yang dianggap "HP / layar kecil".
        // Harus sama dengan breakpoint di style.css (bagian 14b).
        var MOBILE_MAX = 768;

        function isMobile() {
            return window.innerWidth <= MOBILE_MAX;
        }

        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

        // Opsi yang berbeda antara desktop dan HP.
        // HP: toolbar satu baris, agenda pada sel = titik berwarna (judul panjang tidak lagi
        // menumpuk antar-tanggal), tampilan "minggu berjam" tidak ditawarkan.
        function responsiveOptions(mobile) {
            if (mobile) {
                return {
                    headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
                    footerToolbar: { center: 'dayGridMonth,listWeek' },
                    displayEventTime: false,
                    eventDisplay: 'list-item',
                    dayMaxEvents: false
                };
            }
            return {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                footerToolbar: false,
                displayEventTime: true,
                eventDisplay: 'auto',
                dayMaxEvents: 3
            };
        }

        function showDetail(e) {
            var p = e.extendedProps || {};
            var rows = [];
            rows.push(['Tanggal', p.dateLabel]);
            if (p.type !== 'libur_nasional') {
                rows.push(['Waktu', p.session]);
                rows.push(['Ruangan', p.room]);
                rows.push(['Lokasi', p.location]);
                rows.push(['Tujuan penggunaan', p.description]);
                rows.push(['Keterangan', p.notes]);
                rows.push(['Penanggung jawab', p.responsible]);
                rows.push(['Pengaju', p.requester]);
                rows.push(['PIC ruangan', p.pic]);
                rows.push(['Keterangan PIC', p.picNote]);
            } else {
                rows.push(['Keterangan', p.description]);
            }

            var badges = [{ text: p.typeLabel || 'Agenda', color: p.color }];
            if (p.outside) { badges.push({ text: 'Di luar jam sekolah', color: '#6d4c41' }); }

            window.RapiDetail.open({
                title: p.name || e.title,
                badges: badges,
                rows: rows,
                photos: []
            });
        }

        var lastMobile = isMobile();

        var calendar = new FullCalendar.Calendar(calendarEl, Object.assign({
            initialView: 'dayGridMonth',
            locale: 'id',
            height: 'auto',
            firstDay: 0,
            buttonText: { today: 'Hari ini', month: 'Bulan', week: 'Minggu', list: 'Agenda' },
            events: '{{ route('calendar.feed') }}',

            // Sabtu, Minggu & tanggal merah diberi warna merah.
            dayCellClassNames: function (arg) {
                var d = arg.date, cls = [];
                if (d.getDay() === 0 || d.getDay() === 6) { cls.push('rapi-day-off'); }
                if (HOLIDAYS[ymd(d)]) { cls.push('rapi-day-off', 'rapi-day-holiday'); }
                return cls;
            },
            dayHeaderClassNames: function (arg) {
                return (arg.dow === 0 || arg.dow === 6) ? ['rapi-head-off'] : [];
            },

            // HP: tap tanggal -> daftar agenda hari itu.
            dateClick: function (info) {
                if (isMobile()) {
                    calendar.changeView('listDay', info.date);
                }
            },

            // Klik agenda / titik agenda / tanggal merah -> kartu detail (tanpa pindah halaman).
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                showDetail(info.event);
            },

            // Saat jendela diubah ukurannya (atau HP diputar), sesuaikan opsi
            // hanya ketika melewati batas desktop <-> HP.
            windowResize: function () {
                var mobile = isMobile();
                if (mobile === lastMobile) { return; }
                lastMobile = mobile;

                var opts = responsiveOptions(mobile);
                Object.keys(opts).forEach(function (key) {
                    calendar.setOption(key, opts[key]);
                });

                if (mobile && calendar.view.type === 'timeGridWeek') {
                    calendar.changeView('listWeek');
                }
            },

            eventDidMount: function (info) {
                var p = info.event.extendedProps || {};
                var tip = (p.typeLabel ? p.typeLabel + ': ' : '') + (p.name || info.event.title);
                if (p.room) { tip += ' — ' + p.room; }
                info.el.title = tip;
            }
        }, responsiveOptions(lastMobile)));

        calendar.render();
    });
</script>

@include('partials.detail-modal')
@endsection
