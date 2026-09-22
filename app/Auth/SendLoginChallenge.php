<?php

namespace App\Auth;

use App\Actions\IssueLoginChallenge;
use App\Mail\LoginChallengeMail;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendLoginChallenge
{
    public function __construct(private IssueLoginChallenge $issueLoginChallenge) {}

    public function handle(string $email, Session $session): void
    {
        $user = User::query()->where('email', $email)->where('enabled', true)->first();

        if (! $user instanceof User) {
            $session->forget('login_challenge_id');

            return;
        }

        $issuedChallenge = $this->issueLoginChallenge->handle($user);

        if ($issuedChallenge === null) {
            $session->forget('login_challenge_id');

            return;
        }

        $magicUrl = URL::temporarySignedRoute(
            'auth.magic',
            $issuedChallenge->expiresAt,
            ['challenge' => $issuedChallenge->challenge->id, 'token' => $issuedChallenge->magicToken],
        );

        try {
            Mail::to($user)->send(new LoginChallengeMail(
                magicUrl: $magicUrl,
                otp: $issuedChallenge->otp,
                expiresAt: $issuedChallenge->expiresAt,
            ));

            $session->put('login_challenge_id', $issuedChallenge->challenge->id);
        } catch (Throwable) {
            $issuedChallenge->challenge->forceFill(['consumed_at' => now()])->save();
            $session->forget('login_challenge_id');
        }
    }
}
