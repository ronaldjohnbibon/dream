<?php

namespace App\Modules\Notifications;

use Closure;

class PushSubscriptionValidator
{
    public static function rules(): array
    {
        return [
            'endpoint' => ['bail', 'required', 'string', 'url:https', 'max:1024', function (string $attribute, mixed $value, Closure $fail): void {
                $url     = parse_url($value);
                $host    = strtolower($url['host'] ?? '');
                $allowed = collect(config('webpush.allowed_hosts', []))->contains(function (string $pattern) use ($host): bool {
                    return $host === $pattern || (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)));
                });

                if (! $allowed || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || ($url['port'] ?? 443) !== 443) {
                    $fail('Choose a supported browser push subscription.');
                }
            }],
            'keys'        => ['required', 'array'],
            'keys.p256dh' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $key = self::decode($value);
                // SubjectPublicKeyInfo for an uncompressed prime256v1 public key.
                $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$key;
                $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
                if (strlen($key) !== 65 || $key[0] !== "\x04" || ! @openssl_pkey_get_public($pem)) {
                    $fail('The browser push public key is invalid.');
                }
            }],
            'keys.auth' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen(self::decode($value)) !== 16) {
                    $fail('The browser push authentication secret is invalid.');
                }
            }],
        ];
    }

    private static function decode(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $value)) {
            return '';
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
