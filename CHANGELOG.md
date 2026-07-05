# Changelog

All notable changes to `lalalili/email-campaign` will be documented in this file.

## v0.2.0 - 2026-07-05

### Added
- 強化開信／點擊追蹤端點與退訂流程，並補上投遞（delivery）索引
- 擴充郵件活動寄送與追蹤能力

### Changed
- email campaign 排程預設只在正式環境啟用（`scheduler_enabled`）
- 支援缺少短連結（short-url）選用依賴時的降級

### Fixed
- 補強問卷邀請派送事件處理

## v0.1.1

- EDM 寄送引擎初版：活動、收件人、SMTP profile、排程、變數提供者
