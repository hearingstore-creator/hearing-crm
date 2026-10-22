<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your feedback – {{ $setting->company_name ?? 'Hearing Care' }}</title>
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #2563eb; --ok: #059669; --bg: #f8fafc; --card: #fff; }
        @media (prefers-color-scheme: dark) { :root { --ink: #e2e8f0; --muted: #94a3b8; --line: #334155; --accent: #60a5fa; --ok: #34d399; --bg: #0f172a; --card: #1e293b; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 28px 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 22px; }
        h1 { font-size: 19px; margin: 0 0 6px; }
        .muted { color: var(--muted); font-size: 13px; }
        .scale { display: grid; grid-template-columns: repeat(11, 1fr); gap: 4px; margin: 16px 0 6px; }
        .scale label { position: relative; }
        .scale input { position: absolute; opacity: 0; }
        .scale span { display: block; text-align: center; padding: 10px 0; border: 1px solid var(--line); border-radius: 8px; font-weight: 600; cursor: pointer; }
        .scale input:checked + span { background: var(--accent); color: #fff; border-color: var(--accent); }
        .scale input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
        .ends { display: flex; justify-content: space-between; }
        textarea { width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--ink); font: inherit; margin-top: 12px; }
        button { margin-top: 12px; border: 0; border-radius: 10px; padding: 11px 20px; background: var(--ok); color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; }
        .msg { padding: 12px 14px; border-radius: 10px; background: rgba(5, 150, 105, .12); color: var(--ok); margin-bottom: 14px; }
        .err { background: rgba(220, 38, 38, .12); color: #dc2626; }
        @media (max-width: 420px) { .scale { grid-template-columns: repeat(6, 1fr); } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted">{{ $setting->company_name ?? '' }}</div>
        @if (session('done') || $survey->answered_at)
            <div class="msg" role="status">{{ session('done') ?? 'Thank you – your answer was already saved.' }}</div>
            <p class="muted">If anything is not right with your hearing aid, call us at {{ $setting->phone ?? 'the centre' }}.</p>
        @else
            <h1>How likely are you to recommend us to a friend or family member?</h1>
            @if ($errors->any())<div class="msg err" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ url('s/' . $survey->token) }}">
                @csrf
                <div class="scale" role="radiogroup" aria-label="Score from 0 to 10">
                    @for ($i = 0; $i <= 10; $i++)
                        <label><input type="radio" name="score" value="{{ $i }}" @checked(old('score') === (string) $i)><span>{{ $i }}</span></label>
                    @endfor
                </div>
                <div class="ends muted"><span>Not at all</span><span>Definitely</span></div>
                <textarea name="comment" rows="3" maxlength="1000" placeholder="Anything we could do better? (optional)">{{ old('comment') }}</textarea>
                <button type="submit">Send</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
