<tr>
    <td>
        <select class="form-control product" name="product_id[]" required>
            <option value="">Search product…</option>
        </select>
    </td>
    <td>
        <input type="text" name="batch_no[]" class="form-control batch-no" placeholder="Batch No">
    </td>
    <td>
        <input type="text" name="serial_no[]" class="form-control serial-no" placeholder="Serial No (comma separated)">
    </td>
    <td>
        <input type="text" name="stock_qty[]" class="form-control stock-qty" readonly tabindex="-1" placeholder="—">
    </td>
    <td>
        <input type="text" name="qty[]" class="form-control qty" placeholder="0" required>
    </td>
    <td>
        <input type="text" name="purchase_price[]" class="form-control purchase-price" placeholder="0.00" required>
    </td>
    <td>
        <input type="text" name="mrp_price[]" class="form-control mrp-price" placeholder="0.00" required>
    </td>
    <td>
        <input type="text" name="total_purchase_price[]" class="form-control total-purchase-price" placeholder="0.00" readonly="readonly" tabindex="-1">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm delete" title="Remove line"><i class="fas fa-trash-alt"></i></button>
    </td>
</tr>
