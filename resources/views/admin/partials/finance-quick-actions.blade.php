@php
    $user = auth()->user();
    if (!$user) return;

    $actions = [
        [
            'title' => 'Collect Patient Due',
            'short_title' => 'Collect Due',
            'url' => url('admin/customer-payment'),
            'route_pattern' => 'admin/customer-payment*',
            'icon' => 'fas fa-hand-holding-dollar',
            'theme' => 'green',
            'can' => $user->hasAccess('customer_list.edit|customer.payment|customer_payment.create|customer.index'),
            'badge' => null,
        ],
        [
            'title' => 'Record Expense Voucher',
            'short_title' => 'Add Expense',
            'url' => url('admin/expense/create'),
            'route_pattern' => 'admin/expense/create*',
            'icon' => 'fas fa-receipt',
            'theme' => 'rose',
            'can' => $user->hasAccess('expense.create|expense.index|accounts.index'),
            'badge' => null,
        ],
        [
            'title' => 'Supplier Payments & Ledger',
            'short_title' => 'Pay Supplier',
            'url' => url('admin/suppliers-payment'),
            'route_pattern' => 'admin/suppliers-payment*',
            'icon' => 'fas fa-truck-loading',
            'theme' => 'amber',
            'can' => $user->hasAccess('supplier_payment.index|supplier_payment.create|supplier.payment|supplier.index'),
            'badge' => null,
        ],
        [
            'title' => 'Doctor Referral Settlements',
            'short_title' => 'Doctor Settlement',
            'url' => url('admin/doctors-payment'),
            'route_pattern' => 'admin/doctors-payment*',
            'icon' => 'fas fa-user-md',
            'theme' => 'violet',
            'can' => $user->hasAccess('doctor_payment.index|doctor_payment.create|doctor.payment|doctor.index'),
            'badge' => null,
        ],
        [
            'title' => 'Master Financial Ledger',
            'short_title' => 'Master Ledger',
            'url' => url('admin/report/ledger'),
            'route_pattern' => 'admin/report/ledger*',
            'icon' => 'fas fa-book-open',
            'theme' => 'indigo',
            'can' => $user->hasAccess('ledger.index|accounts.index'),
            'badge' => null,
        ],
        [
            'title' => 'Unpaid & Partial Invoices',
            'short_title' => 'Patient Due List',
            'url' => url('admin/sales/due-list'),
            'route_pattern' => 'admin/sales/due-list*',
            'icon' => 'fas fa-file-invoice-dollar',
            'theme' => 'blue',
            'can' => $user->hasAccess('patient_due_list.index|sales.due_list|sale_list.index|sales.index'),
            'badge' => null,
        ],
    ];

    $visibleActions = array_filter($actions, fn($a) => $a['can']);
@endphp

@if(count($visibleActions) > 0)
<div class="finance-quick-bar crm-animate-in mb-4">
    <div class="fqb-inner">
        <div class="fqb-label">
            <span class="fqb-icon-sparkle"><i class="fas fa-bolt"></i></span>
            <span class="fqb-text">Finance Hub</span>
        </div>
        <div class="fqb-actions">
            @foreach($visibleActions as $action)
                @php
                    $isActive = request()->is($action['route_pattern']);
                @endphp
                <a href="{{ $action['url'] }}" 
                   class="fqb-btn fqb-btn-{{ $action['theme'] }} {{ $isActive ? 'is-active' : '' }}" 
                   title="{{ $action['title'] }}">
                    <i class="{{ $action['icon'] }}"></i>
                    <span>{{ $action['short_title'] }}</span>
                    @if($isActive)
                        <span class="fqb-active-dot"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>

<style>
.finance-quick-bar {
    background: #ffffff;
    border: 1px solid var(--gray-200, #e2e8f0);
    border-radius: 12px;
    padding: 8px 14px;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
    transition: all 0.2s ease;
}
.finance-quick-bar:hover {
    box-shadow: 0 4px 14px -3px rgba(15, 23, 42, 0.08);
}
.fqb-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.fqb-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--gray-600, #475569);
    padding-right: 8px;
    border-right: 1px solid var(--gray-200, #e2e8f0);
}
.fqb-icon-sparkle {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35);
}
.fqb-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1;
}
.fqb-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.18s ease;
    border: 1px solid transparent;
    white-space: nowrap;
}
.fqb-btn:hover {
    transform: translateY(-1px);
}
.fqb-active-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    display: inline-block;
    margin-left: 2px;
    animation: fqbPulse 1.8s infinite;
}
@keyframes fqbPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}

/* Green (Collections) */
.fqb-btn-green { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.fqb-btn-green:hover, .fqb-btn-green.is-active { background: #10b981; color: #ffffff; border-color: #059669; box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.4); }

/* Rose (Expenses) */
.fqb-btn-rose { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.fqb-btn-rose:hover, .fqb-btn-rose.is-active { background: #f43f5e; color: #ffffff; border-color: #e11d48; box-shadow: 0 4px 12px -2px rgba(244, 63, 94, 0.4); }

/* Amber (Suppliers) */
.fqb-btn-amber { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.fqb-btn-amber:hover, .fqb-btn-amber.is-active { background: #f59e0b; color: #ffffff; border-color: #d97706; box-shadow: 0 4px 12px -2px rgba(245, 158, 11, 0.4); }

/* Violet (Doctors) */
.fqb-btn-violet { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
.fqb-btn-violet:hover, .fqb-btn-violet.is-active { background: #8b5cf6; color: #ffffff; border-color: #7c3aed; box-shadow: 0 4px 12px -2px rgba(139, 92, 246, 0.4); }

/* Indigo (Ledger) */
.fqb-btn-indigo { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.fqb-btn-indigo:hover, .fqb-btn-indigo.is-active { background: #6366f1; color: #ffffff; border-color: #4f46e5; box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.4); }

/* Blue (Due List) */
.fqb-btn-blue { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.fqb-btn-blue:hover, .fqb-btn-blue.is-active { background: #3b82f6; color: #ffffff; border-color: #2563eb; box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.4); }

@media (max-width: 768px) {
    .fqb-label {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--gray-200, #e2e8f0);
        padding-bottom: 6px;
    }
}
</style>
@endif
