<?php

namespace App\Services;

final class StudioTotp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $raw = random_bytes(20);
        $bits = '';
        foreach (str_split($raw) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $encoded .= self::ALPHABET[bindec($chunk)];
        }
        return $encoded;
    }

    private function decode(string $secret): string
    {
        if (!preg_match('/^[A-Z2-7]{32}$/D', $secret)) {
            throw new \InvalidArgumentException('Invalid TOTP secret');
        }
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }

    public function code(string $secret, int $counter): string
    {
        if ($counter < 0) {
            throw new \InvalidArgumentException('Invalid TOTP counter');
        }
        $message = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $digest = hash_hmac('sha1', $message, $this->decode($secret), true);
        $offset = ord($digest[19]) & 15;
        $number = (unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff) % 1000000;
        return str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /** Returns matched time step, or null. Last accepted step prevents TOTP replay. */
    public function match(string $secret, string $code, ?int $lastCounter = null): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }
        $current = intdiv(now()->getTimestamp(), 30);
        foreach ([$current - 1, $current, $current + 1] as $candidate) {
            if ($candidate >= 0 && ($lastCounter === null || $candidate > $lastCounter) &&
                hash_equals($this->code($secret, $candidate), $code)) {
                return $candidate;
            }
        }
        return null;
    }

    public function recoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(12)));
            $codes[] = implode('-', str_split($raw, 6));
        }
        return $codes;
    }
}
