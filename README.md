# email-campaign

獨立的 EDM／電子報寄送引擎（Laravel）。提供活動、收件人、SMTP profile、排程、開信／點擊追蹤、
退訂與抑制名單，以及可擴充的變數提供者。無 Filament 依賴（後台 UI 見 `email-campaign-filament`）。

## 功能

- 活動與收件人：`EmailCampaign`、`EmailCampaignRecipient`，狀態機 `EmailCampaignStatus`
- 寄送：`SendCampaignAction` → `DispatchCampaignJob` → `SendCampaignEmailJob`（走佇列）
- 多 SMTP profile：`EmailSmtpProfile` + `MailerFactory` 動態切換寄件通道
- 追蹤與投遞：`EmailDelivery`、`EmailEvent`（開信／點擊）、簽章網址 `TrackingUrlSigner`
- 退訂與抑制：`EmailSuppression`，退訂端點與抑制名單流程
- 排程：`ScheduleDueCampaignsAction`（`scheduler_enabled` 控制，預設僅正式環境）
- 變數系統：`VariableProviderRegistry` + `SystemVariableProvider` / `RecipientVariableProvider`，
  可註冊自訂 provider（見 config `providers`）
- 交易信與測試信：`SendTransactionalEmailAction`、`SendTestCampaignEmailAction`
- 受眾整合：`SyncAudienceListToCampaignRecipientsAction` 串接 `lalalili/audience-core`
- 事件：`CampaignDispatched` / `CampaignEmailSent` / `CampaignEmailFailed` / `CampaignCompleted`

## 安裝

```bash
composer require lalalili/email-campaign
php artisan vendor:publish --tag=email-campaign-config
php artisan migrate
```

## 設定重點（`config/email-campaign.php`）

- `demo_safe_mode`：正式環境預設 `true`，只記錄投遞而不實際寄出，避免示範面板誤發
- `tracking.signing_key`：追蹤網址簽章金鑰；`allow_unsigned_clicks` 於正式環境強制忽略，避免 open redirect
- `route_middleware`：追蹤／退訂為未驗證端點，務必保留 `throttle`
- `queue`：`SendCampaignEmailJob` 的佇列連線與名稱

## 基本用法

```php
use Lalalili\EmailCampaign\Actions\SendCampaignAction;

app(SendCampaignAction::class)->execute($campaign);
```
