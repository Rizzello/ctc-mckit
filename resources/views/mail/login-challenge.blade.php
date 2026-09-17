<!DOCTYPE html>
<html lang="en">
    <body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;line-height:1.5;">
        <main style="max-width:560px;margin:0 auto;padding:32px 20px;">
            <section style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;">
                <p style="margin:0 0 8px;color:#0369a1;font-size:14px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">MC Kit</p>
                <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;">Sign in to MC Kit</h1>
                <p style="margin:0 0 24px;">Use the link below, or enter the code in the app.</p>
                <p style="margin:0 0 24px;"><a href="{{ $magicUrl }}" style="display:inline-block;background:#075985;border-radius:6px;color:#ffffff;font-weight:700;padding:12px 18px;text-decoration:none;">Sign in to MC Kit</a></p>
                <p style="margin:0 0 6px;color:#475569;font-size:14px;font-weight:700;">Your 6-digit code</p>
                <p style="margin:0 0 24px;font-size:28px;font-weight:700;letter-spacing:0.18em;">{{ $otp }}</p>
                <p style="margin:0;color:#475569;font-size:14px;">This link and code expire at {{ $expiresAt->format('H:i') }}. If you did not request this sign-in message, you can safely ignore it.</p>
            </section>
        </main>
    </body>
</html>
