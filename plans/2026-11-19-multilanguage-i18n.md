# Bundled translations and RTL support

- **Date:** 2026-11-19
- **Scope:** `wordpressiccllmfiles` (ported from `wordpressiccsupercache`)

## Context

Every user-facing string was already wrapped with `__( '…', 'icc-gg-llm-files-generator' )`,
`esc_html__()` or `esc_html_e()`, and the plugin header declared the `Text Domain`. Nothing else
existed:

* no `Domain Path` header, so WordPress never looked for a local catalog;
* no `load_plugin_textdomain()` call, so a bundled catalog would never be loaded anyway;
* no `languages/` folder — the plugin had zero translations;
* no locale mapping, so documented codes such as `pt-BR` or `ar-SA` could not resolve to the
  catalogs WordPress actually looks for (`pt_BR`, `ar`);
* no RTL handling: `is_rtl()` follows the **site** locale, so an Arabic-speaking user whose profile
  language is Arabic on an English site still got an LTR admin.

`wordpressiccsupercache` already solved all of the above by shipping its own catalogs for
`en-US`, `pt-BR`, `es-MX`, `fr-FR`, `zh-CN`, `hi-IN` and `ar-SA`, plus RTL filters. This plan ports
that implementation unchanged in behaviour.

## Goal

1. Load the bundled catalogs explicitly, because WordPress only auto-loads language packs from
   `wp-content/languages/plugins`.
2. Map the documented locale codes to the locales WordPress reports, and map every Arabic variant to
   the single `ar` catalog.
3. Keep `en_US` as the fallback language: it ships an identity catalog, and any locale without a
   catalog falls back to the source (English) strings.
4. Ship `.pot` plus `.po`/`.mo` catalogs for `en_US`, `pt_BR`, `es_MX`, `fr_FR`, `zh_CN`, `hi_IN` and
   `ar`.
5. Render right-to-left correctly for `ar-SA`: `dir="rtl"` on `<html>`, the `rtl` admin body class
   and an RTL stylesheet for the settings page.
6. Document the workflow (extraction, compilation, manual verification) and validate it with
   `msgfmt --check`.

## Steps

1. **Bootstrap** — `icc-gg-llm-files-generator.php`
   - add `Domain Path:       /languages` to the plugin header;
   - add `icc_gg_llm_files_generator_load_textdomain()` on `plugins_loaded` priority `5`, using
     `dirname( ICC_GG_LLM_FILES_GENERATOR_BASENAME ) . '/languages'` so a git clone
     (`wordpressiccllmfiles/`) and a release install (`icc-gg-llm-files-generator/`) both work;
   - add `icc_gg_llm_files_generator_plugin_locale()` on `plugin_locale`: normalise `-` to `_`, accept
     the supported locales, map `en_*` to `en_US` and `ar*` to `ar`;
   - add `icc_gg_llm_files_generator_is_rtl_locale()` based on `determine_locale()`, plus the
     `language_attributes` and `admin_body_class` filters.
2. **Styles** — add `css/styles-admin-rtl.css` and enqueue it right after the admin stylesheet when
   the active locale is RTL.
3. **Catalogs** — `languages/`
   - `icc-gg-llm-files-generator.pot` generated with `xgettext`;
   - `<domain>-en_US.po/.mo` (identity, fallback language);
   - `<domain>-pt_BR.po/.mo`, `-es_MX`, `-fr_FR`, `-zh_CN`, `-hi_IN`, `-ar`.
4. **Tooling** — `tests/build-translations.sh` compiles every `.po` to `.mo` with
   `msgfmt --check --statistics`, so the binary catalogs can be regenerated and validated in one
   command.
5. **Documentation** — `docs/i18n.md` (implementation + workflow), `docs/testing.md` (static and
   manual checks), `README.md` (feature bullet + Translations section).
6. **Verification** — `php -l` on every touched file, `msgfmt --check` on every catalog,
   `tests/check-translations.php` (parses each `.mo` without WordPress),
   `tests/check-i18n-bootstrap.php` (stubs the loading path and asserts the textdomain, the
   `plugin_locale` mapping and the RTL filters) and `sh tests/validate-plugin-layout.sh`.

## Risks

| Risk | Mitigation |
|---|---|
| Translated AI prompt strings could break the diff contract | `insert`/`remove` keys, file names, URLs and every `%s`/`%1$s` placeholder are preserved verbatim in all catalogs; `msgfmt --check` rejects mismatched format specifiers |
| `plugin_locale` is a global filter | It returns early for any domain other than `icc-gg-llm-files-generator` |
| Forcing every `ar_*` variant onto one catalog hides regional differences | Arabic variants share the same UI vocabulary here, and RTL detection is independent of the catalog used |
| `determine_locale()` is not available before WordPress 4.7 | `icc_gg_llm_files_generator_is_rtl_locale()` falls back to `get_locale()` |
| Machine translations may read poorly | The catalogs are plain `.po` files; native speakers can correct them without touching code. `msgfmt --check` guarantees structural validity, not style |

## Result

Implemented as planned. 57 source strings, 7 locales (`en_US` identity plus six translations), 14
catalog files (`.po` + `.mo`) and the template. `sh tests/build-translations.sh --check-pot` is green,
`tests/check-translations.php` reports 57 entries per catalog with matching placeholders, and
`tests/check-i18n-bootstrap.php` confirms the loader path, the locale mapping and the RTL filters.
The release ZIP picks `languages/` up automatically, because `.github/workflows/build.yml` archives
the working tree.
