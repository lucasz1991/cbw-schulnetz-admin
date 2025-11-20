{{-- resources/views/pdf/courses/doku.blade.php --}}
@php
    use Illuminate\Support\Str;

    $from = $meta['date_from'] ?? null;
    $to   = $meta['date_to']   ?? null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Unterrichtsdokumentation</title>
    <style>
        @page {
            margin: 18px 18px 26px 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        /* Kopfbereich */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .header-table td {
            border: none;
            padding: 2px 3px;
            vertical-align: top;
        }
        .header-left  { width: 35%; }
        .header-center{
            width: 30%;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        .header-right {
            width: 35%;
            text-align: right;
            font-size: 9px;
        }

        /* Tabelle */
        table.doku {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        /* Kopfzeile: nur Linie unten */
        table.doku thead th {
            border-top: none;
            border-left: none;
            border-right: none;
            border-bottom: 0.4px solid #000;
            padding: 2px 2px 3px 2px;
            text-align: center;
            font-weight: bold;
        }

        /* Datenzeilen: kompletter Rahmen, etwas höher */
        table.doku tbody td {
            border: 0.4px solid #000;
            padding: 3px 4px;
            vertical-align: top;
            line-height: 1.15;
        }

        .col-date  { width: 55px;  text-align: center; }
        .col-day   { width: 25px;  text-align: center; }
        .col-time  { width: 60px;  text-align: center; }
        .col-notes { width: auto;  text-align: left; }
        .col-sign  { width: 90px;  text-align: center; }

        /* Inhaltstext innerhalb der Doku-Zelle */
        .notes-content p {
            margin: 0 0 3px 0;
        }
        .notes-content ul,
        .notes-content ol {
            margin: 0 0 3px 12px;
            padding: 0;
        }
        .notes-content li {
            margin: 0 0 2px 0;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="header-left">
            Zeitraum:
            {{ $from ? $from->format('d.m.Y') : '—' }}
            –
            {{ $to ? $to->format('d.m.Y') : '—' }}<br>
            Raum: {{ $meta['room'] ?? '—' }}
        </td>
        <td class="header-center">
            Unterrichtsdokumentation
        </td>
        <td class="header-right">
            Baustein {{ $meta['module'] ?? '—' }}<br>
            Klasse {{ $meta['class_label'] ?? '—' }}<br>
            Dozent: {{ $meta['tutor_name'] ?? '—' }}
        </td>
    </tr>
</table>

<table class="doku">
    <thead>
        <tr>
            <th class="col-date">Datum</th>
            <th class="col-day">Tag</th>
            <th class="col-time">Zeit</th>
            <th class="col-notes">Unterrichtsinhalt / Dokumentation</th>
            <th class="col-sign">Unterschrift<br>Dozent/in</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            @php
                /** @var \Carbon\Carbon $d */
                $d = $row['date'];
            @endphp
            <tr>
                <td class="col-date">{{ $d->format('d.m.Y') }}</td>
                <td class="col-day">{{ Str::upper($d->translatedFormat('D')) }}</td>
                <td class="col-time">{{ $row['time_range'] }}</td>
                <td class="col-notes">
                    <div class="notes-content">
                        {!! $row['notes_html'] !!}
                    </div>
                </td>
                <td class="col-sign"></td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
