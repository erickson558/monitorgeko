# Changelog

All notable changes to this project are documented in this file.

The format is inspired by Keep a Changelog and this project follows Semantic Versioning.

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
