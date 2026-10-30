{{-- One SMS to one doctor / referrer / employee. Shown only to managers (permission compose_&_send.team). --}}
<div class="modal fade" id="quickSmsModal" tabindex="-1" role="dialog" aria-labelledby="quickSmsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickSmsTitle"><i class="fas fa-sms mr-2"></i>Send SMS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" style="font-size:13px;">To: <strong id="quickSmsName"></strong> <span class="text-muted" id="quickSmsPhone"></span></p>
                <textarea id="quickSmsMessage" class="form-control" rows="4" maxlength="500" placeholder="Type the message..."></textarea>
                <small class="text-muted"><span id="quickSmsCount">0</span>/500 characters. Bangla text uses more SMS parts.</small>
                <div id="quickSmsResult" class="mt-2" style="font-size:13px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="quickSmsSend"><i class="fas fa-paper-plane mr-1"></i>Send</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var current = null;
    $(document).on('click', '.btn-quick-sms', function (e) {
        e.preventDefault();
        var b = $(this);
        current = { type: b.data('type'), id: b.data('id') };
        $('#quickSmsName').text(b.data('name'));
        $('#quickSmsPhone').text(b.data('phone') ? '(' + b.data('phone') + ')' : '(no mobile number saved)');
        $('#quickSmsMessage').val('');
        $('#quickSmsCount').text(0);
        $('#quickSmsResult').empty();
        $('#quickSmsSend').prop('disabled', !b.data('phone'));
        $('#quickSmsModal').modal('show');
    });
    $('#quickSmsMessage').on('input', function () { $('#quickSmsCount').text($(this).val().length); });
    $('#quickSmsSend').on('click', function () {
        var msg = $.trim($('#quickSmsMessage').val());
        if (!current || !msg) { $('#quickSmsResult').html('<span class="text-danger">Write a message first.</span>'); return; }
        var btn = $(this).prop('disabled', true);
        $.ajax({
            url: '{{ route('sms.quick-send') }}', method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            data: { person_type: current.type, person_id: current.id, message: msg }
        }).done(function (r) {
            $('#quickSmsResult').html('<span class="text-success"><i class="fas fa-check-circle mr-1"></i>' + r.message + '</span>');
            setTimeout(function () { $('#quickSmsModal').modal('hide'); }, 1200);
        }).fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not send the SMS.';
            $('#quickSmsResult').html('<span class="text-danger"><i class="fas fa-exclamation-circle mr-1"></i>' + m + '</span>');
            btn.prop('disabled', false);
        });
    });
})();
</script>
@endpush
