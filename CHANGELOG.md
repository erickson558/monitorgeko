# Changelog

All notable changes to this project are documented in this file.

The format is inspired by Keep a Changelog and this project follows Semantic Versioning.

## [v1.0.6] - 2026-03-06

### Changed
- Synchronized metric bar animation cadence with dashboard polling speed.
- Bar transition time is now dynamic and derived from `uiPollMs`, reducing the lag versus task-style charts.

### Fixed
- Improved perceived real-time behavior for card bars under fast refresh intervals.

## [v1.0.5] - 2026-03-06

### Changed
- Improved dashboard sampling responsiveness with adaptive pull batching in frontend (`assets/js/app.js`).
- Backend `api/state.php` now supports multi-device pull per request (`max_pull` up to 12) instead of forcing one device only.
- Added a safe pull time budget per request to keep UI updates fast while increasing sampling throughput.

### Fixed
- Reduced card refresh lag in environments with multiple `pull_http` / `pull_ssh` devices.

## [v1.0.4] - 2026-03-06

### Fixed
- Refined Windows CPU measurement for `pull_ssh` to better match real-time load.
- CPU collection now prioritizes `Win32_PerfFormattedData_PerfOS_Processor(_Total)`, then sampled `Get-Counter`, and finally `Win32_Processor.LoadPercentage` fallback.
- Added numeric guardrails (`0-100`) to avoid invalid CPU outputs.

### Changed
- Aligned `agents/windows-agent.ps1` with the same improved CPU strategy for consistency across `push` and `pull_ssh` flows.

## [v1.0.3] - 2026-03-06

### Added
- GitHub Actions workflow `.github/workflows/release-on-main.yml` to create a release on every push to `main`.
- Automatic validation of `VERSION` format (`vX.Y.Z`) in CI before tagging/releasing.

### Changed
- Release process now reads version directly from `VERSION`, creates tag if missing, and publishes GitHub Release with changelog notes.

## [v1.0.2] - 2026-03-06

### Fixed
- Resolved `pull_ssh` failures on Windows with `plink` reporting `The command line is too long`.
- Updated SSH Windows command builder to prefer the shortest PowerShell transport (`-Command` vs `-EncodedCommand`) to stay under line-length limits.

### Changed
- Release version updated to `v1.0.2` and propagated through app/runtime version source (`VERSION`).

## [v1.0.1] - 2026-03-06

### Added
- Centralized app version source in `VERSION` and exposed it in UI and API (`app_version` in `api/state.php`).
- Release documentation artifacts: `CHANGELOG.md`, `DEPENDENCIES.md`, and Apache 2.0 `LICENSE`.

### Fixed
- Hardened Windows CPU collection in SSH pull mode with fallback from `Get-Counter` to `Win32_Processor.LoadPercentage`.
- Hardened `agents/windows-agent.ps1` CPU collection with the same fallback strategy.
- Improved network metric capture for Linux and Windows to avoid persistent zero values during active traffic.
- Fixed an HTTP 500 regression caused by command-string escaping in `api/bootstrap.php`.

### Changed
- README expanded with setup, architecture, dependencies, security notes, and strict versioning workflow.
