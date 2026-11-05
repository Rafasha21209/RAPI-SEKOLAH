@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Kalender Agenda & Fasilitas Sekolah</h2>
    <p style="font-size: 12px; color: #555;">
        Kalender ini hanya menampilkan agenda yang <strong>sudah disetujui</strong> Waka/Admin.
        Pengajuan yang masih menunggu persetujuan tidak akan muncul di sini.
    </p>

    <p class="calendar-hint">Ketuk tanggal atau titik agenda untuk melihat daftar agenda pada hari itu.</p>

    <div id="rapi-calendar" style="max-width: 100%; background: #fff;"></div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('rapi-calendar');

        // Batas lebar layar yang dianggap "HP / layar kecil".
        // Harus sama dengan breakpoint di style.css (bagian 14b).
        var MOBILE_MAX = 768;

        function isMobile() {
            return window.innerWidth <= MOBILE_MAX;
        }

        // Opsi yang berbeda antara desktop dan HP.
        // HP: toolbar satu baris (prev/next - judul - hari ini), pilihan tampilan
        // dipindah ke bawah sebagai tombol sejajar, dan tampilan "minggu berjam"
        // (timeGridWeek, 7 kolom sempit) tidak ditawarkan.
        function responsiveOptions(mobile) {
            if (mobile) {
                return {
                    headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
                    footerToolbar: { center: 'dayGridMonth,listWeek' },
                    displayEventTime: false
                };
            }
            return {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                footerToolbar: false,
                displayEventTime: true
            };
        }

        var lastMobile = isMobile();

        var calendar = new FullCalendar.Calendar(calendarEl, Object.assign({
            initialView: 'dayGridMonth',
            locale: 'id',
            height: 'auto',
            buttonText: { today: 'Hari ini', month: 'Bulan', week: 'Minggu', list: 'Agenda' },
            events: '{{ route('calendar.feed') }}',

            // HP: tap tanggal / titik agenda -> daftar agenda hari itu.
            dateClick: function (info) {
                if (isMobile()) {
                    calendar.changeView('listDay', info.date);
                }
            },
            eventClick: function (info) {
                if (isMobile() && info.view.type === 'dayGridMonth') {
                    info.jsEvent.preventDefault();
                    calendar.changeView('listDay', info.event.start);
                }
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
                if (info.event.extendedProps.location) {
                    info.el.title = info.event.title + ' — ' + info.event.extendedProps.location;
                }
            }
        }, responsiveOptions(lastMobile)));

        calendar.render();
    });
</script>
@endsection
