<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <title>{{ $setting->company_name }}</title>
</head>

<body>
    <div class="pos-print">
        <p class="centered">
            <strong class="text-body company-name">{{ $setting->company_name }}</strong> <br>
            {{ $setting->address }} <br>
            {{ $setting->email }} <br>
            @if (!empty($setting->mobile))
                Mobile: {{ $setting->mobile }}
            @endif
        </p>

        <p class="centered memo-info">
            Invoice: <strong>{{ $saleMaster->sale_invoice_no }}</strong><br>
            Date: <strong>{{ date('F j, Y', strtotime($saleMaster->date)) }}</strong>
        </p>

        <p class="centered">
            Customer: <strong>{{ $saleMaster->customer->name }}</strong>
        </p>

        <table>
            <thead>
                <tr class="item">
                    <th>
                        <span>Medicine</span>
                    </th>
                    <th>
                        <span>Qty</span>
                    </th>
                    <th class="text-right">
                        <span>Price</span>
                    </th>
                    <th class="text-right">
                        <span>Discount</span>
                    </th>
                    <th class="text-right">
                        <span>Amount</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $subTotalDiscount = 0;
                @endphp
                @foreach ($saleDetail as $item)
                    <tr class="item">
                        @php
                            $discountTaka = (($item->qty * $item->mrp_price) / 100) * $item->discount;
                            $totalPrice = $item->qty * $item->mrp_price - $discountTaka;
                            $subtotal += $totalPrice;
                            $subTotalDiscount += $discountTaka;
                            $invoiceDiscount = $saleMaster->total_discount - $subTotalDiscount;
                        @endphp
                        <td class="quantity">{{ $item->medicine->medicine_name }}</td>
                        <td class="description">{{ $item->qty }}</td>
                        <td class="price">{{ $item->mrp_price }}</td>
                        <td class="price">{{ $discountTaka }}</td>
                        <td>{{ number_format($totalPrice, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="last-part">
                    <td colspan="2">Sales By <strong>{{ Auth::user()->name }}</strong></td>
                    <td class="text-right" colspan="2">Sub Total :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($subtotal, 2) }}</nobr>
                    </td>
                </tr>
                <tr>
                    <td class="text-right" colspan="4">Vat :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($saleMaster->vat, 2) }}</nobr>
                    </td>
                </tr>
                <tr>
                    <td class="text-right" colspan="4">Invoice Discount :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($saleMaster->invoiceDiscount, 2) }}</nobr>
                    </td>
                </tr>
                <tr>
                    <td class="text-right" colspan="4">Grand Total :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($subtotal - $invoiceDiscount + $saleMaster->vat, 2) }}</nobr>
                    </td>
                </tr>
                <tr>
                    <td class="text-right" colspan="4">Paid Amount :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($saleMaster->paid, 2) }}</nobr>
                    </td>
                </tr>
                <tr>
                    <td class="text-right" colspan="4">Due :</td>
                    <td class="text-right">
                        <nobr>{{ number_format($saleMaster->total_amount - $saleMaster->paid, 2) }}</nobr>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="centered"><b>Thanks for your purchase!</b></p>
    </div>

    <script src="{{ asset('assets/receipt-print.js') }}"></script>
</body>

</html>
