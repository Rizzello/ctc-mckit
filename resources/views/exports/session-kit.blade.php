<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MC Kit</title>
    <style>
        @page {
            margin: 28px 32px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        h1 {
            color: #1976D2;
            font-size: 20px;
            line-height: 1.25;
            margin: 16px 0 10px;
        }

        h2 {
            color: #1976D2;
            font-size: 11px;
            letter-spacing: .04em;
            margin: 14px 0 6px;
            text-transform: uppercase;
        }

        .header {
            background: #f3f7fb;
            border-bottom: 2px solid #1976D2;
            padding: 9px 11px 8px;
        }

        .brand {
            color: #1976D2;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .12em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .technical {
            display: table;
            table-layout: fixed;
            width: 100%;
        }

        .technical-item {
            display: table-cell;
            padding-right: 12px;
            vertical-align: top;
            width: 20%;
        }

        .technical-item:last-child {
            padding-right: 0;
        }

        .technical-value {
            font-size: 9px;
            line-height: 1.35;
        }

        .session {
            page-break-after: always;
        }

        .session:last-child {
            page-break-after: auto;
        }

        .label {
            color: #4b5563;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .text {
            white-space: pre-wrap;
        }

        .columns {
            display: table;
            table-layout: fixed;
            width: 100%;
        }

        .column {
            display: table-cell;
            padding-right: 12px;
            vertical-align: top;
            width: 50%;
        }

        .column:last-child {
            padding-right: 0;
        }

        .item {
            margin-bottom: 6px;
        }

        .box {
            border: 1px solid #dbe4ed;
            margin-bottom: 8px;
            padding: 8px 10px;
        }

        .speaker {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 7px;
            padding-bottom: 7px;
        }

        .speaker:last-child {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .muted {
            color: #6b7280;
        }

        ul {
            margin: 3px 0 0;
            padding-left: 15px;
        }

        li {
            margin-bottom: 3px;
        }

        .note {
            border-left: 2px solid #90caf9;
            margin-bottom: 6px;
            padding-left: 7px;
        }

        .footer {
            color: #6b7280;
            font-size: 8px;
            margin-top: 14px;
        }
    </style>
</head>
<body>
@php($displayTimezone = config('app.timezone', 'UTC'))
@forelse ($sessions as $session)
    <section class="session">
        <div class="header">
            <div class="brand">MC Kit · Come To Code</div>
            <div class="technical">
                <div class="technical-item">
                    <div class="label">When</div>
                    <div class="technical-value">
                        {{ $session->starts_at?->setTimezone($displayTimezone)->format('d/m/Y H:i') ?? 'To be confirmed' }}
                        @if ($session->ends_at)
                            – {{ $session->ends_at->setTimezone($displayTimezone)->format('H:i') }}
                        @endif
                    </div>
                </div>
                <div class="technical-item">
                    <div class="label">Where</div>
                    <div class="technical-value">{{ $session->room?->name ?? 'To be confirmed' }}</div>
                </div>
                <div class="technical-item">
                    <div class="label">Type</div>
                    <div class="technical-value">
                        {{ $session->is_service_session ? 'Service' : 'Regular' }}
                        @if ($session->is_plenum_session) · Plenum @endif
                        @if ($session->is_confirmed) · Confirmed @endif
                    </div>
                </div>
                <div class="technical-item">
                    <div class="label">MC</div>
                    <div class="technical-value">
                        {{ $session->mcs->pluck('name')->join(', ') ?: 'Not assigned' }}
                    </div>
                </div>
                <div class="technical-item">
                    <div class="label">Categories</div>
                    <div class="technical-value">
                        {{ collect($session->categories ?? [])->join(', ') ?: 'None' }}
                    </div>
                </div>
            </div>
        </div>

        <h1>{{ $session->title }}</h1>

        @if ($session->description)
            <h2>Session description</h2>
            <div class="box text">{{ $session->description }}</div>
        @endif

        <h2>Speakers</h2>
        <div class="box">
            @forelse ($session->speakers as $speaker)
                <div class="speaker">
                    <div><strong>{{ $speaker->name }}</strong>@if ($speaker->tagline) — {{ $speaker->tagline }}@endif</div>
                    @if ($speaker->bio)
                        <div class="muted text">{{ $speaker->bio }}</div>
                    @endif
                </div>
            @empty
                <div class="muted">No speakers</div>
            @endforelse
        </div>

        @if ($session->mc_description || $session->mc_script)
            <h2>MC preparation</h2>
            <div class="box">
                @if ($session->mc_description)
                    <div class="item">
                        <div class="label">Description</div>
                        <div class="text">{{ $session->mc_description }}</div>
                    </div>
                @endif
                @if ($session->mc_script)
                    <div class="item">
                        <div class="label">Script</div>
                        <div class="text">{{ $session->mc_script }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if ($session->notes->isNotEmpty())
            <h2>Notes</h2>
            <div class="box">
                @foreach ($session->notes as $note)
                    <div class="note">
                        <div class="text">{{ $note->body }}</div>
                        <div class="muted">{{ $note->author?->name ?? 'Unknown author' }} · {{ $note->created_at->setTimezone($displayTimezone)->format('d/m/Y H:i') }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="footer">MC Kit · Come To Code · Session {{ $session->id }}</p>
    </section>
@empty
    <h1>MC Kit</h1>
    <p>No active sessions are available.</p>
@endforelse
</body>
</html>
