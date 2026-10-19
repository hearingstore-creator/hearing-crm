{{-- Tabs shared by the two activity log pages. --}}
<div style="display:flex; gap:4px; border-bottom:1px solid var(--card-border); margin:0 0 14px; font-size:14px;" id="log-tabs">
    <a href="{{ url('admin/activity-log') }}" style="padding:8px 14px; text-decoration:none; {{ $active === 'changes' ? 'border-bottom:2px solid var(--accent-500); color:var(--accent-600); font-weight:600;' : 'color:var(--page-text-muted);' }}">Changes</a>
    <a href="{{ url('admin/activity-log?tab=signins') }}" style="padding:8px 14px; text-decoration:none; {{ $active === 'signins' ? 'border-bottom:2px solid var(--accent-500); color:var(--accent-600); font-weight:600;' : 'color:var(--page-text-muted);' }}">Sign-ins</a>
</div>
