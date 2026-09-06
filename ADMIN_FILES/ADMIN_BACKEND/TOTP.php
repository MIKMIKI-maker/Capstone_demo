<?php
/**
 * Minimal, dependency-free TOTP (RFC 6238) implementation for Admin 2FA.
 * No Composer package manager is available in the Docker image (see
 * MAILER/PHPMailer/, which is vendored the same way), so this is written
 * directly against the RFC rather than pulling in a library.
 */
class TOTP {
    const PERIOD = 30;
    const DIGITS = 6;
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh random secret, base32-encoded (the format authenticator apps expect). */
    public static function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    /** The otpauth:// URI to encode into a QR code for the authenticator app to scan. */
    public static function getOtpAuthUri(string $secret, string $accountName, string $issuer = 'SPED ALM'): string {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($accountName)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function getCode(string $base32Secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, self::PERIOD);
        $secret = self::base32Decode($base32Secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $binCounter, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $code = $truncated % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts a code from one period before/after "now" too — phone and
     * server clocks are never perfectly in sync, and without this window a
     * code typed a second after it displayed can wrongly fail.
     */
    public static function verifyCode(string $base32Secret, string $code, int $window = 1): bool {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) return false;
        $timestamp = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::getCode($base32Secret, $timestamp + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }
        return false;
    }

    /** 8 human-typeable one-time backup codes, for when the phone/app is unavailable. */
    public static function generateBackupCodes(int $count = 8): array {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(4))); // e.g. "a1b2c3d4"
        }
        return $codes;
    }

    private static function base32Encode(string $data): string {
        $binaryString = '';
        foreach (str_split($data) as $char) {
            $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($binaryString, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::ALPHABET[bindec($chunk)];
        }
        return $encoded;
    }

    private static function base32Decode(string $b32): string {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
        $binaryString = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) continue;
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binaryString, 8) as $byte) {
            if (strlen($byte) < 8) continue;
            $bytes .= chr(bindec($byte));
        }
        return $bytes;
    }
}
