# Upgrading from 0.1.x to 1.0

Leaf 1.0.0 is the first stable version of `zephyrus-framework/leaf-core`, the theme
`zephyrus-framework/leaf` and the CLI `zephyrus-framework/leaf-cli`, released under the same number.
See the [changelog](CHANGELOG.md) for the full list and
[Versioning and stability](https://leaf.ophelios.com/getting-started/versioning/) for what is public.

## Composer tier

Composer does not move a `^0.1.x` constraint to 1.0 by itself. Change the constraint of
`zephyrus-framework/leaf-core` to `^1.0`:

```bash
composer require zephyrus-framework/leaf-core:^1.0
```

A project created with `composer create-project zephyrus-framework/leaf` or with `leaf eject`
requires `"@dev"` and sets `"minimum-stability": "dev"`. In its `composer.json`, require `"^1.0"` for
`zephyrus-framework/leaf-core` and remove `minimum-stability` (stable is the default), then run
`composer update zephyrus-framework/leaf-core`.

## Binary tier

Pin the new version, and replace the checksum together with it:

- Set `LEAF_VERSION=v1.0.0` in your workflow, or when running `install.sh`.
- Take the `leaf-linux-amd64` line of the `checksums.txt` published with the v1.0.0 release and use it
  as the expected SHA-256.

## Behaviour changes since 0.1.12

- A locale listed twice in `localization.supported_locales` is refused at startup, once the codes are
  lowercased (`en` and `EN` count as one). Remove the duplicate. The message is quoted in the
  [changelog](CHANGELOG.md).

## Theme overrides

A template, stylesheet or script you copied from the theme belongs to your site. The theme's
[CHANGELOG.md](https://github.com/zephyrus-framework/leaf/blob/v1.0.0/CHANGELOG.md) names each file
that changed and what an override has to take over.
