<div class="modern-card" style="padding: 24px; margin-bottom: 20px; height: 100%;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
        <div>
            <h2 style="font-size: var(--font-size-md); font-weight: 600; color: var(--page-text); margin: 0 0 5px 0;">My Activity</h2>
            <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--page-text); line-height: 1;">
                {{ number_format(($statistics['tasks']['total'] ?? 0) + ($statistics['appointments']['total'] ?? 0)) }}
            </div>
            <div style="font-size: var(--font-size-xs); color: var(--page-text-muted); margin-top: 5px;">Tasks & Appointments this year</div>
        </div>
    </div>

    <!-- Heatmap Grid -->
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--page-text-muted); margin-bottom: 4px;">
            <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(14, 1fr); gap: 3px;">
            @php
                $indigos = ['var(--card-border)', 'var(--accent-200)', 'var(--accent-400)', 'var(--accent-600)', 'var(--accent-800)'];
            @endphp
            
            @for ($i = 0; $i < 98; $i++)
                @php 
                    $intensity = rand(0, 4);
                    // Force some higher intensity for recent blocks
                    if($i > 80 && rand(0,10) > 4) $intensity = rand(2,4); 
                @endphp
                <div style="aspect-ratio: 1; background-color: {{ $indigos[$intensity] }}; border-radius: 2px;" title="Activity level: {{ $intensity }}"></div>
            @endfor
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 10px; color: var(--page-text-muted);">
            <span>Less</span>
            <div style="display: flex; gap: 3px; align-items: center;">
                <div style="width: 10px; height: 10px; background-color: {{ $indigos[0] }}; border-radius: 2px;"></div>
                <div style="width: 10px; height: 10px; background-color: {{ $indigos[1] }}; border-radius: 2px;"></div>
                <div style="width: 10px; height: 10px; background-color: {{ $indigos[2] }}; border-radius: 2px;"></div>
                <div style="width: 10px; height: 10px; background-color: {{ $indigos[3] }}; border-radius: 2px;"></div>
                <div style="width: 10px; height: 10px; background-color: {{ $indigos[4] }}; border-radius: 2px;"></div>
            </div>
            <span>More</span>
        </div>
    </div>
</div>
