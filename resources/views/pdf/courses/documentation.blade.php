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

    $classSpeakerName = data_get($footer ?? [], 'class_speaker_name');
    $classSpeakerSignedAtRaw = data_get($footer ?? [], 'class_speaker_signed_at');
    $classSpeakerSignatureSrc = data_get($footer ?? [], 'class_speaker_signature_src');
    $classSpeakerSignedAt = $classSpeakerSignedAtRaw ? \Carbon\Carbon::parse($classSpeakerSignedAtRaw)->format('d.m.Y H:i') : null;
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
            width: 14%;
            font-weight: bold;
        }

        .header-meta .value {
            width: 26%;
        }

        .header-meta .value-wide {
            width: 46%;
            word-break: break-word;
        }

        .head-row th {
            padding: 3px 4px;
            font-size: 8px;
            text-align: center;
            font-weight: bold;
        }

        .col-tag      { width: 20%; }
        .col-content  { width: 60%; }
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
            margin-top: 22px;
            font-size: 8px;
        }

        .footer-sign .spacer {
            border: none;
        }

        .logo {
            max-width: 110px;
            max-height: 40px;
        }

        .footer-signature-img {
            max-height: 42px;
            max-width: 100%;
            display: block;
            margin: 0 auto 4px auto;
        }

        .footer-signature-meta {
            font-size: 7px;
            line-height: 1.2;
        }

        .footer-sign td {
            vertical-align: bottom;
            text-align: center;
            padding: 0;
        }

        .sig-content {
            min-height: 52px;
            padding: 4px 2px;
            text-align: center;
        }

        .sig-line {
            border-top: 0.4px solid #000;
            margin: 0 4px;
        }

        .sig-label {
            font-size: 8px;
            margin-top: 3px;
        }

        .sig-muted {
            font-size: 7px;
            color: #444;
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
        <td class="label">Standort</td>
        <td class="value">{{ $meta['location'] }}</td>
        <td class="label">Bausteinkurzbezeichnung</td>
        <td class="value-wide">{{ $meta['module'] }}</td>
    </tr>
    <tr>
        <td class="label">Von:</td>
        <td class="value">
            {{ $from->format('d.m.y') }}
            &nbsp;&nbsp;–&nbsp;&nbsp;
            {{ $to->format('d.m.y') }}
            &nbsp;{{ $meta['year'] }}
        </td>
        <td class="label">Klasse / Unterrichtstage</td>
        <td class="value-wide">{{ $meta['class_label'] }} / {{ $meta['num_days'] }}</td>
    </tr>
</table>

{{-- Tabellenkopf für Tage --}}
<table class="bordered" style="margin-top: 6px;">
    <tr class="head-row">
        <th class="col-tag">Datum / Uhrzeit</th>
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
                {{ $date->format('d.m.Y') }}<br>
                {{ $row['time_range'] }}
            </td>

            {{-- 2. Spalte: Inhalte (graue Fläche, HTML aus notes) --}}
            <td class="col-content cell-content">
                {{-- notes_html enthält deine HTML-Struktur aus course_days.notes --}}
                {!! $row['notes_html'] !!}
            </td>

            {{-- 3. Spalte: UE --}}
            <td class="col-ue" style="text-align:center;">
                {{ $row['ue'] ?? '' }}
            </td>

            {{-- 4. Spalte: Unterschrift-Feld mit Bild (falls vorhanden) --}}
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
        <td style="width: 35%;">
            <div class="sig-content">
                {{ $classSpeakerSignedAt ? \Illuminate\Support\Str::before($classSpeakerSignedAt, ' ') : '' }}
            </div>
            <div class="sig-line"></div>
            <div class="sig-label">DATUM</div>
        </td>
        <td class="spacer" style="width: 10%;"></td>
        <td style="width: 25%;">
            <div class="sig-content">
                @if($classSpeakerSignatureSrc)
                    <img src="{{ $classSpeakerSignatureSrc }}" alt="Klassensprecher-Signatur" class="footer-signature-img">
                @endif
                @if($classSpeakerName || $classSpeakerSignedAt)
                    <div class="footer-signature-meta">
                        @if($classSpeakerName)
                            <div>{{ $classSpeakerName }}</div>
                        @endif
                        @if($classSpeakerSignedAt)
                            <div class="sig-muted">{{ $classSpeakerSignedAt }}</div>
                        @endif
                    </div>
                @endif
            </div>
            <div class="sig-line"></div>
            <div class="sig-label">Unterschrift Klassensprecher</div>
        </td>
        <td class="spacer" style="width: 5%;"></td>
        <td style="width: 25%;">
            <div class="sig-content"></div>
            <div class="sig-line"></div>
            <div class="sig-label">Unterschrift Kontrolle</div>
        </td>
    </tr>
</table>

</body> 

</html>
