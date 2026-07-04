<?php

namespace Lalalili\EmailCampaign\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lalalili\AudienceCore\Models\AudienceListRow;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class SyncAudienceListToCampaignRecipientsAction
{
    private const INSERT_CHUNK_SIZE = 500;

    public function execute(EmailCampaign $campaign): int
    {
        if (! $campaign->audience_list_id || ! $campaign->audience_email_column) {
            return 0;
        }

        // 已有寄送紀錄就不可重同步：recipients 全刪會沿著 cascadeOnDelete
        // 連帶抹除 email_deliveries 與 email_events 的歷史資料。
        if ($campaign->deliveries()->exists()) {
            return 0;
        }

        $synced = 0;
        $skipped = 0;
        $seenEmails = [];

        DB::transaction(function () use ($campaign, &$synced, &$skipped, &$seenEmails): void {
            $campaign->recipients()->delete();

            $now = now();

            AudienceListRow::query()
                ->where('audience_list_id', $campaign->audience_list_id)
                ->where('status', 'active')
                ->lazyById(self::INSERT_CHUNK_SIZE)
                ->chunk(self::INSERT_CHUNK_SIZE)
                ->each(function ($rows) use ($campaign, $now, &$synced, &$skipped, &$seenEmails): void {
                    $pending = [];

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

                        $pending[] = [
                            'email_campaign_id' => $campaign->id,
                            'audience_list_row_id' => $row->id,
                            'email' => $email,
                            'user_name' => null,
                            'external_id' => (string) $row->id,
                            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $synced++;
                    }

                    if ($pending !== []) {
                        EmailCampaignRecipient::query()->insert($pending);
                    }
                });

            $campaign->update([
                'audience_snapshot_at' => now(),
                'audience_skipped_count' => $skipped,
            ]);
        });

        return $synced;
    }
}
