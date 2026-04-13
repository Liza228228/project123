@extends('layouts.report-print', ['title' => 'Отчёт по заявкам', 'bodyFont' => $mainFont])

@section('content')
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Печать / сохранить в PDF</button>
    </div>

    @if($headerSettings)
        @include('applications.report.partials.act_header', ['s' => $headerSettings])
    @endif

    @if(trim((string) $mainBodyText) !== '')
        <div class="act-intro">{{ $mainBodyText }}</div>
    @endif

    <table class="act-table" style="font-family: {{ $tableFont }};">
        <thead>
            <tr>
                <th style="width:3rem;">№ п/п</th>
                <th>Наименование</th>
                <th style="width:5rem;">Ед. измер.</th>
                <th style="width:4rem;">Кол-во</th>
                <th style="width:28%;">Примечание</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tableRows as $row)
                <tr>
                    <td style="text-align:center;">{{ $row['n'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td style="text-align:center;">{{ $row['unit'] }}</td>
                    <td style="text-align:center;">{{ $row['qty'] }}</td>
                    <td>{{ $row['note'] }}</td>
                </tr>
            @endforeach
            @for($i = 0; $i < $blankTailRows; $i++)
                <tr>
                    <td style="text-align:center;">{{ count($tableRows) + $i + 1 }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    @if($footerSettings)
        @include('applications.report.partials.act_footer', ['s' => $footerSettings])
    @endif
@endsection
