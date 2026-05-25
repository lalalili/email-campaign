<?php

namespace Lalalili\EmailCampaign\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lalalili\AudienceCore\Models\AudienceListRow;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class SyncAudienceListToCampaignRecipientsAction
{
    public function execute(EmailCampaign $campaign): int
    {
        if (! $campaign->audience_list_id || ! $campaign->audience_email_column) {
            return 0;
        }

        $rows = AudienceListRow::query()
            ->where('audience_list_id', $campaign->audience_list_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $synced = 0;
        $skipped = 0;
        $seenEmails = [];

        DB::transaction(function () use ($campaign, $rows, &$synced, &$skipped, &$seenEmails): void {
            $campaign->recipients()->delete();

            foreach ($rows as $row) {
                $payload = $row->data_json ?? [];

                $email = trim((string) ($payload[$campaign->audience_email_column] ?? ''));
                $normalizedEmail = mb_strtolower($email);

                if (
                    $email === ''
                    || isset($seenEmails[$normalizedEmail])
                    || Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()
                ) {
                    $skipped++;

                    continue;
                }

                $seenEmails[$normalizedEmail] = true;

                EmailCampaignRecipient::create([
                    'email_campaign_id'    => $campaign->id,
                    'audience_list_row_id' => $row->id,
                    'email'                => $email,
                    'user_name'            => null,
                    'external_id'          => (string) $row->id,
                    'payload_json'         => $payload,
                ]);

                $synced++;
            }

            $campaign->update([
                'audience_snapshot_at'   => now(),
                'audience_skipped_count' => $skipped,
            ]);
        });

        return $synced;
    }
}
