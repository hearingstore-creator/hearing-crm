<tr>
    <td>
        <input type="hidden" name="stock_qty[]" class="stock-qty" value="0">
        <select class="form-control product" name="product_id[]">
            <option value="">Select</option>
        </select>
    </td>
    <td>
        <input type="text" name="batch_no[]" class="form-control batch-no" placeholder="Batch No">
    </td>
    <td>
        <input type="text" name="serial_no[]" class="form-control serial-no" placeholder="Serial No (comma separated)">
    </td>
    <td>
        <input type="text" name="stock_qty[]" class="form-control stock-qty" readonly>
    </td>
    <td>
        <input type="text" name="qty[]" class="form-control qty" required>
    </td>
    <td>
        <input type="text" name="purchase_price[]" class="form-control purchase-price" required>
    </td>
    <td>
        <input type="text" name="mrp_price[]" class="form-control mrp-price" required>
    </td>
    <td>
        <input type="text" name="total_purchase_price[]" class="form-control total-purchase-price" readonly="readonly">
    </td>
    <td>
        <input type="button" class="btn btn-danger btn-sm delete" value="x">
    </td>
</tr>
