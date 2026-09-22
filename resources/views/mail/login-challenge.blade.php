<!DOCTYPE html>
<html lang="en">
    <body style="margin:0;background:#f5f5f5;color:#212121;font-family:Roboto,Arial,sans-serif;line-height:1.5;">
        <main style="max-width:560px;margin:0 auto;padding:32px 20px;">
            <section style="background:#ffffff;border:1px solid #e0e0e0;border-radius:4px;padding:32px;">
                <p style="margin:0 0 8px;color:#1976d2;font-size:14px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">MC Kit</p>
                <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;">Sign in to MC Kit</h1>
                <p style="margin:0 0 24px;">Use the link below, or enter the code in the app.</p>
                <p style="margin:0 0 24px;"><a href="{{ $magicUrl }}" style="display:inline-block;background:#1976d2;border-radius:4px;color:#ffffff;font-weight:700;padding:12px 18px;text-decoration:none;">Sign in to MC Kit</a></p>
                <p style="margin:0 0 6px;color:#616161;font-size:14px;font-weight:700;">Your 6-digit code</p>
                <p style="margin:0 0 24px;background:#f5f5f5;border-radius:4px;color:#1976d2;font-size:28px;font-weight:700;letter-spacing:0.18em;padding:12px 16px;">{{ $otp }}</p>
                <p style="margin:0;color:#616161;font-size:14px;">This link and code expire at {{ $expiresAt->format('H:i') }}. If you did not request this sign-in message, you can safely ignore it.</p>
            </section>
        </main>
    </body>
</html>
