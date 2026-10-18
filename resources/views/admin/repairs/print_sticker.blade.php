<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sticker - {{ $job->job_no }}</title>
    <style>
        @page {
            size: 50mm 30mm;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 2mm 3mm;
            font-family: Arial, sans-serif;
            text-align: center;
            width: 50mm;
            height: 30mm;
            box-sizing: border-box;
            background: white;
            color: black;
            overflow: hidden;
        }
        .header {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1mm;
        }
        .barcode {
            max-height: 12mm;
            width: auto;
            max-width: 44mm;
            display: inline-block;
        }
        .job-no {
            font-size: 9px;
            font-weight: bold;
            font-family: monospace;
            margin: 0.5mm 0;
        }
        .details {
            font-size: 7px;
            line-height: 1.1;
            text-align: left;
            margin-top: 0.5mm;
            border-top: 0.5px solid #000;
            padding-top: 0.5mm;
        }
        .row {
            display: flex;
            justify-content: space-between;
        }
        .no-print {
            text-align: center;
            margin-bottom: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="header">
        HEARING CARE - SERVICE
    </div>

    @php
        $cleanNo = preg_replace('/[^A-Za-z0-9\-]/', '', $job->job_no);
    @endphp
    <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($cleanNo, 'C128', 1, 24) }}" class="barcode" alt="Barcode">
    
    <div class="job-no">*{{ $cleanNo }}*</div>

    <div class="details">
        <div class="row">
            <span>Pt: <b>{{ Str::limit($job->customer?->lead?->full_name ?? 'Customer', 15) }}</b></span>
            <span>Side: <b>{{ strtoupper($job->side) }}</b></span>
        </div>
        <div class="row">
            <span>S/N: <b>{{ Str::limit($job->original_serial_no, 12) }}</b></span>
            <span>Due: <b>{{ $job->promised_date ? $job->promised_date->format('d-M') : '--' }}</b></span>
        </div>
    </div>

</body>
</html>
