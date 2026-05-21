<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Events\CampaignDispatched;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class SendCampaignAction
{
    public function __construct(private readonly SyncAudienceListToCampaignRecipientsAction $syncAudienceList) {}

    public function execute(EmailCampaign $campaign): void
    {
        $this->syncAudienceList->execute($campaign);

        try {
            $recipients = $campaign->recipients()->get();

            if ($recipients->isEmpty()) {
                $campaign->update(['status' => EmailCampaignStatus::Failed]);

                return;
            }

            $recipients->each(function ($recipient) use ($campaign) {
                SendCampaignEmailJob::dispatch($campaign, $recipient);
            });

            $campaign->update(['status' => EmailCampaignStatus::Sending]);

            CampaignDispatched::dispatch($campaign);
        } catch (\Throwable $e) {
            $campaign->update(['status' => EmailCampaignStatus::Failed]);

            throw $e;
        }
    }
}
