# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2025-12-08

### Changed
- PHP requirement lowered to 8.2+

## [1.0.0] - 2024-12-05

### Added
- Zero downtime deployment with atomic symlink switching
- Automatic rollback on deployment failure
- Health check validation after deployment
- Database backups before migrations (MySQL, PostgreSQL)
- Deployment notifications via webhooks (Slack, Discord)
- Deployment locks to prevent concurrent deployments
- Release management with configurable retention
- Custom deployment hooks (before/after clone, activate, rollback)
- GitHub Actions integration examples
- Multiple configuration methods (env vars, server config, project config)
- Custom shared paths across releases
- Multi-environment deployment support

### Quality
- 100% test coverage with Pest
- Laravel Pint for code formatting
- Rector for automated refactoring
- Strict types throughout codebase
- Comprehensive integration tests

### Documentation
- Complete README with examples
- Configuration reference guide
- GitHub Actions integration guide
- Troubleshooting guide
- Example workflows and configurations

### Infrastructure
- Built with Laravel Zero framework v12
- SSH connection management via phpseclib
- PHP 8.3+ requirement

[Unreleased]: https://github.com/veltix/zdt/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/veltix/zdt/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/veltix/zdt/releases/tag/v1.0.0
