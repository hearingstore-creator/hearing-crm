<tr data-index="{{ $index }}" class="service-row">
    <td>
        <select class="form-control service-type" name="service_type_id[]">
            <option value="">Select Service Type</option>
            @foreach($serviceTypes as $serviceType)
                <option value="{{ $serviceType->id }}" data-price="{{ $serviceType->price ?? 0 }}">{{ $serviceType->service_type_name }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" name="service_qty[]" class="form-control service-qty" value="1" required>
    </td>
    <td>
        <input type="text" name="service_price[]" class="form-control service-price" placeholder="0.00" required>
    </td>
    <td>
        <input type="text" name="service_discount[]" class="form-control service-discount" value="0">
    </td>
    <td>
        <input type="text" name="service_discount_taka[]" class="form-control service-discount-taka" value="0">
    </td>
    <td>
        <input type="text" name="service_total_price[]" class="form-control service-total-price" placeholder="0.00" readonly="readonly" tabindex="-1">
    </td>
    <td>
        <button type="button" class="btn btn-danger btn-sm delete-service" title="Remove line"><i class="fas fa-trash-alt"></i></button>
    </td>
</tr>