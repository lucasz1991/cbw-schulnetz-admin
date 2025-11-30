{{-- resources/views/pdf/courses/exam-results.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Prüfungsergebnisse</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .header-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .logo {
            width: 120px;
        }

        .title-center {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding-top: 8px;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        table.list th,
        table.list td {
            border: 0.4px solid #000;
            padding: 3px 4px;
        }
        table.list th {
            text-align: left;
            background: #f5f5f5;
        }
    </style>
</head>
<body>

@php
    // Laden des Logos
    $logoPath = public_path('site-images/logo.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $fromLabel = isset($from) && $from
        ? \Carbon\Carbon::parse($from)->format('d.m.Y')
        : '—';

    $toLabel = isset($to) && $to
        ? \Carbon\Carbon::parse($to)->format('d.m.Y')
        : '—';

    $tutorName = optional($course->tutor)->full_name
        ?: trim(($course->tutor->vorname ?? '') . ' ' . ($course->tutor->nachname ?? ''));
    if ($tutorName === '') {
        $tutorName = '—';
    }
@endphp


<table class="header-table">
    <tr>
        <td style="width: 140px;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" class="logo">
            @endif
        </td>

        <td class="title-center">
            Prüfungsergebnisse
        </td>

        <td style="text-align: right; font-size: 10px;">
            Kurs: {{ $course->title ?? '—' }}<br>
            Klasse: {{ $course->klassen_id ?? '—' }}<br>
            Zeitraum: {{ $fromLabel }} – {{ $toLabel }}<br>
            Dozent: {{ $tutorName }}
        </td>
    </tr>
</table>


<table class="list">
    <thead>
        <tr>
            <th style="width: 60%;">Name</th>
            <th style="width: 20%;">Geburtsdatum</th>
            <th style="width: 20%;">Punkte</th>
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
                : '';

            $points = $res
                ? ($res->points
                    ?? $res->score
                    ?? $res->result
                    ?? '')
                : '';
        @endphp

        <tr>
            <td>{{ $name }}</td>
            <td>{{ $dobLabel }}</td>
            <td>{{ $points }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
