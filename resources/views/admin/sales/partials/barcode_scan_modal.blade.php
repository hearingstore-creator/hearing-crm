{{-- Reusable Barcode Fast Scanner Modal & Global Hardware Listener --}}
<div class="modal fade" id="barcodeScanModal" tabindex="-1" role="dialog" aria-labelledby="barcodeScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 540px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.25);">
            
            {{-- Header --}}
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 14px 20px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.25); border: 1px solid rgba(59, 130, 246, 0.5); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #60a5fa;">
                        <i class="fas fa-barcode"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold" style="font-size: 16px; margin: 0; color: #fff;">
                            Invoice #<span id="scan_modal_inv_no">—</span>
                        </h5>
                        <div style="font-size: 11.5px; color: #94a3b8;" id="scan_modal_patient_sub">Patient Details</div>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            {{-- Body --}}
            <div class="modal-body" style="padding: 18px 20px;">
                {{-- Patient & Items Card --}}
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <strong id="scan_patient_name" style="font-size: 14px; color: #0f172a;"></strong>
                            <div id="scan_patient_phone" style="font-size: 12px; color: #64748b;"></div>
                        </div>
                        <div class="text-right">
                            <span id="scan_current_badge" class="badge" style="font-size: 11.5px; padding: 5px 10px; border-radius: 9999px;"></span>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #334155; margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 6px;">
                        <i class="fas fa-tag mr-1 text-muted"></i><b>Items:</b> <span id="scan_items_summary"></span>
                    </div>
                    <div id="scan_extra_meta" style="font-size: 11.5px; color: #64748b; margin-top: 4px; display: none;"></div>
                </div>

                {{-- Financial Overview --}}
                <div class="d-flex justify-content-between align-items-center p-3 rounded mb-3" id="scan_finance_box" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #166534;" id="scan_finance_lbl">Payment Status</div>
                        <div style="font-size: 12.5px; color: #334155;" id="scan_finance_details">Total: ৳0 | Paid: ৳0</div>
                    </div>
                    <div class="text-right">
                        <div id="scan_due_val" style="font-size: 18px; font-weight: 800; color: #15803d;">PAID</div>
                    </div>
                </div>

                {{-- Due Collection Fields (Shown if Due > 0) --}}
                <div id="scan_due_collection_wrap" style="display: none; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px;">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="scan_collect_due_chk" checked>
                        <label class="custom-control-label font-weight-bold text-danger" for="scan_collect_due_chk" style="cursor: pointer; font-size: 12.5px;">
                            <i class="fas fa-hand-holding-usd mr-1"></i>Collect Remaining Due Balance Now
                        </label>
                    </div>
                    <div id="scan_due_inputs_group">
                        <div class="row">
                            <div class="col-7 pr-1">
                                <label style="font-size: 11px; font-weight: 700; color: #475569;">Amount (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="scan_paid_amount" style="font-weight: 700; font-size: 14px;">
                            </div>
                            <div class="col-5 pl-1">
                                <label style="font-size: 11px; font-weight: 700; color: #475569;">Payment Date</label>
                                <input type="date" class="form-control form-control-sm" id="scan_payment_date" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="form-group mb-0 mt-2">
                            <label style="font-size: 11px; font-weight: 700; color: #475569;">Remarks / Note</label>
                            <input type="text" class="form-control form-control-sm" id="scan_payment_note" placeholder="Collected upon barcode handover">
                        </div>
                    </div>
                </div>

                {{-- Patient SMS Alert Option --}}
                <div id="scan_sms_wrap" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 12px; margin-bottom: 14px;">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="scan_send_sms_chk" checked>
                        <label class="custom-control-label font-weight-bold text-primary" for="scan_send_sms_chk" style="cursor: pointer; font-size: 12px;">
                            <i class="fas fa-sms mr-1"></i>Send Patient SMS Alert (<span id="scan_sms_phone_lbl"></span>)
                        </label>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="mb-2">
                    <button type="button" class="btn btn-success btn-block py-2 mb-2 btn-scan-action" id="btn_scan_deliver_fast" data-status="delivered" style="font-weight: 700; font-size: 14px; border-radius: 8px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);">
                        <i class="fas fa-check-circle mr-1"></i><span id="scan_primary_btn_text">⚡ Handover &amp; Deliver</span>
                    </button>
                </div>

                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px; display: block;">
                    Or Switch to Specific Stage:
                </label>
                <div class="d-flex flex-wrap" style="gap: 6px;" id="scan_other_stage_buttons">
                    {{-- Dynamically populated --}}
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer" style="padding: 10px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <a href="#" id="scan_link_details" target="_blank" class="btn btn-outline-secondary btn-sm" style="font-size: 11.5px; border-radius: 6px;">
                        <i class="fas fa-file-invoice mr-1"></i>View Invoice
                    </a>
                    <a href="#" id="scan_link_pos" target="_blank" class="btn btn-outline-info btn-sm ml-1" style="font-size: 11.5px; border-radius: 6px;">
                        <i class="fas fa-print mr-1"></i>POS Slip
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="font-size: 12px; border-radius: 6px;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Web Audio API Synthesizer (Instant scan feedback, zero audio files required)
function playScanAudio(type) {
    try {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        var ctx = new AudioCtx();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'success') {
            // High cheerful beep (880Hz to 1250Hz)
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1250, ctx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } else if (type === 'alert') {
            // Low double alert tone for due collection
            osc.frequency.setValueAtTime(420, ctx.currentTime);
            osc.frequency.setValueAtTime(360, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.22);
            osc.start();
            osc.stop(ctx.currentTime + 0.22);
        }
    } catch (e) {
        // AudioContext not allowed or muted
    }
}

