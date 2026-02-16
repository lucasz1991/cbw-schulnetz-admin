{{-- resources/views/pdf/courses/exam-results.blade.php --}}
@php
    $logoPath = public_path('site-images/logo.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $fromLabel = isset($from) && $from
        ? \Carbon\Carbon::parse($from)->format('d.m.Y')
        : '-';

    $toLabel = isset($to) && $to
        ? \Carbon\Carbon::parse($to)->format('d.m.Y')
        : '-';

    $tutorName = optional($course->tutor)->full_name
        ?: trim(($course->tutor->vorname ?? '') . ' ' . ($course->tutor->nachname ?? ''));

    if ($tutorName === '') {
        $tutorName = '-';
    }
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Pruefungsergebnisse</title>
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
            min-width: 74px;
            font-weight: bold;
            color: #334155;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            table-layout: fixed;
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

        .muted {
            color: #64748b;
            font-size: 9px;
        }

        .num {
            text-align: right;
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
                <div><span class="meta-k">Kurs:</span> {{ $course->title ?? '-' }}</div>
                <div><span class="meta-k">Klasse:</span> {{ $course->klassen_id ?? '-' }}</div>
                <div><span class="meta-k">Zeitraum:</span> {{ $fromLabel }} - {{ $toLabel }}</div>
            </div>
        </td>

        <td class="title-center" style="width: 40%;">
            Pruefungsergebnisse
            <div class="subtitle">Teilnehmeruebersicht mit Ergebnissen</div>
        </td>

        <td style="width: 28%;">
            <div class="meta-box">
                <div><span class="meta-k">Dozent:</span> {{ $tutorName }}</div>
                <div><span class="meta-k">Export:</span> {{ now()->format('d.m.Y H:i') }}</div>
                <div><span class="meta-k">Anzahl:</span> {{ isset($rows) ? count($rows) : 0 }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="list">
    <thead>
        <tr>
            <th style="width: 56%;">Name</th>
            <th style="width: 20%;">Geburtsdatum</th>
            <th style="width: 24%;" class="num">Punkte / Ergebnis</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        @php
            $p = $row['person'];
            $res = $row['result'] ?? null;

            $name = trim(
                ($p->nachname ?? $p->last_name ?? '') . ', ' .
                ($p->vorname ?? $p->first_name ?? '')
            );

            $dob = $p->geburt_datum ?? $p->geburtsdatum ?? null;
            $dobLabel = $dob
                ? \Carbon\Carbon::parse($dob)->format('d.m.Y')
                : '-';

            $points = $res
                ? ($res->points
                    ?? $res->score
                    ?? $res->result
                    ?? '-')
                : '-';
        @endphp

        <tr>
            <td>
                {{ $name !== ',' ? $name : '-' }}
            </td>
            <td>{{ $dobLabel }}</td>
            <td class="num">{{ $points }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="muted" style="margin-top: 6px;">
    Hinweis: Leere Werte bedeuten, dass fuer den Teilnehmer noch kein Ergebnis erfasst wurde.
</div>

</body>
</html>
