<?php

namespace Lalalili\EmailCampaign\Support;

class TrackingUrlSigner
{
    public function sign(string $trackingToken, string $url): string
    {
        return hash_hmac('sha256', $this->payload($trackingToken, $url), $this->signingKey());
    }

    public function hasValidSignature(string $trackingToken, string $url, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($trackingToken, $url), $signature);
    }

    /**
     * 未簽章的 click 轉址等同 open redirect，生產環境一律拒絕，不受 config 開關影響。
     */
    public function allowsUnsignedClicks(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        return (bool) config('email-campaign.tracking.allow_unsigned_clicks', false);
    }

    public function isAllowedDestination(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function payload(string $trackingToken, string $url): string
    {
        return $trackingToken.'|'.$url;
    }

    private function signingKey(): string
    {
        $configuredKey = config('email-campaign.tracking.signing_key');

        if (is_string($configuredKey) && $configuredKey !== '') {
            return $configuredKey;
        }

        return (string) config('app.key');
    }
}
