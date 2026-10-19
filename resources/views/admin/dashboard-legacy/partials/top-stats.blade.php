<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Card 1: Total Sales -->
    <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div class="stat-label">Total Sales</div>
                <div class="stat-value">৳{{ number_format($statistics['overview']['total_sales'] ?? 0) }}</div>
            </div>
            <div class="stat-icon" style="background: var(--accent-50); color: var(--accent-600);">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>
        <div class="stat-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i>
            <span>{{ $statistics['sales']['total']['count'] ?? 0 }} orders</span>
        </div>
    </div>

    <!-- Card 2: Net Profit -->
    <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div class="stat-label">Net Profit</div>
                <div class="stat-value">৳{{ number_format($statistics['financials']['net_profit'] ?? 0) }}</div>
            </div>
            <div class="stat-icon" style="background: var(--success-50); color: var(--success-600);">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>
        @php $isProfit = ($statistics['financials']['net_profit'] ?? 0) >= 0; @endphp
        <div class="stat-trend {{ $isProfit ? 'up' : 'down' }}">
            <i class="fa-solid fa-arrow-trend-{{ $isProfit ? 'up' : 'down' }}"></i>
            <span>From last period</span>
        </div>
    </div>

    <!-- Card 3: Total Customers -->
    <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div class="stat-label">Total Customers</div>
                <div class="stat-value">{{ number_format($statistics['overview']['total_customers'] ?? 0) }}</div>
            </div>
            <div class="stat-icon" style="background: var(--warning-50); color: var(--warning-600);">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="stat-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i>
            <span>+{{ $statistics['customers']['this_month'] ?? 0 }} this month</span>
        </div>
    </div>

    <!-- Card 4: Conversion Rate -->
    <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <div class="stat-label">Conversion Rate</div>
                <div class="stat-value">{{ number_format($statistics['performance']['averages']['conversion_rate'] ?? 0, 1) }}%</div>
            </div>
            <div class="stat-icon" style="background: var(--info-50); color: var(--info-600);">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
        <div class="stat-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i>
            <span>{{ $statistics['overview']['total_leads'] ?? 0 }} total leads</span>
        </div>
    </div>
</div>
