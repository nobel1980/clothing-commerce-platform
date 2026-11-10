<?php

namespace App\Shared\Support;

final class SensitiveDataRedactor
{
    public const REDACTED_VALUE = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'store_passwd',
        'store_password',
        'app_secret',
        'app_key',
        'x-app-key',
        'api_key',
        'api-key',
        'secret_key',
        'secret-key',
        'client_secret',
        'api-access-token',
        'authorization',
        'id_token',
        'refresh_token',
        'access_token',
        'token',
        'private_key',
        'public_key',
        'webhook_secret',
        'secret',
        'signature',
        'sensitivedata',
        'sensitive_data',
        'card_number',
        'card_no',
        'cvv',
        'cvc',
        'pin',
        'otp',
    ];

    /**
     * Recursively redact sensitive credentials, tokens, signatures, and secrets from arrays.
     */
    public static function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED_VALUE;
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redact($value);
                continue;
            }

            if (is_string($value) && self::looksLikeBearerOrSecret($value)) {
                $redacted[$key] = self::REDACTED_VALUE;
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['secret', 'password', 'passwd', 'private_key', 'token', 'signature'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function looksLikeBearerOrSecret(string $value): bool
    {
        $trimmed = trim($value);
        return str_starts_with(strtolower($trimmed), 'bearer ')
            || str_starts_with($trimmed, '-----BEGIN ');
    }
}

