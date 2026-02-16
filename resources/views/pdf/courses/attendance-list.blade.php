{{-- resources/views/pdf/courses/attendance-list.blade.php --}}
@php
    use Illuminate\Support\Str;

    $from = $meta['date_from'];
    $to   = $meta['date_to'];

    $weekdayMap = [
        'Mon' => 'MO',
        'Tue' => 'DI',
        'Wed' => 'MI',
        'Thu' => 'DO',
        'Fri' => 'FR',
        'Sat' => 'SA',
        'Sun' => 'SO',
    ];

    $logoPath = public_path('site-images/logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    // DomPDF behandelt Prozentbreiten bei vielen Spalten oft "weich".
    // Name daher als 2 echte Spalten (colspan=2) mit fixer Gesamtbreite.
    $nameColsTotalMm = 120.0;
    $nameColMm = $nameColsTotalMm / 2;
    $smallColMm = 7.0; // Uform/Qpro/FT
    $usableWidthMm = 257.0; // A4 landscape (297) - 2x20mm Seitenrand
    $dayCols = max(1, (count($days ?? []) * 2));
    $remainingMm = max(20.0, $usableWidthMm - $nameColsTotalMm - (3 * $smallColMm));
    $dayColMm = max(2.2, $remainingMm / $dayCols);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Klassen-Anwesenheitsliste</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        table { border-collapse: collapse; width: 100%; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .logo {
            width: 120px;
            margin-bottom: 8px;
        }

        .title-center {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            color: #0f172a;
            padding-top: 6px;
        }

        .subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
            font-weight: normal;
        }

        .meta-box {
            border: 0.4px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 6px;
            padding: 6px 8px;
            line-height: 1.35;
        }

        .meta-k {
            display: inline-block;
            min-width: 82px;
            font-weight: bold;
            color: #334155;
        }

        table.attendance {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
            font-size: 8px;
        }

        table.attendance thead th,
        table.attendance tbody td {
            border: 0.4px solid #cbd5e1;
            padding: 4px 3px;
        }

        table.attendance tbody td {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        table.attendance thead th {
            background: #eef2f7;
            color: #334155;
            text-align: center;
            font-weight: bold;
            font-size: 8px;
        }

        table.attendance tbody tr:nth-child(odd) {
            background-color: #fafcff;
        }

        .col-name  { text-align: left; padding-left: 6px; }
        .col-small { text-align: center; }

        td.border-x { border-left: 0.4px solid #94a3b8 !important; border-right: 0.4px solid #94a3b8 !important; }
        td.border-l { border-left: 0.4px solid #94a3b8 !important; }
        td.border-r { border-right: 0.4px solid #94a3b8 !important; }
        td.day { border-left: 0.4px solid #cbd5e1 !important; border-right: 0.4px solid #cbd5e1 !important; }
        td.day.morning { border-left: 0.4px solid #94a3b8 !important; }
        td.day.end { border-right: 0.4px solid #94a3b8 !important; }

        .legend {
            margin-top: 6px;
            font-size: 8px;
            color: #64748b;
        }

        .section-title {
            margin-top: 8px;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 10px;
            color: #0f172a;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 8.5px;
        }
        table.list th,
        table.list td {
            border: 0.4px solid #cbd5e1;
            padding: 3px 4px;
        }
        table.list th {
            text-align: left;
            background: #eef2f7;
            color: #334155;
            font-weight: bold;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 32%;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" class="logo" alt="Logo">
            @endif
            <div class="meta-box">
                <div><span class="meta-k">Datum:</span> {{ optional($from)->format('d.m.Y') }} - {{ optional($to)->format('d.m.Y') }}</div>
                <div><span class="meta-k">Tage:</span> {{ $meta['num_days'] ?? '-' }}</div>
                <div><span class="meta-k">Raum:</span> {{ $meta['room'] ?? '-' }}</div>
            </div>
        </td>

        <td class="title-center" style="width: 40%;">
            Klassen-Anwesenheitsliste
            <div class="subtitle">Anwesenheit pro Teilnehmer und Unterrichtstag</div>
        </td>

        <td style="width: 28%;">
            <div class="meta-box">
                <div><span class="meta-k">Modul:</span> {{ $meta['module'] ?? '-' }}</div>
                <div><span class="meta-k">Klasse:</span> {{ $meta['class_label'] ?? '-' }}</div>
                <div><span class="meta-k">Dozent:</span> {{ $meta['tutor_name'] ?? '-' }}</div>
                <div><span class="meta-k">Beginn:</span> {{ $meta['start_time'] ?? '-' }} Uhr</div>
            </div>
        </td>
    </tr>
</table>

<table class="attendance">
    <colgroup>
        <col style="width: {{ number_format($nameColMm, 2, '.', '') }}mm;">
        <col style="width: {{ number_format($nameColMm, 2, '.', '') }}mm;">
        <col style="width: {{ number_format($smallColMm, 2, '.', '') }}mm;">
        <col style="width: {{ number_format($smallColMm, 2, '.', '') }}mm;">
        <col style="width: {{ number_format($smallColMm, 2, '.', '') }}mm;">
        @foreach($days as $day)
            <col style="width: {{ number_format($dayColMm, 2, '.', '') }}mm;">
            <col style="width: {{ number_format($dayColMm, 2, '.', '') }}mm;">
        @endforeach
    </colgroup>
    <thead>
    <tr>
        <th class="col-name" colspan="2">Name</th>
        <th class="col-small">Uform</th>
        <th class="col-small">Qpro</th>
        <th class="col-small">FT</th>

        @foreach($days as $day)
            @php
                $dayKey  = $day->date->format('D');
                $weekday = $weekdayMap[$dayKey] ?? Str::upper($dayKey);
            @endphp
            <th class="col-small" colspan="2">
                {{ $day->date->format('d.m.') }}<br>
                {{ $weekday }}
            </th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @foreach($rows as $row)
        @php
            $p = $row['person'];

            $nachname = is_array($p)
                ? ($p['nachname'] ?? $p['last_name'] ?? '')
                : ($p->nachname ?? $p->last_name ?? '');

            $vorname = is_array($p)
                ? ($p['vorname'] ?? $p['first_name'] ?? '')
                : ($p->vorname ?? $p->first_name ?? '');

            $name = trim($nachname . ', ' . $vorname);
        @endphp
        <tr>
            <td class="col-name border-l" colspan="2">{{ $name }}</td>

            <td class="col-small">{{ $row['uform'] ?? '' }}</td>
            <td class="col-small border-x">{{ $row['qpro'] ?? '' }}</td>
            <td class="col-small border-r">{{ $row['ft'] ?? '' }}</td>

            @foreach($days as $day)
                @php
                    $cell = $row['cells'][$day->id] ?? null;
                @endphp

                <td class="col-small day morning">
                    @if($cell)
                        @if($cell['excused'])
                            E
                        @elseif($cell['empty'] === true)
                        @elseif($cell['morning_present'] === true)
                            x
                        @elseif($cell['morning_present'] === false)
                            f
                        @endif
                    @endif
                </td>

                <td class="col-small day end">
                    @if($cell)
                        @if($cell['excused'])
                            E
                        @elseif($cell['empty'] === true)
                        @elseif($cell['end_present'] === true)
                            x
                        @elseif($cell['end_present'] === false)
                            f
                        @endif
                    @endif
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

<div class="legend">
    x = anwesend, f = fehlt, E = entschuldigt
</div>

@if(!empty($partials))
    <div style="page-break-before: always; margin-top: 10px;">
        <div class="section-title">Uebersicht: teilweise Anwesenheit (zu spaet / frueher gegangen)</div>
        <table class="list">
            <thead>
            <tr>
                <th>Name</th>
                <th>Datum</th>
                <th style="text-align:right;">Zu spaet (Min)</th>
                <th style="text-align:right;">Frueher gegangen (Min)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($partials as $entry)
                @php
                    $pp   = $entry['person'];
                    $nach = $pp->nachname ?? $pp->last_name ?? '';
                    $vor  = $pp->vorname  ?? $pp->first_name ?? '';
                    $pName = trim($nach . ', ' . $vor);
                @endphp
                <tr>
                    <td>{{ $pName }}</td>
                    <td>{{ $entry['date']?->format('d.m.Y') }}</td>
                    <td style="text-align:right;">{{ $entry['late_minutes'] ?: '-' }}</td>
                    <td style="text-align:right;">{{ $entry['left_early_minutes'] ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

</body>
</html>
