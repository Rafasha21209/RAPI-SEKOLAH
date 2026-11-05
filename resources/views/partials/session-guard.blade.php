{{--
    Sesi pelajaran realtime (jam WIB):
    - Jam sesi mengikuti jadwal sekolah per hari (Senin-Kamis 10 sesi, Jumat 8 sesi) dari Event::SCHEDULE_*.
    - Untuk TANGGAL HARI INI, sesi yang jamnya sudah dimulai/lewat otomatis tidak bisa dipilih (diperbarui tiap 20 detik).
    - Pilih tanggal besok atau setelahnya: semua sesi bisa dipilih.
    Form yang dijaga: <form data-session-guard> berisi input[name=date], select[name=start_session], select[name=end_session]
    dan (opsional) elemen .sg-note untuk keterangan jam sekarang.
    Server tetap memvalidasi ulang, jadi ini hanya kemudahan tampilan.
--}}
@php
    $toMin = fn ($t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
    $sched = [
        'regular' => collect(\App\Models\Event::SCHEDULE_REGULAR)->map(fn ($r) => [$r[0], $r[1], $toMin($r[0])])->all(),
        'friday'  => collect(\App\Models\Event::SCHEDULE_FRIDAY)->map(fn ($r) => [$r[0], $r[1], $toMin($r[0])])->all(),
    ];
@endphp
<script>
    (function () {
        var SCHEDULE = @json($sched);

        function scheduleFor(dateStr) {
            if (!dateStr) { return SCHEDULE.regular; }
            var d = new Date(dateStr + 'T00:00:00');
            return d.getDay() === 5 ? SCHEDULE.friday : SCHEDULE.regular;
        }

        var fmt = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        });

        function nowWib() {
            var p = {};
            fmt.formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
            return { date: p.year + '-' + p.month + '-' + p.day, minutes: parseInt(p.hour, 10) * 60 + parseInt(p.minute, 10), text: p.hour + ':' + p.minute };
        }

        function guard(form) {
            var dateEl = form.querySelector('[name="date"]');
            var start  = form.querySelector('[name="start_session"]');
            var end    = form.querySelector('[name="end_session"]');
            var note   = form.querySelector('.sg-note');
            if (!dateEl || !start) { return; }

            // Nilai awal (form edit) tidak pernah dimatikan agar data lama tetap bisa disimpan.
            var keepStart = start.dataset.keep !== undefined ? start.dataset.keep : (start.dataset.keep = start.value);
            var keepEnd   = end ? (end.dataset.keep !== undefined ? end.dataset.keep : (end.dataset.keep = end.value)) : '';
            var keepDate  = dateEl.dataset.keep !== undefined ? dateEl.dataset.keep : (dateEl.dataset.keep = dateEl.value);

            function apply() {
                var now = nowWib();
                var sched = scheduleFor(dateEl.value);
                var isToday = dateEl.value === now.date;
                var isUnchanged = dateEl.value === keepDate;
                var available = 0;

                function refresh(sel, keep, countIt, minValue) {
                    Array.prototype.forEach.call(sel.options, function (opt) {
                        if (!opt.value) { return; }
                        var row = sched[opt.value];
                        var kept = isUnchanged && opt.value === keep;

                        if (!row) {                               // sesi tidak ada pada hari ini (mis. Jumat > sesi 8)
                            opt.textContent = 'Sesi ' + opt.value + ' (tidak ada hari ini)';
                            opt.disabled = !kept;
                            if (opt.disabled && sel.value === opt.value) { sel.value = ''; }
                            return;
                        }

                        opt.textContent = 'Sesi ' + opt.value + ' (' + row[0] + '-' + row[1] + ')';
                        var past = isToday && row[2] <= now.minutes;
                        var tooEarly = minValue && parseInt(opt.value, 10) < minValue;
                        var locked = (past && !kept) || tooEarly;
                        opt.disabled = locked;
                        if (!locked && countIt) { available++; }
                        if (locked && sel.value === opt.value) { sel.value = ''; }
                    });
                }

                refresh(start, keepStart, true, 0);
                if (end) { refresh(end, keepEnd, false, parseInt(start.value || '0', 10)); }

                if (note) {
                    var maxSession = Object.keys(sched).length;
                    if (isToday) {
                        note.textContent = available === 0
                            ? 'Sekarang pukul ' + now.text + ' WIB. Tidak ada sesi tersisa hari ini, silakan pilih tanggal besok.'
                            : 'Sekarang pukul ' + now.text + ' WIB. Sesi yang sudah dimulai tidak bisa dipilih untuk hari ini.';
                        note.style.color = available === 0 ? '#c0392b' : '#666';
                    } else {
                        note.textContent = 'Jam pelajaran tanggal ini: sesi 1-' + maxSession + ' (' + sched[1][0] + '-' + sched[maxSession][1] + ').';
                        note.style.color = '#666';
                    }
                }
            }

            dateEl.addEventListener('change', apply);
            dateEl.addEventListener('input', apply);
            start.addEventListener('change', apply);
            apply();
            setInterval(apply, 20000);
        }

        document.querySelectorAll('form[data-session-guard]').forEach(guard);
    })();
</script>
