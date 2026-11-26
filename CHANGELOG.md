# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

First stable version. From here on, what is public does not break before 2.0: see
[Versioning and stability](https://leaf.ophelios.com/getting-started/versioning/).
Moving from 0.1.x: see [UPGRADE.md](UPGRADE.md).

### Changed

- A locale listed twice in `localization.supported_locales` is now refused at startup, after the
  codes are lowercased (`en` and `EN` are the same locale). The refusal reads:
  `Configuration section 'localization' field 'supported_locales' has invalid value "en": listed once; locale codes are compared in lower case, so "EN" and "en" are the same locale.`
  (with the offending code in place of `en`).
- The helper classes `Leaf\Content\SlugTitle`, `Leaf\Localization\LocaleCode`, `Leaf\LeafLatteExtension` and `Leaf\FileWatcher` are now `@internal` and are no longer part of the public API.

### Fixed

- A numeric front matter `title` (`title: 2024`) is now indexed as a string, and a non-scalar one falls back to the title taken from the slug, so the page no longer vanishes from search.
- The search index text extraction is now bounded, so unusual markup cannot stall or fail a build on a PHP without the PCRE JIT.

## 0.1.x

See the [GitHub Releases](https://github.com/zephyrus-framework/leaf-core/releases).
