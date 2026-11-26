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

## Tailwind CSS

The theme no longer loads Tailwind CSS from its CDN. It ships the classes its own templates and scripts use,
compiled in `public/assets/css/tailwind.css`, and pages look the same.

- **Binary tier**: the CLI embeds the new theme. A template you override keeps working, and so does a
  `templates/partials/head.latte` that still loads the CDN. One case breaks: a template or a Markdown page of your
  site (an override, a custom page or content) that uses a Tailwind class the theme itself never uses loses that
  style. Load Tailwind from your
  `templates/partials/head.latte` with the snippet in the theme's
  [Styling](https://github.com/zephyrus-framework/leaf/blob/v1.0.0/README.md#styling) section.
- **Composer tier**: your project has its own copy of the theme and keeps loading the CDN until you take over
  the compiled stylesheet, as the theme's
  [changelog](https://github.com/zephyrus-framework/leaf/blob/v1.0.0/CHANGELOG.md) describes. `composer build`
  then compiles the classes of your templates and also those of the Markdown pages in `content/` (the theme's
  `tailwind.config.js` now reads the `class` attributes of `content/**/*.md`: copy that file rather than adding the
  path by hand, which would also compile the words of your prose).

## Theme overrides

A template, stylesheet or script you copied from the theme belongs to your site. The theme's
[CHANGELOG.md](https://github.com/zephyrus-framework/leaf/blob/v1.0.0/CHANGELOG.md) names each file
that changed and what an override has to take over.
