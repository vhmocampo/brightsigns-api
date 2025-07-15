<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Estimate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .customer-info {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }
        .customer-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .line-items {
            margin: 25px 0;
        }
        .line-items table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .line-items th,
        .line-items td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .line-items th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        .line-items tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total-section {
            background-color: #27ae60;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin: 25px 0;
        }
        .total-section h3 {
            margin: 0;
            font-size: 20px;
        }
        .quote-details {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }
        .quote-details p {
            margin: 5px 0;
            color: #7f8c8d;
        }
        .original-request {
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
        }
        .original-request h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        .notes {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Quote Estimate</h1>
        </div>

        <div class="customer-info">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> {{ $quoteRequest->name }}</p>
            <p><strong>Email:</strong> {{ $quoteRequest->email }}</p>
            <p><strong>Request Date:</strong> {{ $quoteRequest->created_at ? $quoteRequest->created_at->format('F j, Y \a\t g:i A') : 'N/A' }}</p>
        </div>

        @if($quoteRequest->original_request)
        <div class="original-request">
            <h4>Original Request</h4>
            <p>{{ $quoteRequest->original_request }}</p>
        </div>
        @endif

        <div class="line-items">
            <h3>Quote Details</h3>
            @if($lineItems->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lineItems as $item)
                        <tr>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>{{ $item->description ?: 'No description provided' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>${{ number_format($item->unit_price, 2) }}</td>
                            <td>${{ number_format($item->total_price, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>No line items found for this quote estimate.</p>
            @endif
        </div>

        {{-- <div class="total-section">
            <h3>Total Estimate: ${{ number_format($total_amount, 2) }}</h3>
        </div> --}}

        @if($quoteEstimate->notes)
        <div class="notes">
            <h4>Additional Notes</h4>
            <p>{{ $quoteEstimate->notes }}</p>
        </div>
        @endif

        <div class="quote-details">
            <p><strong>Quote Status:</strong> {{ ucfirst($quoteEstimate->status) }}</p>
            @if($quoteEstimate->completed_at)
                <p><strong>Completed:</strong> {{ $quoteEstimate->completed_at->format('F j, Y \a\t g:i A') }}</p>
            @endif
            <p><strong>Quote UUID:</strong> {{ $quoteEstimate->uuid }}</p>
        </div>
    </div>
</body>
</html>
