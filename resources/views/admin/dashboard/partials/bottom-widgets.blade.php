<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    
    <!-- Lead Source Performance -->
    <div class="modern-card" style="padding: 24px;">
        <h2 style="font-size: var(--font-size-md); font-weight: 600; color: var(--page-text); margin: 0 0 20px 0;">Lead Status Performance</h2>
        
        <div style="display: flex; flex-direction: column; gap: 16px;">
            @php 
                $leadDistribution = $statistics['charts']['lead_status_distribution'] ?? [];
                $totalLeads = $statistics['overview']['total_leads'] ?? 1; // Prevent div by zero
                if($totalLeads == 0) $totalLeads = 1;
                $colors = ['var(--accent-500)', 'var(--info-500)', 'var(--warning-500)', 'var(--success-500)'];
            @endphp

            @forelse($leadDistribution as $index => $status)
                @php
                    $percentage = ($status->count / $totalLeads) * 100;
                    $color = $colors[$index % count($colors)];
                @endphp
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: var(--font-size-sm); margin-bottom: 6px;">
                        <span style="color: var(--page-text); font-weight: 500;">{{ ucfirst($status->lead_status) }}</span>
                        <span style="color: var(--page-text-muted);">{{ $status->count }}</span>
                    </div>
                    <div style="width: 100%; background: var(--card-border); border-radius: 4px; height: 8px; overflow: hidden;">
                        <div style="width: {{ $percentage }}%; background: {{ $color }}; height: 100%; border-radius: 4px;"></div>
                    </div>
                </div>
            @empty
                <div style="color: var(--page-text-muted); font-size: var(--font-size-sm); text-align: center; padding: 20px 0;">No lead data available</div>
            @endforelse
        </div>
    </div>

    <!-- Product Mix Donut Chart -->
    <div class="modern-card" style="padding: 24px; display: flex; flex-direction: column;">
        <h2 style="font-size: var(--font-size-md); font-weight: 600; color: var(--page-text); margin: 0 0 20px 0;">Product Category Mix</h2>
        
        <div style="display: flex; align-items: center; justify-content: space-between; flex: 1;">
            <div style="width: 140px; height: 140px; position: relative;">
                <canvas id="productMixChart"></canvas>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                    <div style="font-size: var(--font-size-lg); font-weight: 700; color: var(--page-text);">{{ $statistics['overview']['total_products'] ?? 0 }}</div>
                    <div style="font-size: 10px; color: var(--page-text-muted);">Products</div>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px; padding-left: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 12px; height: 12px; background: #6366f1; border-radius: 2px;"></span>
                    <span style="font-size: var(--font-size-sm); color: var(--page-text-secondary);">Hearing Aids</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 12px; height: 12px; background: #3b82f6; border-radius: 2px;"></span>
                    <span style="font-size: var(--font-size-sm); color: var(--page-text-secondary);">Accessories</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 12px; height: 12px; background: #e2e8f0; border-radius: 2px;"></span>
                    <span style="font-size: var(--font-size-sm); color: var(--page-text-secondary);">Batteries</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('productMixChart').getContext('2d');
    
    // Function to get CSS variable values
    const getVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Hearing Aids', 'Accessories', 'Batteries'],
            datasets: [{
                data: [65, 20, 15],
                backgroundColor: ['#6366f1', '#3b82f6', '#e2e8f0'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: getVar('--card-bg') || '#fff',
                    titleColor: getVar('--page-text') || '#1e293b',
                    bodyColor: getVar('--page-text-secondary') || '#64748b',
                    borderColor: getVar('--card-border') || '#e2e8f0',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.label + ': ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
