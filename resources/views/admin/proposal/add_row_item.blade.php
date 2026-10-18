@php
    $type = $itemType ?? 'product';
    $idx = $index ?? time();
@endphp
<tr class="proposal-item-row" data-index="{{ $idx }}" data-type="{{ $type }}">
    <td style="width: 125px; vertical-align: top;">
        <select class="form-control item-type-select" name="items[{{ $idx }}][item_type]" style="height: 38px !important; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a;">
            <option value="product" {{ $type === 'product' ? 'selected' : '' }}>📦 Product</option>
            <option value="service" {{ $type === 'service' ? 'selected' : '' }}>🩺 Service</option>
            <option value="custom" {{ $type === 'custom' ? 'selected' : '' }}>📝 Custom</option>
        </select>
    </td>
    <td style="vertical-align: top;">
        {{-- Product Select --}}
        <div class="item-select-product" style="{{ $type === 'product' ? '' : 'display: none;' }}">
            <select class="form-control select2-product product-id-input" name="items[{{ $idx }}][product_id]">
                <option value="">— Select Hearing Aid / Product —</option>
                @foreach($products as $prod)
                    <option value="{{ $prod->id }}" 
                        data-name="{{ $prod->product_name }}" 
                        data-price="{{ $prod->latest_mrp }}"
                        data-warranty="{{ $prod->warranty_sales_months ?: 24 }}"
                        data-category="{{ $prod->category->category_name ?? '' }}">
                        {{ $prod->product_name }}@if($prod->latest_mrp > 0) (৳{{ number_format($prod->latest_mrp, 2) }})@endif
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Service Select --}}
        <div class="item-select-service" style="{{ $type === 'service' ? '' : 'display: none;' }}">
            <select class="form-control select2-service service-id-input" name="items[{{ $idx }}][service_id]">
                <option value="">— Select Clinical / Diagnostic Service —</option>
                @foreach($serviceTypes as $svc)
                    <option value="{{ $svc->id }}" 
                        data-name="{{ $svc->service_type_name }}" 
                        data-price="{{ $svc->price ?: 0 }}">
                        {{ $svc->service_type_name }} (৳{{ number_format($svc->price ?: 0, 2) }})
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Custom Title --}}
        <div class="item-custom-title" style="{{ $type === 'custom' ? '' : 'display: none;' }}">
            <input type="text" class="form-control item-name-input" name="items[{{ $idx }}][item_name]" placeholder="Enter Custom Item / Service Name" value="" style="height: 38px !important; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;">
        </div>

        {{-- Hidden synced title input when in product/service mode so items array receives item_name --}}
        <input type="hidden" class="item-name-hidden" name="items[{{ $idx }}][item_name_hidden]" value="">

        {{-- Remarks / Specification --}}
        <input type="text" class="form-control item-desc-input mt-1" name="items[{{ $idx }}][description]" placeholder="Remarks: Ear side (Left/Right/Binaural), color, trial notes..." style="height: 28px !important; font-size: 11px; color: #64748b; border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 8px;">
    </td>
    <td style="width: 100px; vertical-align: top;">
        <div class="input-group" style="width: 100%;">
            <input type="number" class="form-control warranty-input text-center font-weight-bold" name="items[{{ $idx }}][warranty_months]" placeholder="24" value="24" min="0" style="height: 38px !important; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px 0 0 6px;">
            <div class="input-group-append">
                <span class="input-group-text px-1" style="font-size: 11px; height: 38px !important; width: 28px; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1; border-left: none; border-radius: 0 6px 6px 0; color: #64748b; font-weight: 600;">mo</span>
            </div>
        </div>
    </td>
    <td style="width: 115px; vertical-align: top;">
        <div class="qty-stepper">
            <button type="button" class="btn-qty btn-qty-minus" title="Decrease Quantity">−</button>
            <input type="number" step="any" min="0.01" class="form-control qty-input" name="items[{{ $idx }}][qty]" value="1" required autocomplete="off">
            <button type="button" class="btn-qty btn-qty-plus" title="Increase Quantity">+</button>
        </div>
    </td>
    <td style="width: 130px; vertical-align: top;">
        <input type="number" step="any" min="0" class="form-control unit-price-input text-right font-weight-bold" name="items[{{ $idx }}][unit_price]" value="0.00" required autocomplete="off" style="height: 38px !important; font-size: 13px !important; font-weight: 700; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px !important;">
    </td>
    <td style="width: 115px; vertical-align: top;">
        <div class="input-group" style="width: 100%;">
            <input type="number" step="any" min="0" max="100" class="form-control discount-percent-input text-right" name="items[{{ $idx }}][discount_percent]" value="0" placeholder="0" autocomplete="off" style="height: 38px !important; font-size: 13px !important; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 6px 0 0 6px; padding: 0 6px !important;">
            <div class="input-group-append">
                <span class="input-group-text px-1" style="font-size: 11px; height: 38px !important; width: 26px; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1; border-left: none; border-radius: 0 6px 6px 0; color: #64748b; font-weight: 700;">%</span>
            </div>
        </div>
    </td>
    <td style="width: 135px; vertical-align: top;">
        <div class="input-group" style="width: 100%;">
            <div class="input-group-prepend">
                <span class="input-group-text px-1" style="font-size: 11px; height: 38px !important; width: 26px; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1; border-right: none; border-radius: 6px 0 0 6px; color: #64748b; font-weight: 700;">৳</span>
            </div>
            <input type="number" step="any" min="0" class="form-control discount-taka-input discount-input text-right" name="items[{{ $idx }}][discount]" value="0.00" placeholder="0.00" title="Discount in ৳" autocomplete="off" style="height: 38px !important; font-size: 13px !important; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 0 6px 6px 0; padding: 0 8px !important;">
        </div>
    </td>
    <td style="width: 140px; vertical-align: top;">
        <input type="text" class="form-control total-price-input text-right font-weight-bold" name="items[{{ $idx }}][total_price]" value="0.00" readonly style="height: 38px !important; font-size: 14px !important; font-weight: 800; color: #1e293b; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding-right: 10px;">
    </td>
    <td style="width: 48px; text-align: center; vertical-align: top;">
        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" title="Remove line" style="width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