// Active scanned sale data store
var activeScannedSale = null;

// Barcode Lookup Function
function lookupScannedBarcode(code) {
    if (!code || !code.trim()) return;
    code = code.trim().replace(/^[*#]+|[*#]+$/g, '');

    // Visual feedback on input if present
    var scanInput = $('#hub_barcode_input, #barcode_scan_input, .barcode-scan-input').first();
    var origPlaceholder = scanInput.attr('placeholder');
    scanInput.prop('disabled', true).val(code);

    $.ajax({
        url: '{{ route("sale.scan-lookup") }}',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            code: code
        },
        success: function(res) {
            scanInput.prop('disabled', false).val('');
            if (res.success) {
                activeScannedSale = res;
                populateAndOpenScanModal(res);
            } else {
                playScanAudio('alert');
                alert(res.message || 'Invoice not found');
            }
        },
        error: function(xhr) {
            scanInput.prop('disabled', false).val('');
            playScanAudio('alert');
            var err = xhr.responseJSON ? xhr.responseJSON.message : 'Error looking up barcode';
            alert(err);
        }
    });
}

// Populate Modal
function populateAndOpenScanModal(sale) {
    $('#scan_modal_inv_no').text(sale.invoice_no);
    $('#scan_patient_name').text(sale.patient_name);
    $('#scan_patient_phone').text(sale.patient_phone || 'No phone recorded');
    $('#scan_modal_patient_sub').text(sale.patient_name + (sale.patient_phone ? ' · ' + sale.patient_phone : ''));
    $('#scan_items_summary').text(sale.items_summary);

    // Current Status Badge
    $('#scan_current_badge')
        .css({ 'background': sale.badge_bg, 'color': sale.badge_color, 'border': '1px solid ' + sale.badge_color + '40' })
        .html('<i class="fas ' + sale.icon + ' mr-1"></i>' + sale.delivery_label);

    // Extra meta (promised date, lab)
    var extraHtml = '';
    if (sale.delivery_date_time) {
        extraHtml += '<span class="mr-3"><i class="far fa-clock mr-1 text-primary"></i>Promised: ' + sale.delivery_date_time + '</span>';
    }
    if (sale.lab_name) {
        extraHtml += '<span><i class="fas fa-flask mr-1 text-purple"></i>Lab: ' + sale.lab_name + '</span>';
    }
    if (extraHtml) {
        $('#scan_extra_meta').html(extraHtml).show();
    } else {
        $('#scan_extra_meta').hide();
    }

    // Financials
    var hasDue = sale.has_due;
    if (hasDue) {
        playScanAudio('alert');
        $('#scan_finance_box').css({ 'background': '#fff1f2', 'border-color': '#fecdd3' });
        $('#scan_finance_lbl').css('color', '#b91c1c').text('Pending Due Balance');
        $('#scan_finance_details').html('Total: <b>৳' + sale.total_amount.toFixed(2) + '</b> · Paid: <b>৳' + sale.paid_amount.toFixed(2) + '</b>');
        $('#scan_due_val').css('color', '#ef4444').text('৳' + sale.due_amount.toFixed(2) + ' Due');

        // Due collection inputs
        $('#scan_due_collection_wrap').show();
        $('#scan_collect_due_chk').prop('checked', true);
        $('#scan_paid_amount').val(sale.due_amount.toFixed(2)).attr('max', sale.due_amount.toFixed(2));
        $('#scan_primary_btn_text').html('<i class="fas fa-hand-holding-usd mr-1"></i>Collect ৳' + sale.due_amount.toFixed(2) + ' &amp; Handover');
    } else {
        playScanAudio('success');
        $('#scan_finance_box').css({ 'background': '#f0fdf4', 'border-color': '#bbf7d0' });
        $('#scan_finance_lbl').css('color', '#15803d').text('Payment Settled');
        $('#scan_finance_details').html('Total: <b>৳' + sale.total_amount.toFixed(2) + '</b> · Paid: <b>৳' + sale.paid_amount.toFixed(2) + '</b>');
        $('#scan_due_val').css('color', '#15803d').text('PAID');

        $('#scan_due_collection_wrap').hide();
        $('#scan_primary_btn_text').text('⚡ 1-Click Handover & Deliver');
    }

    // SMS Wrap
    if (sale.patient_phone) {
        $('#scan_sms_phone_lbl').text(sale.patient_phone);
        $('#scan_sms_wrap').show();
    } else {
        $('#scan_sms_wrap').hide();
    }

    // Render other stage buttons
    var otherBtnsHtml = '';
    if (sale.is_service) {
        otherBtnsHtml += '<button type="button" class="btn btn-outline-info btn-sm btn-scan-action" data-status="ready_for_delivery"><i class="fas fa-bell mr-1"></i>Report Ready</button>';
        otherBtnsHtml += '<button type="button" class="btn btn-outline-secondary btn-sm btn-scan-action" data-status="ordered"><i class="fas fa-clock mr-1"></i>Assessment Booked</button>';
    } else {
        otherBtnsHtml += '<button type="button" class="btn btn-outline-warning btn-sm btn-scan-action" data-status="ready_for_delivery"><i class="fas fa-user-check mr-1"></i>Ready for Fitting</button>';
        otherBtnsHtml += '<button type="button" class="btn btn-outline-purple btn-sm btn-scan-action" data-status="sent_to_lab" style="color:#7c3aed; border-color:#ddd6fe;"><i class="fas fa-flask mr-1"></i>Sent to Lab</button>';
        otherBtnsHtml += '<button type="button" class="btn btn-outline-secondary btn-sm btn-scan-action" data-status="ordered"><i class="fas fa-clock mr-1"></i>Order Placed</button>';
    }
    $('#scan_other_stage_buttons').html(otherBtnsHtml);

    // Links
    $('#scan_link_details').attr('href', sale.detail_url);
    $('#scan_link_pos').attr('href', sale.pos_print_url);

    // Show modal
    $('#barcodeScanModal').modal('show');
}

// Global Hardware Barcode Scanner Key Buffer Listener
(function() {
    var keyBuffer = '';
    var lastKeypressTime = 0;

    $(document).on('keydown', function(e) {
        var now = new Date().getTime();
        var isEnter = (e.which === 13 || e.key === 'Enter');

        // Check if Enter pressed with buffered barcode
        if (isEnter) {
            if (keyBuffer.length >= 3) {
                var barcode = keyBuffer;
                keyBuffer = '';
                lookupScannedBarcode(barcode);
                e.preventDefault();
                return;
            }
        }

        // If typing inside an input other than our barcode input, only capture if scanner speed (< 40ms)
        var targetTag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        var isInput = (targetTag === 'input' || targetTag === 'textarea' || targetTag === 'select');
        var isOurScanInput = $(e.target).is('#hub_barcode_input, #barcode_scan_input, .barcode-scan-input');

        if (e.key && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            var diff = now - lastKeypressTime;
            // Barcode scanners type with < 45ms between characters
            if (diff > 75 && keyBuffer.length > 0) {
                keyBuffer = '';
            }

            if (!isInput || isOurScanInput || diff < 45) {
                keyBuffer += e.key;
            }
            lastKeypressTime = now;
        }
    });
})();

// DOM Events
$(document).ready(function() {
    // Submit from input button or Enter in input
    $(document).on('click', '#btn_hub_barcode_scan, #btn_barcode_submit', function() {
        var input = $(this).closest('.input-group').find('input');
        lookupScannedBarcode(input.val());
    });

    $(document).on('keypress', '#hub_barcode_input, #barcode_scan_input, .barcode-scan-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            lookupScannedBarcode($(this).val());
        }
    });

    // Toggle due collection inputs
    $('#scan_collect_due_chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#scan_due_inputs_group').slideDown(150);
            if (activeScannedSale && activeScannedSale.has_due) {
                $('#scan_primary_btn_text').html('<i class="fas fa-hand-holding-usd mr-1"></i>Collect ৳' + activeScannedSale.due_amount.toFixed(2) + ' &amp; Handover');
            }
        } else {
            $('#scan_due_inputs_group').slideUp(150);
            $('#scan_primary_btn_text').text('⚡ Handover & Deliver (Keep Due Balance)');
        }
    });

    // Execute status transition from modal
    $(document).on('click', '.btn-scan-action', function(e) {
        e.preventDefault();
        if (!activeScannedSale) return;

        var btn = $(this);
        var targetStatus = btn.data('status');
        var saleId = activeScannedSale.sale_id;
        var collectDue = $('#scan_collect_due_chk').is(':checked') && activeScannedSale.has_due;
        var paidAmount = collectDue ? parseFloat($('#scan_paid_amount').val()) || 0 : 0;
        var paymentDate = $('#scan_payment_date').val();
        var paymentDetails = $('#scan_payment_note').val();
        var sendSms = $('#scan_send_sms_chk').is(':checked') ? 1 : 0;

        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Updating…');

        $.ajax({
            url: '{{ url("admin/sales") }}/' + saleId + '/update-delivery-status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                delivery_status: targetStatus,
                collect_due: collectDue ? 1 : 0,
                paid_amount: paidAmount,
                payment_date: paymentDate,
                payment_details: paymentDetails,
                send_sms: sendSms
            },
            success: function(res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    playScanAudio('success');
                    $('#barcodeScanModal').modal('hide');
                    if (window.toastr) {
                        toastr.success(res.message);
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 600);
                } else {
                    playScanAudio('alert');
                    alert(res.message || 'Status update failed');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(origHtml);
                playScanAudio('alert');
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating status';
                alert(err);
            }
        });
    });
});
</script>
