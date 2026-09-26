{{--
    Daftar Hadir: fetches the attendance JSON of ONE event and renders it as
    tabs (one per perkumpulan) each with a Pemain table and an SDM table,
    including peserta who haven't checked in yet ("Belum"). Safe to include
    several times on a page (one per event): every instance is scoped to its
    own root element.

    Params:
      $attendanceUrl : URL of the JSON attendance-list endpoint
      $lazy          : (optional) when true and the partial sits inside a
                       <details>, the fetch only happens the first time it opens
--}}
<div class="attendance-list mb-6" data-attendance-url="{{ $attendanceUrl }}" data-lazy="{{ !empty($lazy) ? 1 : 0 }}">
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-bold text-navy-900 text-base">Daftar Tim dan SDM yang Telah Check In</h3>
                <p class="att-updated text-xs text-gray-400 mt-1">Memuat...</p>
            </div>
            <span class="att-total-badge shrink-0 bg-green-100 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">Memuat...</span>
        </div>

        <div class="att-tabs flex gap-1 mt-4 border-b border-gray-200 overflow-x-auto"></div>
        <div class="att-content mt-4 text-sm text-gray-500">Memuat data kehadiran...</div>
    </div>
</div>

@once
@push('scripts')
<script>
function initAttendanceList(el) {
    if (el.dataset.loaded) return;
    el.dataset.loaded = '1';
    var updatedEl = el.querySelector('.att-updated');
    var badgeEl = el.querySelector('.att-total-badge');
    var tabsEl = el.querySelector('.att-tabs');
    var contentEl = el.querySelector('.att-content');
    if (!contentEl) return;

    var groups = [];
    var activeIndex = 0;

    function countHadir(rows) {
        return rows.filter(function (r) { return r.status_kehadiran === 1; }).length;
    }

    function statusBadge(row) {
        var cls = 'bg-gray-100 text-gray-500';
        var icon = 'fa-circle';
        if (row.status_kehadiran === 1) { cls = 'bg-green-100 text-green-700'; icon = 'fa-check-circle'; }
        else if (row.status_kehadiran === 0) { cls = 'bg-red-100 text-red-700'; icon = 'fa-times-circle'; }
        return '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full ' + cls + '">'
            + '<i class="fas ' + icon + '"></i>' + row.status_label + '</span>';
    }

    function renderSection(title, rows, columns) {
        var hadir = countHadir(rows);
        var header = '<tr class="bg-gray-50 text-left text-xs text-gray-500">'
            + columns.map(function (c) { return '<th class="px-3 py-2 font-semibold whitespace-nowrap">' + c.label + '</th>'; }).join('')
            + '</tr>';
        var body = rows.length
            ? rows.map(function (r) {
                return '<tr class="border-t border-gray-100">' + columns.map(function (c) { return '<td class="px-3 py-2 align-middle">' + c.render(r) + '</td>'; }).join('') + '</tr>';
            }).join('')
            : '<tr><td colspan="' + columns.length + '" class="px-3 py-4 text-xs text-gray-400 text-center">Belum ada data.</td></tr>';

        return '<div class="mb-5">'
            + '<h4 class="text-sm font-bold text-navy-900 mb-2">' + title + ' (' + hadir + '/' + rows.length + ')</h4>'
            + '<div class="overflow-x-auto rounded-lg border border-gray-100">'
            + '<table class="w-full text-sm"><thead>' + header + '</thead><tbody>' + body + '</tbody></table>'
            + '</div></div>';
    }

    function renderActiveGroup() {
        var group = groups[activeIndex];
        if (!group) { contentEl.innerHTML = '<p class="text-sm text-gray-400">Belum ada peserta.</p>'; return; }

        var pemainColumns = [
            { label: 'No', render: function (r) { return r.no_punggung ? ('#' + r.no_punggung) : '-'; } },
            { label: 'Nama Pemain', render: function (r) { return '<span class="font-semibold text-navy-900">' + r.nama + '</span>'; } },
            { label: 'Posisi', render: function (r) { return r.posisi || '-'; } },
            { label: 'Status', render: statusBadge },
        ];
        var sdmColumns = [
            { label: 'Nama', render: function (r) { return '<span class="font-semibold text-navy-900">' + r.nama + '</span>'; } },
            { label: 'Jabatan', render: function (r) { return r.posisi || '-'; } },
            { label: 'Status', render: statusBadge },
        ];

        var sdmRows = [].concat(group.sdm || [], group.lainnya || []);
        contentEl.innerHTML = renderSection('Pemain', group.pemain || [], pemainColumns)
            + renderSection('SDM', sdmRows, sdmColumns);
    }

    function renderTabs() {
        if (groups.length <= 1) { tabsEl.innerHTML = ''; return; }
        tabsEl.innerHTML = groups.map(function (g, i) {
            var active = i === activeIndex;
            return '<button type="button" data-idx="' + i + '" class="attendance-tab-btn shrink-0 px-4 py-2 text-xs sm:text-sm font-semibold border-b-2 -mb-px transition '
                + (active ? 'border-navy-700 text-navy-900' : 'border-transparent text-gray-400 hover:text-gray-600')
                + '">' + g.nama_perkumpulan + ' (' + g.summary.hadir + '/' + g.summary.total + ')</button>';
        }).join('');
        tabsEl.querySelectorAll('.attendance-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeIndex = parseInt(btn.dataset.idx, 10);
                renderTabs();
                renderActiveGroup();
            });
        });
    }

    fetch(el.dataset.attendanceUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.status !== '00' || !json.data) {
                contentEl.textContent = 'Gagal memuat daftar hadir.';
                updatedEl.textContent = '';
                badgeEl.textContent = '-';
                return;
            }
            groups = json.data.groups || [];
            var s = json.data.summary || { hadir: 0, total: 0 };
            badgeEl.textContent = 'Total Hadir ' + s.hadir + '/' + s.total;

            var now = new Date();
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            updatedEl.textContent = 'Terakhir diperbarui: ' + pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear()
                + ', ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ' WIB';

            if (!groups.length) {
                tabsEl.innerHTML = '';
                contentEl.innerHTML = '<p class="text-sm text-gray-400">Belum ada peserta.</p>';
                return;
            }

            renderTabs();
            renderActiveGroup();
        })
        .catch(function () {
            contentEl.textContent = 'Gagal memuat daftar hadir.';
            updatedEl.textContent = '';
        });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.attendance-list').forEach(function (el) {
        var details = el.dataset.lazy === '1' ? el.closest('details') : null;
        if (!details) { initAttendanceList(el); return; }
        var load = function () { if (details.open) initAttendanceList(el); };
        details.addEventListener('toggle', load);
        load();
    });
});
</script>
@endpush
@endonce
