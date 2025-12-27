<?php

namespace backend\components;

use common\models\User;
use yii\base\InvalidConfigException;

class JwtService
{
    private string $secret;
    private int $ttlSeconds;

    public function __construct(?string $secret, ?int $ttlSeconds = null)
    {
        if (empty($secret)) {
            throw new InvalidConfigException('JWT secret is not configured.');
        }

        $this->secret = $secret;
        $this->ttlSeconds = $ttlSeconds ?? 60 * 60 * 24 * 30;
    }

    public function issueToken(User $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ];

        return $this->encode($payload);
    }

    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;

        $header = $this->decodeSegment($headerSegment);
        if (!$header || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $payload = $this->decodeSegment($payloadSegment);
        if (!$payload) {
            return null;
        }

        $expectedSignature = $this->sign($headerSegment . '.' . $payloadSegment);
        if (!$this->hashEquals($expectedSignature, $signatureSegment)) {
            return null;
        }

        if (isset($payload['exp']) && time() >= (int)$payload['exp']) {
            return null;
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerSegment = $this->encodeSegment($header);
        $payloadSegment = $this->encodeSegment($payload);
        $signatureSegment = $this->sign($headerSegment . '.' . $payloadSegment);

        return $headerSegment . '.' . $payloadSegment . '.' . $signatureSegment;
    }

    private function sign(string $data): string
    {
        $signature = hash_hmac('sha256', $data, $this->secret, true);

        return $this->base64UrlEncode($signature);
    }

    private function encodeSegment(array $data): string
    {
        return $this->base64UrlEncode(json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    private function decodeSegment(string $segment): ?array
    {
        $decoded = $this->base64UrlDecode($segment);
        if ($decoded === null) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function hashEquals(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }
}
