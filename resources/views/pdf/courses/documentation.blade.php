{{-- resources/views/pdf/courses/documentation.blade.php --}}
@php
    /** @var \Carbon\Carbon $from */
    /** @var \Carbon\Carbon $to */
    $from = $meta['date_from'];
    $to   = $meta['date_to'];

    // Logo laden
    $logoPath = public_path('site-images/logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Unterrichtsdokumentation</title>
    <style>
        @page {
            margin: 20px 25px 30px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .bordered td,
        .bordered th {
            border: 0.4px solid #000;
        }

        .header-top td {
            border: 0.4px solid #000;
            padding: 4px 6px;
        }

        .header-top .left {
            width: 35%;
            font-weight: bold;
        }

        .header-top .center {
            width: 35%;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }

        .header-top .right {
            width: 30%;
            text-align: center;
            font-size: 11px;
        }

        .header-meta td {
            border: 0.4px solid #000;
            padding: 3px 5px;
            font-size: 8px;
        }

        .header-meta .label {
            width: 12%;
            font-weight: bold;
        }

        .header-meta .value {
            width: 13%;
        }

        .header-meta .value-wide {
            width: 25%;
        }

        .head-row th {
            padding: 3px 4px;
            font-size: 8px;
            text-align: center;
            font-weight: bold;
        }

        .col-tag      { width: 12%; }
        .col-time     { width: 10%; }
        .col-content  { width: 58%; }
        .col-ue       { width: 5%;  }
        .col-sign     { width: 15%; }

        .day-row td {
            font-size: 8px;
            vertical-align: top;
            padding: 3px 4px;
        }

        .cell-day-label {
            white-space: nowrap;
        }

        .cell-content {
            background-color: #e6e6e6;
        }

        .cell-content p {
            margin: 0 0 2px 0;
        }

        .cell-content ul {
            margin: 0 0 2px 12px;
            padding: 0;
        }

        .cell-content li {
            margin: 0;
        }

        .sign-cell {
            text-align: center;
            font-size: 7px;
        }
        .sign-cell img {
            max-height: 45px;
            max-width: 100%;
        }

        .sign-cell-inner {
            min-height: 55px;
        }

        .sign-label-top {
            display: block;
            margin-bottom: 22px;
        }

        .sign-label-bottom {
            display: block;
            margin-top: 15px;
        }

        .footer-sign {
            margin-top: 20px;
            font-size: 8px;
        }

        .footer-sign td {
            padding-top: 20px;
            border-top: 0.4px solid #000;
            text-align: center;
        }

        .footer-sign .spacer {
            border: none;
        }

        .logo {
            max-width: 110px;
            max-height: 40px;
        }
    </style>
</head>
<body>

{{-- Oberer Formblatt-Kopf --}}
<table class="header-top">
    <tr>
        <td class="left">
            Formblatt<br>
            <span style="font-size:8px;">Unterrichtsdokumentation</span>
        </td>
        <td class="center">
            Unterrichtsdokumentation {{ $from->format('d.m.') }}-{{ $to->format('d.m.') }}.{{ $from->format('Y') }}
        </td>
        <td class="right">
            {{-- Platz für Logo / Text --}}
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo" class="logo">
            @else
                CBW GmbH
            @endif
        </td>
    </tr>
</table>

{{-- Meta-Zeile (ähnlich deinem Formular) --}}
<table class="header-meta" style="margin-top: 4px;">
    <tr>
        <td class="label">CBW</td>
        <td class="value-wide"></td>
        <td class="label">Standort</td>
        <td class="value-wide">{{ $meta['location'] }}</td>
        <td class="label">Bausteinkurzbezeichnung</td>
        <td class="value">{{ $meta['module'] }}</td>
    </tr>
    <tr>
        <td class="label">Von:</td>
        <td class="value">
            {{ $from->format('d.m.y') }}
            &nbsp;&nbsp;–&nbsp;&nbsp;
            {{ $to->format('d.m.y') }}
            &nbsp;{{ $meta['year'] }}
        </td>
        <td class="label">Klasse:</td>
        <td class="value">{{ $meta['class_label'] }}</td>
        <td class="label">Anzahl der Unterrichtstage</td>
        <td class="value">{{ $meta['num_days'] }}</td>
    </tr>
</table>

{{-- Tabellenkopf für Tage --}}
<table class="bordered" style="margin-top: 6px;">
    <tr class="head-row">
        <th class="col-tag">Datum/Uhrzeit</th>
        <th class="col-time"></th>
        <th class="col-content">V E R M I T T E L T E &nbsp;&nbsp; I N H A L T E (Hauptpunkte)</th>
        <th class="col-ue">UE</th>
        <th class="col-sign">Unterschrift Instruktor/-in</th>
    </tr>

    {{-- Tage untereinander --}}
    @foreach($rows as $row)
        @php
            /** @var \Carbon\Carbon $date */
            $date = $row['date'];
        @endphp
        <tr class="day-row">
            {{-- 1. Spalte: "1. Tag" + Datum --}}
            <td class="col-tag cell-day-label">
                {{ $row['index'] }}. Tag<br>
                {{ $date->format('d.m.Y') }}
            </td>

            {{-- 2. Spalte: Uhrzeit --}}
            <td class="col-time">
                {{ $row['time_range'] }}
            </td>

            {{-- 3. Spalte: Inhalte (graue Fläche, HTML aus notes) --}}
            <td class="col-content cell-content">
                {{-- notes_html enthält deine HTML-Struktur aus course_days.notes --}}
                {!! $row['notes_html'] !!}
            </td>

            {{-- 4. Spalte: UE --}}
            <td class="col-ue" style="text-align:center;">
                {{ $row['ue'] ?? '' }}
            </td>

            {{-- 5. Spalte: Unterschrift-Feld mit Bild (falls vorhanden) --}}
            <td class="col-sign sign-cell">
                <div class="sign-cell-inner">
                    @if(!empty($row['tutor_signature_src']))
                        <img src="{{ $row['tutor_signature_src'] }}" alt="Tutor-Signatur">
                    @else
                        <span class="sign-label-top">
                            Unterschrift<br>Instruktor/-in
                        </span>
                    @endif

                    <span class="sign-label-bottom">
                        {{ $meta['tutor_name'] }}
                    </span>
                </div>
            </td>
        </tr>
    @endforeach
</table>

{{-- Abschluss-Signaturzeile wie im Beispiel --}}
<table class="footer-sign">
    <tr>
        <td style="width: 35%;">DATUM</td>
        <td class="spacer" style="width: 10%;"></td>
        <td style="width: 25%;">Unterschrift Klassensprecher</td>
        <td class="spacer" style="width: 5%;"></td>
        <td style="width: 25%;">Unterschrift Kontrolle</td>
    </tr>
</table>

</body> 

</html>
