# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-05-26

### Added
- Customer authentication state is now part of the ESI URL via the `esi_auth`
  parameter, so Varnish caches logged-in and logged-out ESI responses in
  separate entries.
- `RestoreEsiContextPlugin` reads `esi_auth` from the request and adds a
  matching layout cache key, keeping Magento's internal layout cache aligned
  with the URL-level segmentation.
- `Magento_Customer` added as a module sequence and composer dependency.

## [1.0.2] - Previous release

- See git history.

## [1.0.1] - Previous release

- See git history.

## [1.0.0] - Initial release

- Initial release.

[1.1.0]: https://github.com/hryvinskyi/magento2-esi-page-layout/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/hryvinskyi/magento2-esi-page-layout/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/hryvinskyi/magento2-esi-page-layout/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/hryvinskyi/magento2-esi-page-layout/releases/tag/1.0.0
