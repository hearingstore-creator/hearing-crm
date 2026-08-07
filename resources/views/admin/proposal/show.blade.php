<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal #{{ $proposal->id }}</title>
    <!-- Bootstrap CSS for table styling -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .proposal-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            background: white;
        }
        .proposal-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #4a90d9;
            padding-bottom: 20px;
        }
        .proposal-title {
            font-size: 36px;
            color: #4a90d9;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .proposal-subtitle {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }
        .proposal-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .info-section {
            flex: 1;
        }
        .info-section h3 {
            font-size: 16px;
            color: #4a90d9;
            margin-bottom: 10px;
            text-transform: uppercase;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }
        .info-section p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-section strong {
            color: #555;
        }
        .proposal-content {
            margin: 30px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .proposal-content h2 {
            font-size: 24px;
            color: #4a90d9;
            margin-bottom: 15px;
            border-bottom: 2px solid #4a90d9;
            padding-bottom: 10px;
        }
        .proposal-content-text {
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Table styling */
        .table {
            width: 100% !important;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            border-top: 0;
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
        }

        .proposal-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            display: flex;
            justify-content: space-between;
        }
        .footer-section {
            flex: 1;
        }
        .footer-section h4 {
            font-size: 14px;
            color: #4a90d9;
            margin-bottom: 10px;
        }
        .footer-section p {
            margin: 5px 0;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }
        .status-new {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-draft {
            background: #e2e3e5;
            color: #383d41;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4a90d9;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .print-button:hover {
            background: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
        .print-button i {
            margin-right: 8px;
        }
        .amount-display {
            font-size: 28px;
            color: #28a745;
            font-weight: bold;
            text-align: right;
            margin: 20px 0;
        }
        .amount-label {
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background: white;
            }
            .proposal-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Print Proposal
    </button>

    <div class="proposal-container">
        <!-- Header -->
        <div class="proposal-header">
            <h1 class="proposal-title">PROPOSAL</h1>
            <p class="proposal-subtitle">{{ $proposal->title }}</p>
        </div>

        <!-- Proposal Info -->
        <div class="proposal-info">
            <div class="info-section">
                <h3><i class="fas fa-file-alt"></i> Proposal Details</h3>
                <p><strong>Proposal #:</strong> {{ str_pad($proposal->id, 6, '0', STR_PAD_LEFT) }}</p>
                <p><strong>Date:</strong> {{ $proposal->proposal_date ? date('F d, Y', strtotime($proposal->proposal_date)) : 'N/A' }}</p>
                <p><strong>Valid Until:</strong> {{ $proposal->valid_until ? date('F d, Y', strtotime($proposal->valid_until)) : 'N/A' }}</p>
                <p><strong>Status:</strong>
                    <span class="status-badge
                        @if($proposal->status === \App\Enums\ProposalStatus::ACCEPTED->value) status-accepted
                        @elseif($proposal->status === \App\Enums\ProposalStatus::DECLINED->value) status-declined
                        @elseif($proposal->status === \App\Enums\ProposalStatus::NEW->value) status-new
                        @else status-draft @endif">
                        {{ $proposal->status }}
                    </span>
                </p>
            </div>

            <div class="info-section">
                <h3><i class="fas fa-user"></i> Recipient Information</h3>
                @if($proposal->customer)
                    <p><strong>Name:</strong> {{ $proposal->customer->lead->full_name ?? 'N/A' }}</p>
                    <p><strong>Email:</strong> {{ $proposal->customer->lead->email ?? 'N/A' }}</p>
                    <p><strong>Phone:</strong> {{ $proposal->customer->lead->phone ?? 'N/A' }}</p>
                @else
                    <p><strong>Email:</strong> {{ $proposal->email ?? 'N/A' }}</p>
                @endif
            </div>
        </div>

        <!-- Amount -->
        @if($proposal->total_amount)
        <div class="amount-display">
            <span class="amount-label">Total Amount:</span><br>
            {{ number_format($proposal->total_amount, 2) }}
        </div>
        @endif

        <!-- Content -->
        <div class="proposal-content">
            <h2><i class="fas fa-file-contract"></i> Proposal Details</h2>
           
            <div>
                @if ($proposal->content)
                   {!! $proposal->content ?: 'No content provided.' !!} 
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="proposal-footer">
            <div class="footer-section">
                <h4><i class="fas fa-building"></i> Company Information</h4>
                <p><strong>{{ config('app.name', 'Your Company') }}</strong></p>
                <p>Generated: {{ date('F d, Y g:i A') }}</p>
            </div>
            <div class="footer-section" style="text-align: right;">
                <h4><i class="fas fa-info-circle"></i> Terms & Conditions</h4>
                <p>This proposal is valid until the expiration date mentioned above.</p>
                <p>Please contact us for any questions or clarifications.</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print on load (optional - remove if not needed)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
