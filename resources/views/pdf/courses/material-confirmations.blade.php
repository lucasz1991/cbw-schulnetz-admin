{{-- resources/views/pdf/courses/material-confirmations.blade.php --}}
@php
    $course = $course ?? null;
    $materials = $course?->materials ?? [];

    $logoPath = public_path('site-images/logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Material-Bestätigungen</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
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
            min-width: 64px;
            font-weight: bold;
            color: #334155;
        }

        .section-title {
            margin-top: 8px;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 10px;
            color: #0f172a;
        }

        .table-gap {
            margin-top: 14px;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        table.list th,
        table.list td {
            border: 0.4px solid #cbd5e1;
            padding: 4px 5px;
        }
        table.list th {
            text-align: left;
            background: #eef2f7;
            color: #334155;
            font-weight: bold;
        }

        .signature-img {
            max-height: 40px;
            max-width: 100%;
        }

        .subline {
            font-size: 9px;
            color: #64748b;
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
                <div><span class="meta-k">Kurs:</span> {{ $course->title ?? '—' }}</div>
                <div><span class="meta-k">Klasse:</span> {{ $course->klassen_id ?? '—' }}</div>
                <div><span class="meta-k">Zeitraum:</span>
                    {{ optional($course->planned_start_date)->format('d.m.Y') ?? '—' }}
                    –
                    {{ optional($course->planned_end_date)->format('d.m.Y') ?? '—' }}
                </div>
            </div>
        </td>

        <td class="title-center" style="width: 40%;">
            Material-Bestätigungen
            <div class="subtitle">Teilnehmerbestätigung der bereitgestellten Bildungsmittel</div>
        </td>

        <td style="width: 28%;">
            <div class="meta-box">
                <div><span class="meta-k">Dozent:</span>
                    {{ $course->tutor->full_name
                        ?? trim(($course->tutor->vorname ?? '').' '.($course->tutor->nachname ?? ''))
                        ?? '—' }}
                </div>
                <div><span class="meta-k">Export:</span> {{ now()->format('d.m.Y H:i') }}</div>
            </div>
        </td>
    </tr>
</table>

@if(!empty($materials))
    <div class="section-title">Bildungsmittel (Titel / Verlag / ISBN)</div>
    <table class="list">
        <thead>
        <tr>
            <th>Titel</th>
            <th style="width: 180px;">Verlag</th>
            <th style="width: 150px;">ISBN</th>
        </tr>
        </thead>
        <tbody>
        @foreach($materials as $m)
            <tr>
                <td>
                    {{ $m['titel'] ?? '—' }}
                    @if(!empty($m['titel2']))
                        <div class="subline">{{ $m['titel2'] }}</div>
                    @endif
                </td>
                <td>{{ $m['verlag'] ?? '—' }}</td>
                <td>{{ $m['isbn'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="section-title table-gap">Teilnehmer-Bestätigungen</div>
<table class="list">
    <thead>
    <tr>
        <th style="width: 180px;">Name</th>
        <th style="width: 80px;">Geburtsdatum</th>
        <th style="width: 130px;">Bestätigt am</th>
        <th>Unterschrift Teilnehmer</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        @php
            /** @var \App\Models\Person $person */
            $person = $row['person'];
            $ack    = $row['ack'] ?? null;
        @endphp

        <tr>
            <td>
                {{ $person->nachname }}, {{ $person->vorname }}
            </td>

            <td>
                {{ optional($person->geburt_datum)->format('d.m.Y') ?? '—' }}
            </td>

            <td>
                {{ $ack?->acknowledged_at?->format('d.m.Y H:i') ?? '—' }}
            </td>

            <td style="text-align: center;">
                @if(!empty($row['signature_src']))
                    <img src="{{ $row['signature_src'] }}"
                         alt="Unterschrift Teilnehmer"
                         class="signature-img">
                @else
                    —
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>