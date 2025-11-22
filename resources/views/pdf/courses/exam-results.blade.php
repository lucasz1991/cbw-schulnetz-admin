{{-- resources/views/pdf/courses/exam-results.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Prüfungsergebnisse</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-table td { padding: 2px 4px; vertical-align: top; }
        .title-center { text-align: center; font-weight: bold; font-size: 12px; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.list th, table.list td {
            border: 0.4px solid #000;
            padding: 3px 4px;
        }
        table.list th { text-align: left; background: #f5f5f5; }
    </style>
</head>
<body>
<table class="header-table">
    <tr>
        <td>
            Kurs: {{ $course->title ?? '—' }}<br>
            Klasse: {{ $course->klassen_id ?? '—' }}<br>
            Zeitraum:
            {{ optional($course->planned_start_date)->format('d.m.Y') ?? '—' }}
            –
            {{ optional($course->planned_end_date)->format('d.m.Y') ?? '—' }}
        </td>
        <td class="title-center">
            Prüfungsergebnisse
        </td>
        <td style="text-align: right">
            Dozent: {{ $course->tutor->full_name ?? trim(($course->tutor->vorname ?? '').' '.($course->tutor->nachname ?? '')) ?? '—' }}
        </td>
    </tr>
</table>

<table class="list">
    <thead>
    <tr>
        <th style="width: 180px;">Name</th>
        <th style="width: 80px;">Geburtsdatum</th>
        <th style="width: 60px;">Note</th>
        <th style="width: 60px;">Punkte</th>
        <th>Bemerkungen</th>
    </tr>
    </thead>
    <tbody>
    @foreach($participants as $p)
        @php
            $name = trim(($p->nachname ?? $p->last_name ?? '').', '.($p->vorname ?? $p->first_name ?? ''));
            $dob  = $p->geburtsdatum
                ? \Carbon\Carbon::parse($p->geburtsdatum)->format('d.m.Y')
                : '';
        @endphp
        <tr>
            <td>{{ $name }}</td>
            <td>{{ $dob }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
