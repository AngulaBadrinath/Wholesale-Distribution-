<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    protected Google2FA $engine;

    public function __construct(?Google2FA $engine = null)
    {
        $this->engine = $engine ?? new Google2FA();
    }

    /**
     * Generate a cryptographically secure 32-character base32 secret key.
     */
    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * Generate a local, self-contained SVG QR code for authenticator configuration.
     * ZERO external network requests or remote rendering services.
     */
    public function generateQrCodeSvg(User $user, string $secret): string
    {
        $company = (string) config('app.name', 'Wholesale Distribution');
        $otpauthUrl = $this->engine->getQRCodeUrl($company, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($otpauthUrl);
    }

    /**
     * Format secret into 4-character blocks for accessible manual entry.
     */
    public function formatSecretForManualEntry(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Verify a 6-digit TOTP code against the secret key.
     * Window of 1 allows +/- 30 seconds clock skew.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $cleanCode = preg_replace('/\s+/', '', $code);

        if (! is_string($cleanCode) || ! preg_match('/^\d{6}$/', $cleanCode)) {
            return false;
        }

        return (bool) $this->engine->verifyKey($secret, $cleanCode, 1);
    }

    /**
     * Generate 8 high-entropy recovery codes, hash them, and persist them.
     *
     * @return array<int, string> The plaintext recovery codes for one-time display.
     */
    public function generateRecoveryCodes(User $user): array
    {
        $plaintextCodes = [];
        $records = [];
        $key = (string) (config('app.key') ?: 'default-auth-fallback-key');

        for ($i = 0; $i < 8; $i++) {
            $code = sprintf('%s-%s', strtoupper(Str::random(5)), strtoupper(Str::random(5)));
            $plaintextCodes[] = $code;
            $records[] = [
                'user_id' => $user->id,
                'code_hash' => hash_hmac('sha256', $code, $key),
                'used_at' => null,
                'created_at' => now(),
            ];
        }

        DB::transaction(function () use ($user, $records) {
            DB::table('two_factor_recovery_codes')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('two_factor_recovery_codes')->insert($records);
        });

        return $plaintextCodes;
    }

    /**
     * Atomically verify and consume a single-use recovery code.
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        $normalizedCode = strtoupper(trim($code));
        $key = (string) (config('app.key') ?: 'default-auth-fallback-key');
        $codeHash = hash_hmac('sha256', $normalizedCode, $key);

        $affected = DB::table('two_factor_recovery_codes')
            ->where('user_id', $user->id)
            ->where('code_hash', $codeHash)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return $affected === 1;
    }

    /**
     * Get count of unconsumed recovery codes for a user.
     */
    public function getUnconsumedRecoveryCodesCount(User $user): int
    {
        return DB::table('two_factor_recovery_codes')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->count();
    }
}
