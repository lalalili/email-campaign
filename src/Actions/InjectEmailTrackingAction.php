<?php

namespace Lalalili\EmailCampaign\Actions;

use DOMDocument;
use DOMElement;
use Lalalili\EmailCampaign\Models\EmailSuppression;

class InjectEmailTrackingAction
{
    public function execute(string $html, string $trackingToken, string $recipientEmail): string
    {
        if (EmailSuppression::isSuppressed($recipientEmail)) {
            return $html;
        }

        $openPixelUrl = route('email-campaign.track.open', $trackingToken);
        $unsubscribeUrl = route('email-campaign.track.unsubscribe', $trackingToken);

        $html = $this->wrapLinks($html, $trackingToken);
        $html = $this->injectPixelAndFooter($html, $openPixelUrl, $unsubscribeUrl);

        return $html;
    }

    private function wrapLinks(string $html, string $token): string
    {
        // Use regex for robustness — DOMDocument may mangle non-UTF8 or incomplete HTML
        return preg_replace_callback(
            '/<a\s([^>]*?)href=["\']([^"\']+)["\']/i',
            function (array $matches) use ($token): string {
                $attrs = $matches[1];
                $originalUrl = $matches[2];

                // Skip anchors, mailto, tel, and already-wrapped tracking links
                if (
                    str_starts_with($originalUrl, '#')
                    || str_starts_with($originalUrl, 'mailto:')
                    || str_starts_with($originalUrl, 'tel:')
                    || str_contains($originalUrl, '/email/track/')
                ) {
                    return $matches[0];
                }

                $clickUrl = route('email-campaign.track.click', $token).'?u='.urlencode($originalUrl);

                return "<a {$attrs}href=\"{$clickUrl}\"";
            },
            $html,
        ) ?? $html;
    }

    private function injectPixelAndFooter(string $html, string $pixelUrl, string $unsubscribeUrl): string
    {
        $pixel = "<img src=\"{$pixelUrl}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:block;width:1px;height:1px;\" />";
        $footer = "\n<div style=\"text-align:center;font-size:12px;color:#999;padding:16px 0;\">"
            ."<a href=\"{$unsubscribeUrl}\" style=\"color:#999;\">取消訂閱</a>"
            ."</div>";

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $pixel.$footer.'</body>', $html);
        }

        return $html.$pixel.$footer;
    }
}
