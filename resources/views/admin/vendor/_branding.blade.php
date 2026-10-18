{{-- Branch invoice branding: logo shown on invoices / receipts / purchase prints for this branch.
     Expects optional $vendor (edit). Address, mobile and email come from the main fields. --}}
@php $currentLogo = isset($vendor) && !empty($vendor->logo) ? asset($vendor->logo) : null; @endphp
<div class="crm-form-section crm-animate-in delay-2">
    <div class="crm-form-section-header">
        <div class="section-icon" style="background: var(--primary-500, var(--accent-500));"><i class="fas fa-image"></i></div>
        <h6>Invoice Branding</h6>
    </div>
    <div class="crm-form-section-body">
        <div class="vbrand">
            <div class="vbrand-preview" id="vendor-logo-preview">
                <img id="vendor-logo-img" src="{{ $currentLogo ?? '' }}" alt="Branch logo" @if(!$currentLogo) style="display:none" @endif>
                <span id="vendor-logo-empty" @if($currentLogo) style="display:none" @endif><i class="fas fa-store"></i><small>No logo</small></span>
            </div>
            <div class="vbrand-body">
                <label for="vendor-logo">Branch Logo</label>
                <input type="file" class="form-control" id="vendor-logo" name="logo" accept="image/png,image/jpeg,image/webp">
                <small class="text-muted d-block mt-1">PNG / JPG / WEBP, max 2 MB. A wide logo with a transparent background (about 400×120 px) prints best.</small>
                @if ($currentLogo)
                    <label class="mt-2 mb-0" style="font-weight: 500;">
                        <input type="checkbox" name="remove_logo" value="1" id="vendor-remove-logo"> Remove current logo
                    </label>
                @endif
                <div class="vbrand-note mt-2">
                    <i class="fas fa-info-circle"></i>
                    Invoices made by this branch print this logo with the branch <strong>Name, Address, Mobile and Email</strong> above.
                    Any empty field uses the company details from <a href="{{ url('admin/setting') }}">Settings</a>.
                </div>
            </div>
        </div>
    </div>
</div>

@push('css')
<style>
    .vbrand { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
    .vbrand-preview {
        width: 180px; height: 100px; border-radius: 12px; flex-shrink: 0;
        border: 2px dashed var(--border-color, rgba(0,0,0,.15));
        background: var(--bg-secondary, rgba(0,0,0,.02));
        display: flex; align-items: center; justify-content: center; overflow: hidden;
        transition: border-color .2s ease;
    }
    .vbrand-preview:hover { border-color: var(--accent-500); }
    .vbrand-preview img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 8px; }
    .vbrand-preview span { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--text-muted, #94a3b8); }
    .vbrand-preview span i { font-size: 26px; }
    .vbrand-body { flex: 1; min-width: 240px; }
    .vbrand-note {
        font-size: 12.5px; padding: 8px 12px; border-radius: 8px;
        background: color-mix(in srgb, var(--accent-500) 8%, transparent);
        color: var(--text-secondary, inherit);
    }
    .vbrand-note i { color: var(--accent-500); margin-right: 4px; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('vendor-logo');
        var img = document.getElementById('vendor-logo-img');
        var empty = document.getElementById('vendor-logo-empty');
        var remove = document.getElementById('vendor-remove-logo');
        var original = img ? img.getAttribute('src') : '';
        if (!input) return;
        input.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result; img.style.display = ''; empty.style.display = 'none';
                if (remove) remove.checked = false;
            };
            reader.readAsDataURL(file);
        });
        if (remove) {
            remove.addEventListener('change', function () {
                if (this.checked) { img.style.display = 'none'; empty.style.display = ''; input.value = ''; }
                else if (original) { img.src = original; img.style.display = ''; empty.style.display = 'none'; }
            });
        }
    })();
</script>
@endpush
