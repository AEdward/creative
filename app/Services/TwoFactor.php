<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/** Authenticator-app (TOTP) two-step sign-in for staff. */
class TwoFactor
{
    public const SESSION_KEY = 'two_factor_passed';

    public function __construct(private Google2FA $engine = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /** QR code (data URI) the authenticator app scans. */
    public function qrCode(User $user, string $secret): string
    {
        return $this->engine->getQRCodeInline('Creative Lab Academy', $user->email, $secret, 220);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        return strlen($code) === 6 && $this->engine->verifyKey($secret, $code, 1);
    }

    /** @return list<string> */
    public function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** @return list<string> the recovery codes to show the user once */
    public function enable(User $user, string $secret): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes, 'two_factor_confirmed_at' => now()])->save();
        AuditLog::create(['actor_id' => $user->id, 'action' => 'two_factor.enabled', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => []]);

        return $codes;
    }

    /** A code from the app, or a one-time recovery code (which is then used up). */
    public function check(User $user, string $input): bool
    {
        $input = trim($input);
        if ($user->two_factor_secret && $this->verify($user->two_factor_secret, $input)) {
            return true;
        }
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(Str::lower($input), $codes, true);
        if ($index === false) {
            return false;
        }
        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
        AuditLog::create(['actor_id' => $user->id, 'action' => 'two_factor.recovery_code_used', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => ['remaining' => count($codes)]]);

        return true;
    }

    /** Super admin resets a lost phone: the person sets two-step sign-in up again at their next sign-in. */
    public function reset(User $actor, User $user): void
    {
        abort_unless($actor->staffCan('super'), 403);
        abort_if($user->protectedFrom($actor), 403, 'This account is protected.');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::create(['actor_id' => $actor->id, 'action' => 'two_factor.reset', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => []]);
    }
}
