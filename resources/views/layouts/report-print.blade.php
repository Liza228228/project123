<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Отчёт' }}</title>
    @php
        $bodyStack = $bodyFont ?? 'Times New Roman, Times, serif';
    @endphp
    <style>
        :root {
            color-scheme: light;
        }
        body {
            font-family: {{ $bodyStack }};
            font-size: 12pt;
            line-height: 1.45;
            color: #111;
            margin: 0;
            padding: 1.5cm;
            max-width: 21cm;
            margin-left: auto;
            margin-right: auto;
        }
        .act-cap {
            font-size: 9pt;
            color: #333;
            margin-top: 0.15rem;
        }
        .act-intro {
            white-space: pre-wrap;
            word-break: break-word;
            margin: 1rem 0 1rem;
            text-align: justify;
        }
        table.act-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.5rem 0 1.25rem;
            font-size: 10.5pt;
        }
        table.act-table th,
        table.act-table td {
            border: 1px solid #000;
            padding: 0.35rem 0.45rem;
            vertical-align: top;
            text-align: left;
        }
        table.act-table th {
            font-weight: 600;
            text-align: center;
        }
        .act-footer-block {
            margin-top: 1.75rem;
        }
        .act-sig-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.35rem 1rem;
            margin-top: 0.85rem;
        }
        .act-sig-line {
            display: inline-block;
            min-width: 10rem;
            border-bottom: 1px solid #000;
            min-height: 1.1rem;
            vertical-align: bottom;
        }
        .act-member-block {
            margin-top: 1rem;
        }
        .toolbar {
            font-family: system-ui, sans-serif;
            font-size: 14px;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #fff8f0;
            border: 1px solid #e8a870;
            border-radius: 6px;
        }
        .toolbar button {
            padding: 0.45rem 1rem;
            cursor: pointer;
            background: #ea580c;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-weight: 600;
        }
        .toolbar button:hover {
            background: #c2410c;
        }
        @media print {
            .toolbar { display: none !important; }
            body { padding: 0.8cm; }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
