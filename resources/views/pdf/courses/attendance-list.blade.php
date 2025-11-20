{{-- resources/views/pdf/courses/attendance-list.blade.php --}}
@php
    use Illuminate\Support\Str;

    $from = $meta['date_from'];
    $to   = $meta['date_to'];
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
            margin-bottom: 6px; /* Abstand zur Tabelle */
        }
        .header-table td {
            border: none;
            padding: 2px 3px;
            vertical-align: top;
        }
        .header-left {
            width: 35%;
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
            margin-top: 30px;
            font-size: 14px;
        }

        /* Kopfzeile: keine kompletten Rahmen, nur Linie unten */
        table.attendance thead th {
            border-top: none;
            border-left: none;
            border-right: none;
            border-bottom: 0.4px solid #000;
            padding: 2px 2px 3px 2px;
            text-align: center;
            height: 16px;
            font-weight: inherit;
        }

        /* Datenzeilen: kompletter Rahmen */
        table.attendance tbody td {
            border: 0.4px solid #000;
            padding: 5px 5px;
            height: 14px;
        }

        .col-name  { width: 210px; text-align: left;  padding-left: 4px; }
        .col-small { width: 18px;  text-align: center; }

        .legend {
            margin-top: 4px;
            font-size: 7.5px;
        }
    </style>
</head>
<body>
<table class="header-table">
    <tr>
        <td class="header-left">
            Datum:
            {{ optional($from)->format('d.m.Y') }}
            –
            {{ optional($to)->format('d.m.Y') }}
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
    <tr>
        <th class="col-name">Name</th>
        <th class="col-small">Uform</th>
        <th class="col-small">Qpro</th>
        <th class="col-small">Punkte</th>
        <th class="col-small">FT</th>

        @foreach($days as $day)
            <th class="col-small">
                {{ $day->date->format('d.m.') }}<br>
                {{ Str::upper($day->date->translatedFormat('D')) }}
            </th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @foreach($rows as $row)
        @php
            $p = $row['person'];

            // Sowohl Array- als auch Objekt-Zugriff unterstützen
            $nachname = is_array($p)
                ? ($p['nachname'] ?? $p['last_name'] ?? '')
                : ($p->nachname ?? $p->last_name ?? '');

            $vorname = is_array($p)
                ? ($p['vorname'] ?? $p['first_name'] ?? '')
                : ($p->vorname ?? $p->first_name ?? '');

            $name = trim($nachname . ', ' . $vorname);
        @endphp
        <tr>
            <td class="col-name">{{ $name }}</td>

            {{-- Platzhalter-Spalten wie in der Vorlage, aktuell leer --}}
            <td class="col-small">{{ $row['uform']  ?? '' }}</td>
            <td class="col-small">{{ $row['qpro']   ?? '' }}</td>
            <td class="col-small">{{ $row['points'] ?? '' }}</td>
            <td class="col-small">{{ $row['ft']     ?? '' }}</td>

            @foreach($row['cells'] as $symbol)
                <td class="col-small">{{ $symbol }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
