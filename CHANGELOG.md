# Changelog

All notable changes to `lalalili/email-campaign` will be documented in this file.

## [1.0.1] - 2026-07-27

### Fixed

- `php` 約束由 `^8.2` 更正為 `^8.4`。相依鏈上的
  `spatie/laravel-activitylog ^5.0` 硬性要求 php `^8.4`,原本的宣告與
  現實不符,在 8.2/8.3 上根本無法安裝。
- `phpstan.neon.dist` 的 `tmpDir` 由 `../../storage/...` 改為套件內的
  `build/phpstan`,不再假設套件位於宿主 `packages/` 底下。

### Added

- 掛上 `lalalili/.github` 的共用 CI 與 Release workflow。此套件先前因為
  相依私有 repo 而無法在 CI 解析依賴,長期沒有自動化測試。

## [1.0.0] - 2026-07-27

### Changed

- 首個穩定版。此後遵循
  [SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)
  定義的 public API 契約,宿主可安全使用 `^1.0` 約束。
- 對其他 lalalili 套件的約束一律收斂為 `^1.0`,取代先前 `^0.x`
  與多段 OR 的寫法。
- `repositories` 改用 GitHub VCS,不再依賴宿主 `packages/` 底下的
  兄弟目錄;測試資源改從 `vendor/lalalili/*` 讀取。
- 移除 `minimum-stability` / `prefer-stable` 宣告,授權統一為 MIT。

### 為什麼是 1.0.0

Composer 對 `^0.1.1` 的解讀是 `>=0.1.1 <0.2.0`,0.x 期間每發一個 minor
都需要所有宿主手動改 `composer.json`,否則 `composer update` 永遠拿不到
新版。本套件生態曾因此讓宿主停在數十個 commit 之前而無人察覺。

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
