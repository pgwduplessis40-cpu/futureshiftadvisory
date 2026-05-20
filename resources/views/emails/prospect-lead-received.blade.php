<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New enquiry</title>
</head>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color:#1C2B45; background:#F9F6F0; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border:1px solid #E0D8CC; border-radius:8px; overflow:hidden;">
        <div style="background:#1C2B45; color:#F9F6F0; padding:16px 20px;">
            <div style="font-size:11px; letter-spacing:0.15em; text-transform:uppercase; color:#D4A020;">Future Shift Advisory</div>
            <div style="font-family: 'DM Serif Display', Georgia, serif; font-size:20px; margin-top:4px;">New enquiry from the website</div>
        </div>
        <div style="padding:20px;">
            <p style="margin:0 0 12px;"><strong>{{ $lead->name }}</strong>
                @if ($lead->company)
                    — {{ $lead->company }}
                @endif
            </p>
            <p style="margin:0 0 8px;">Email: <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></p>
            @if ($lead->phone)
                <p style="margin:0 0 8px;">Phone: {{ $lead->phone }}</p>
            @endif
            @if ($lead->engagement_interest)
                <p style="margin:0 0 8px;">Interest: {{ $lead->engagement_interest }}</p>
            @endif

            <hr style="border:none; border-top:1px solid #E0D8CC; margin:16px 0;">

            <p style="white-space:pre-wrap; margin:0;">{{ $lead->message }}</p>

            <hr style="border:none; border-top:1px solid #E0D8CC; margin:16px 0;">

            <p style="font-size:12px; color:#5A6A7A; margin:0;">
                Received {{ $lead->created_at?->format('d M Y H:i') }} ·
                IP {{ $lead->ip_address ?? 'unknown' }} ·
                Lead #{{ $lead->id }}
            </p>
        </div>
    </div>
</body>
</html>
