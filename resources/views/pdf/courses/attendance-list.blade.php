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
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Klassen-Anwesenheitsliste</title>
    <style>
        @page {
            margin: 18px 18px 26px 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 1em;
        }

        /* ---------- Header ---------- */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px; /* Abstand zur Tabelle wie in der Vorlage */
        }
        .header-table td {
            border: none;
            padding: 2px 3px;
            vertical-align: top;
        }
        .header-left {
            width: 35%;
            font-size: 8.5px;
        }
        .header-center {
            width: 30%;
            text-align: center;
            font-size: 11px;
        }
        .header-right {
            width: 35%;
            text-align: right;
            font-size: 8.5px;
        }

        /* ---------- Tabelle ---------- */
        table.attendance {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;    /* großer Abstand wie in der KARE-PDF */
            font-size: 12px;
        }

        /* Kopfzeile: keine kompletten Rahmen, nur Linie unten */
        table.attendance thead th {
            border-top: none;
            border-left: none;
            border-right: none;
            border-bottom: none;
            padding: 2px 2px 0px 2px;
            height: 14px;
            font-size: 10px;
            font-weight: normal;
        }
        table.attendance tbody{ 
            border-top: 0.4px solid #000; 
        }
        table.attendance tbody tr{
            border-bottom: 0.4px solid #333;
        }
        table.attendance tbody tr:last-of-type {
            border-bottom: 0.4px solid #000;
        }
        table.attendance tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        /* Datenzeilen: kompletter Rahmen */
        table.attendance tbody td {
            border: none;
            padding: 7px;
            height: 20px;
        }

        .col-name  { width: 210px; text-align: left;  padding-left: 4px; }
        .col-small { width: 18px;  text-align: center; }

        .legend {
            margin-top: 4px;
            font-size: 7.5px;
        }

        /* Unsichtbare zweite Header-Zeile (nur für die Colspans notwendig) */
        .invisible-header th {
            height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            font-size: 0 !important;
            line-height: 0
        }
        td.border-x{
            border-left: 0.4px solid #111 !important;
            border-right: 0.4px solid #111 !important;
        }
        td.border-l{
            border-left: 0.4px solid #111 !important;
        }
        td.border-r{
            border-right: 0.4px solid #111 !important;
        }
        td.day{
            border-left: 0.4px solid #ccc !important;
            border-right: 0.4px solid #ccc !important;
        }
        td.day.morning{
            border-left: 0.4px solid #111 !important;
        }
        td.day.end{
            border-right: 0.4px solid #111 !important;
        }
    </style>
</head>
<body>
<table class="header-table">
    <tr>
        <td class="header-left">
            Datum:
            {{ optional($from)->format('d.m.Y') }}-{{ optional($to)->format('d.m.Y') }}
            ({{ $meta['num_days'] }})<br>
            Raum:
            {{ $meta['room'] ?? '—' }},
            Unterrichts-Beginn:
            {{ $meta['start_time'] ?? '—' }} Uhr
        </td>
        <td class="header-center">
            Klassen-Anwesenheitsliste
        </td>
        <td class="header-right">
            Baustein {{ $meta['module'] }}<br>
            Klasse {{ $meta['class_label'] }}, Dozent: {{ $meta['tutor_name'] }}
        </td>
    </tr>
</table>

<table class="attendance">
    <thead>
    {{-- Zeile 1: Name + Datum/Wochentag (jede Tagesgruppe colspan=2) --}}
    <tr>
        <th class="col-name"  rowspan="2">Name</th>
        <th class="col-small" rowspan="2">Uform</th>
        <th class="col-small" rowspan="2">Qpro</th>
        <th class="col-small" rowspan="2">Punkte</th>
        <th class="col-small" rowspan="2">FT</th>

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

    {{-- Zeile 2: strukturell notwendig, aber unsichtbar --}}
    <tr class="invisible-header">
        @foreach($days as $day)
            <th class="col-small"></th>
            <th class="col-small"></th>
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
            <td class="col-name border-l">{{ $name }}</td>

            <td class="col-small">{{ $row['uform']  ?? '' }}</td>
            <td class="col-small border-x" >{{ $row['qpro']   ?? '' }}</td>
            <td class="col-small border-r">{{ $row['points'] ?? '' }}</td>
            <td class="col-small">{{ $row['ft']     ?? '' }}</td>

            @foreach($days as $day)
                @php
                    $cell = $row['cells'][$day->id] ?? null;
                @endphp

                {{-- Vormittag --}}
                <td class="col-small day morning" >
                    @if($cell)
                        @if($cell['excused'])
                            E
                        @elseif($cell['morning_present'] === true)
                            x
                        @elseif($cell['morning_present'] === false)
                            f
                        @endif
                    @endif
                </td>

                {{-- Nachmittag / Ende --}}
                <td class="col-small day end">
                    @if($cell)
                        @if($cell['excused'])
                            E
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

@if(!empty($partials))
    <div style="page-break-before: always; margin-top: 10px;">
        <h3 style="font-size: 10px; margin-bottom: 4px;">
            Übersicht: teilweise Anwesenheit (zu spät / früher gegangen)
        </h3>
        <table style="width:100%; border-collapse: collapse; font-size:8.5px;">
            <thead>
            <tr>
                <th style="border:0.4px solid #000; padding:2px 3px; text-align:left;">Name</th>
                <th style="border:0.4px solid #000; padding:2px 3px; text-align:left;">Datum</th>
                <th style="border:0.4px solid #000; padding:2px 3px; text-align:right;">Zu spät (Min)</th>
                <th style="border:0.4px solid #000; padding:2px 3px; text-align:right;">Früher gegangen (Min)</th>
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
                    <td style="border:0.4px solid #000; padding:2px 3px;">{{ $pName }}</td>
                    <td style="border:0.4px solid #000; padding:2px 3px;">
                        {{ $entry['date']?->format('d.m.Y') }}
                    </td>
                    <td style="border:0.4px solid #000; padding:2px 3px; text-align:right;">
                        {{ $entry['late_minutes'] ?: '—' }}
                    </td>
                    <td style="border:0.4px solid #000; padding:2px 3px; text-align:right;">
                        {{ $entry['left_early_minutes'] ?: '—' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

</body>
</html>
