/**
 * SMS Module - Vanilla JS
 */

document.addEventListener('DOMContentLoaded', function () {

    // ==========================================
    // Template Selection → Auto-fill body, variables, audience
    // ==========================================
    var templateSelect = document.getElementById('smsTemplateSelect');
    var messageBody = document.getElementById('messageBody');
    var variableChipsContainer = document.getElementById('variableChipsContainer');
    var smsVariableChips = document.getElementById('smsVariableChips');
    var audienceType = document.getElementById('audienceType');
    var loadBtn = document.getElementById('loadRecipientsBtn');

    // Category → audience type mapping
    var categoryAudienceMap = {
        'warranty': 'warranty',
        'lead_onboarding': 'lead',
        'customer_onboarding': 'customer',
        'appointment': 'appointment',
        'membership': 'member'
    };

    if (templateSelect) {
        templateSelect.addEventListener('change', function () {
            var option = this.options[this.selectedIndex];

            if (!this.value) {
                // Reset
                if (messageBody) messageBody.value = '';
                if (variableChipsContainer) variableChipsContainer.style.display = 'none';
                if (smsVariableChips) smsVariableChips.innerHTML = '';
                if (audienceType) audienceType.value = '';
                if (loadBtn) loadBtn.disabled = true;
                messageBody.dispatchEvent(new Event('input'));
                return;
            }

            // Fill message body
            var body = option.getAttribute('data-body');
            if (body && messageBody) {
                messageBody.value = body;
                messageBody.dispatchEvent(new Event('input'));
            }

            // Set audience from category
            var category = option.getAttribute('data-category');
            if (audienceType) {
                audienceType.value = categoryAudienceMap[category] || category;
            }

            // Build variable chips from template's variables
            var variables = JSON.parse(option.getAttribute('data-variables') || '[]');
            if (smsVariableChips && variableChipsContainer) {
                smsVariableChips.innerHTML = '';
                variables.forEach(function (v) {
                    var chip = document.createElement('span');
                    chip.className = 'sms-variable-chip';
                    chip.setAttribute('data-variable', v);
                    chip.textContent = '{{' + v + '}}';
                    smsVariableChips.appendChild(chip);
                });
                variableChipsContainer.style.display = variables.length ? '' : 'none';
            }

            // Enable load recipients
            if (loadBtn) loadBtn.disabled = false;

            // Auto-load recipients
            if (loadBtn) loadBtn.click();

            // Attach click handlers to new chips
            bindVariableChips();
        });
    }

    // ==========================================
    // Variable Chip Click → Insert at Cursor
    // ==========================================
    function bindVariableChips() {
        var variableChips = document.querySelectorAll('#smsVariableChips .sms-variable-chip');
        variableChips.forEach(function (chip) {
            chip.onclick = function () {
                if (!messageBody) return;
                var variable = this.getAttribute('data-variable');
                var start = messageBody.selectionStart;
                var end = messageBody.selectionEnd;
                var text = messageBody.value;
                var insert = '{{' + variable + '}}';
                messageBody.value = text.substring(0, start) + insert + text.substring(end);
                messageBody.selectionStart = messageBody.selectionEnd = start + insert.length;
                messageBody.focus();
                messageBody.dispatchEvent(new Event('input'));
            };
        });
    }

    // ==========================================
    // Load Recipients via AJAX
    // ==========================================
    var recipientList = document.getElementById('recipientList');
    var recipientCount = document.getElementById('recipientCount');

    if (loadBtn) {
        loadBtn.addEventListener('click', function () {
            var selectedAudience = audienceType ? audienceType.value : '';
            if (!selectedAudience) {
                recipientList.innerHTML = '<div class="sms-recipient-empty"><i class="fas fa-exclamation-circle"></i><p>Please select a template first.</p></div>';
                recipientCount.textContent = '0';
                return;
            }

            loadBtn.disabled = true;
            loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading...';

            var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value;

            fetch(SMS_BASE_URL + '/sms/recipients', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ audience_type: selectedAudience })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                recipientCount.textContent = data.count;
                if (data.recipients.length === 0) {
                    recipientList.innerHTML = '<div class="sms-recipient-empty"><i class="fas fa-user-slash"></i><p>No recipients found for selected template audience.</p></div>';
                    return;
                }
                var html = '';
                data.recipients.forEach(function (r) {
                    html += '<div class="sms-recipient-item">';
                    html += '<span class="sms-recipient-name">' + escapeHtml(r.name) + '</span>';
                    html += '<span class="sms-recipient-phone">' + escapeHtml(r.phone) + '</span>';
                    html += '</div>';
                });
                recipientList.innerHTML = html;
            })
            .catch(function () {
                recipientList.innerHTML = '<div class="sms-recipient-empty"><i class="fas fa-times-circle"></i><p>Error loading recipients.</p></div>';
            })
            .finally(function () {
                loadBtn.disabled = false;
                loadBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Load Recipients';
            });
        });
    }

    // ==========================================
    // Character Counter + SMS Count + Live Preview
    // ==========================================
    var charCount = document.getElementById('smsCharCount');
    var smsCount = document.getElementById('smsCount');
    var previewText = document.getElementById('smsPreviewText');

    // Preview sample values for seeder variables
    var previewSamples = {
        'name': 'John Doe',
        'company_phone': '017XXXXXXXX',
        'customer_id': 'C-202607051',
        'warranty_expiry_date': '15 Aug 2026',
        'appointment_date': '10 Jul 2026',
        'tier': 'Gold',
        'points': '500'
    };

    if (messageBody) {
        messageBody.addEventListener('input', function () {
            var len = this.value.length;
            if (charCount) charCount.textContent = len;
            if (smsCount) smsCount.textContent = Math.ceil(len / 160) || 1;

            if (previewText) {
                if (len === 0) {
                    previewText.textContent = 'Your message preview will appear here...';
                    previewText.classList.add('sms-preview-placeholder');
                } else {
                    previewText.classList.remove('sms-preview-placeholder');
                    var text = this.value;
                    text = text.replace(/\{\{(\w+)\}\}/g, function (match, key) {
                        return previewSamples[key] || match;
                    });
                    previewText.textContent = text;
                }
            }
        });
    }

    // ==========================================
    // Template Card Click → Select in Composer
    // ==========================================
    var templateCards = document.querySelectorAll('.sms-template-card');
    templateCards.forEach(function (card) {
        var useBtn = card.querySelector('.sms-template-use-btn');
        if (useBtn) {
            useBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var templateId = this.getAttribute('data-template-id');
                if (templateSelect && templateId) {
                    templateSelect.value = templateId;
                    templateSelect.dispatchEvent(new Event('change'));
                }
                // Switch to compose tab
                var composeTab = document.querySelector('a[href="#compose-tab"]');
                if (composeTab) composeTab.click();
            });
        }
    });

    // ==========================================
    // Form Validation - Template required
    // ==========================================
    var composeForm = document.getElementById('smsComposeForm');
    if (composeForm) {
        composeForm.addEventListener('submit', function (e) {
            if (templateSelect && !templateSelect.value) {
                e.preventDefault();
                alert('Please select a template first.');
                return;
            }
            if (!confirm('Are you sure you want to send this campaign? This will send SMS to all recipients matching the selected template audience.')) {
                e.preventDefault();
            }
        });
    }

    // ==========================================
    // Helper: Escape HTML
    // ==========================================
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
