<?php

namespace Lalalili\EmailCampaign\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Lalalili\EmailCampaign\Actions\LogEmailEventAction;
use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Support\TrackingUrlSigner;

class EmailTrackingController extends Controller
{
    // 1×1 transparent GIF
    private const PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function open(Request $request, string $token, LogEmailEventAction $logger): Response
    {
        $delivery = EmailDelivery::where('tracking_token', $token)->first();

        if ($delivery) {
            $logger->execute($delivery, EmailEventType::Open, null, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response(self::PIXEL_GIF, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(Request $request, string $token, LogEmailEventAction $logger, TrackingUrlSigner $signer): RedirectResponse
    {
        $delivery = EmailDelivery::where('tracking_token', $token)->first();
        $destination = $request->query('u');
        $signature = $request->query('s');

        if (! is_string($destination) || ! $signer->isAllowedDestination($destination)) {
            return redirect('/');
        }

        if (! $signer->hasValidSignature($token, $destination, is_string($signature) ? $signature : null) && ! $signer->allowsUnsignedClicks()) {
            return redirect('/');
        }

        if ($delivery) {
            $logger->execute($delivery, EmailEventType::Click, $destination, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->away($destination);
    }

    /**
     * GET 僅顯示確認頁、不寫入抑制名單：郵件閘道（Gmail、防毒）會預抓連結，
     * 若 GET 直接退訂會造成大量誤退訂。實際退訂由 POST confirmUnsubscribe 執行。
     */
    public function unsubscribe(string $token): Response
    {
        return response()->view('email-campaign::tracking.unsubscribe-confirm', [
            'confirmUrl' => route('email-campaign.track.unsubscribe.confirm', $token),
        ], 200);
    }

    public function confirmUnsubscribe(string $token, LogEmailEventAction $logger): Response
    {
        $delivery = EmailDelivery::where('tracking_token', $token)->first();

        if ($delivery) {
            $logger->execute($delivery, EmailEventType::Unsubscribe);
        }

        return response()->view('email-campaign::tracking.unsubscribed', [], 200);
    }
}
