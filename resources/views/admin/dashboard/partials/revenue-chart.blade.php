<div class="modern-card" style="padding: 24px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: var(--font-size-md); font-weight: 600; color: var(--page-text); margin: 0 0 8px 0;">Revenue vs Purchases</h2>
            <div style="display: flex; align-items: baseline; gap: 12px;">
                <span style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--page-text);">৳{{ number_format($statistics['overview']['total_sales'] ?? 0, 2) }}</span>
                <span style="font-size: var(--font-size-sm); font-weight: 500; color: var(--success-500);"><i class="fas fa-arrow-up"></i> Sales Lead</span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; background: #6366f1; border-radius: 2px;"></span>
                <span style="font-size: var(--font-size-sm); color: var(--page-text-secondary);">Sales</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; background: #10b981; border-radius: 2px;"></span>
                <span style="font-size: var(--font-size-sm); color: var(--page-text-secondary);">Purchases</span>
            </div>
            <select class="form-control" style="width: auto; padding: 4px 12px; height: auto;">
                <option>Monthly</option>
            </select>
        </div>
    </div>
    
    <div style="height: 300px; width: 100%;">
        <canvas id="revenueChart"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Gradient for Sales
    let salesGradient = ctx.createLinearGradient(0, 0, 0, 300);
    salesGradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
    salesGradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    const chartData = @json($statistics['charts']['monthly_sales'] ?? []);
    const purchaseData = @json($statistics['charts']['monthly_purchases'] ?? []);
    
    const labels = chartData.map(item => item.month);
    const sales = chartData.map(item => item.total);
    const purchases = purchaseData.map(item => item.total);

    // Function to get CSS variable values
    const getVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales',
                    data: sales,
                    borderColor: '#6366f1',
                    backgroundColor: salesGradient,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Purchases',
                    data: purchases,
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: getVar('--card-bg') || '#fff',
                    titleColor: getVar('--page-text') || '#1e293b',
                    bodyColor: getVar('--page-text-secondary') || '#64748b',
                    borderColor: getVar('--card-border') || '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ৳' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.1)',
                        drawBorder: false,
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 },
                        callback: function(value) {
                            if (value >= 1000) return '৳' + (value/1000) + 'k';
                            return '৳' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false,
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                }
            }
        }
    });
});
</script>
@endpush
