{{-- resources/views/pdf/courses/documentation.blade.php --}}
@php
    /** @var \Carbon\Carbon $from */
    /** @var \Carbon\Carbon $to */
    $from = $meta['date_from'];
    $to   = $meta['date_to'];

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
        @page { margin: 20px 20px 30px 20px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        table { width: 100%; border-collapse: collapse; }

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
            min-width: 105px;
            font-weight: bold;
            color: #334155;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.list th,
        table.list td {
            border: 0.4px solid #cbd5e1;
            padding: 5px 6px;
        }
        table.list th {
            text-align: left;
            background: #eef2f7;
            color: #334155;
            font-weight: bold;
        }

        .head-row th {
            font-size: 8px;
            text-align: center;
            padding: 4px 5px;
        }

        .col-tag     { width: 20%; }
        .col-content { width: 60%; }
        .col-ue      { width: 5%; }
        .col-sign    { width: 15%; }

        .day-row td {
            font-size: 8px;
            vertical-align: top;
            padding: 4px 5px;
        }

        .cell-day-label { white-space: nowrap; }

        .cell-content {
            background-color: #f8fafc;
            line-height: 1.25;
        }
        .cell-content p { margin: 0 0 2px 0; }
        .cell-content ul { margin: 0 0 2px 12px; padding: 0; }
        .cell-content li { margin: 0; }

        .sign-cell {
            text-align: center;
            font-size: 7px;
        }
        .sign-cell img {
            max-height: 45px;
            max-width: 100%;
        }
        .sign-cell-inner { min-height: 55px; }
        .sign-label-top { display: block; margin-bottom: 22px; color: #64748b; }
        .sign-label-bottom { display: block; margin-top: 15px; color: #334155; }

        .footer-sign {
            margin-top: 22px;
            font-size: 8px;
        }
        .footer-sign td {
            vertical-align: bottom;
            text-align: center;
            padding: 0;
        }
        .footer-sign .spacer { border: none; }

        .footer-signature-img {
            max-height: 42px;
            max-width: 100%;
            display: block;
            margin: 0 auto 4px auto;
        }

        .footer-signature-meta { font-size: 7px; line-height: 1.2; }

        .sig-content {
            min-height: 52px;
            padding: 4px 2px;
            text-align: center;
        }

        .sig-line {
            border-top: 0.4px solid #cbd5e1;
            margin: 0 4px;
        }

        .sig-label {
            font-size: 8px;
            margin-top: 3px;
            color: #334155;
            font-weight: bold;
        }

        .sig-muted { font-size: 7px; color: #64748b; }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 32%;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo" class="logo">
            @endif
            <div class="meta-box">
                <div><span class="meta-k">Standort:</span> {{ $meta['location'] ?? '-' }}</div>
                <div><span class="meta-k">Klasse:</span> {{ $meta['class_label'] ?? '-' }}</div>
                <div><span class="meta-k">Unterrichtstage:</span> {{ $meta['num_days'] ?? '-' }}</div>
            </div>
        </td>

        <td class="title-center" style="width: 40%;">
            Unterrichtsdokumentation
            <div class="subtitle">
                {{ $from->format('d.m.') }}-{{ $to->format('d.m.') }}.{{ $from->format('Y') }}
            </div>
        </td>

        <td style="width: 28%;">
            <div class="meta-box">
                <div><span class="meta-k">Baustein:</span> {{ $meta['module'] ?? '-' }}</div>
                <div><span class="meta-k">Zeitraum:</span> {{ $from->format('d.m.Y') }} - {{ $to->format('d.m.Y') }}</div>
                <div><span class="meta-k">Dozent/in:</span> {{ $meta['tutor_name'] ?? '-' }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="list">
    <tr class="head-row">
        <th class="col-tag">Datum / Uhrzeit</th>
        <th class="col-content">V E R M I T T E L T E  I N H A L T E (Hauptpunkte)</th>
        <th class="col-ue">UE</th>
        <th class="col-sign">Unterschrift Instruktor/-in</th>
    </tr>

    @foreach($rows as $row)
        @php
            /** @var \Carbon\Carbon $date */
            $date = $row['date'];
        @endphp
        <tr class="day-row">
            <td class="col-tag cell-day-label">
                {{ $row['index'] }}. Tag<br>
                {{ $date->format('d.m.Y') }}<br>
                {{ $row['time_range'] }}
            </td>

            <td class="col-content cell-content">
                {!! $row['notes_html'] !!}
            </td>

            <td class="col-ue" style="text-align:center;">
                {{ $row['ue'] ?? '' }}
            </td>

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
                        {{ $meta['tutor_name'] ?? '-' }}
                    </span>
                </div>
            </td>
        </tr>
    @endforeach
</table>

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
