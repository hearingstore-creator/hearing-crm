<tr data-index="{{ $index }}">
    <td>
        <select class="form-control product" name="product_id[]" required>
            <option value="">Select Product</option>
        </select>
    </td>
    <td>
        <select class="form-control batch" name="batch_id[]">
            <option value="">Select Batch</option>
        </select>
    </td>
    <td>
        <input type="text" autocomplete="off" name="stock_qty[]" class="form-control stock-qty" readonly>
    </td>
    <td>
        <select class="form-control serial-no" name="serial_no[{{ $index }}][]" multiple>
            <option value="">Select Serial No</option>
        </select>
    </td>
    <td>
        <input type="text" name="qty[]" class="form-control qty" required>
    </td>
    <td>
        <input type="hidden" name="purchase_price[]" class="form-control purchase-price" required>
        <input type="text" name="unit_price[]" class="form-control unit-price" required>
    </td>
    <td>
        <input type="text" name="discount[]" class="form-control discount" value="0">
    </td>
    <td>
        <input type="text" name="discount_taka[]" class="form-control discount-taka" value="0">
    </td>
    <td>
        <input type="text" name="total_price[]" class="form-control total-price" readonly="readonly">
    </td>
    <td>
        <input type="button" class="btn btn-danger btn-sm delete" value="x">
    </td>
</tr>
