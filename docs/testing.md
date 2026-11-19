# Testing

## Static checks

Every PHP file must lint cleanly:

```bash
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;
```

The packaged tree must not look like several plugins. WordPress reads every `*.php` file in the plugin
root **and one directory below it** as a potential plugin, so a second `Plugin Name:` header would be
offered for activation ("The plugin does not have a valid header"). The guard also protects the
package from the layout mistake that breaks updates:

```bash
sh tests/validate-plugin-layout.sh
```

## Translation catalogs

Every catalog in `languages/` must be fully translated and structurally valid:

```bash
sh tests/build-translations.sh
```

The script compiles each `.po` into `.mo` with `msgfmt --check --check-format`, rejects catalogs
that report `fuzzy`, `untranslated` or `obsolete` entries, and fails when a catalog is missing a
string that the template has. Add `--check-pot` to also re-extract the strings with `xgettext` and
fail when `languages/icc-gg-llm-files-generator.pot` no longer matches the source:

```bash
sh tests/build-translations.sh --check-pot
```

A catalog that is 99% translated still passes WordPress (each missing string silently falls back to
English), which is why the script treats any untranslated entry as an error. The same run parses
every compiled `.mo` with `tests/check-translations.php`, which needs no WordPress and no installed
locales, and asserts the header (UTF-8, `Language:`, `Plural-Forms:`), that no entry is empty, that
printf placeholders and `<strong>` markup survive translation, and that a known string resolves to
the expected translation per locale:

```bash
php tests/check-translations.php
```

`tests/check-i18n-bootstrap.php` checks the wiring rather than the strings: it stubs the WordPress
functions the plugin calls while loading, requires the main plugin file and asserts that
`load_plugin_textdomain()` points at this plugin's `languages/` folder, that the `plugin_locale`
filter maps `pt-BR`, `es-MX`, `zh-CN`, `ar_SA` and `en_GB` to the shipped catalogs, and that the RTL
filters stay inert for left-to-right locales.

```bash
php tests/check-i18n-bootstrap.php
```

See [`docs/i18n.md`](i18n.md) for the locale table, the RTL behaviour and the full update workflow.

## Release ZIP check

The only asset WordPress can install is `<slug>.zip`, whose single root folder is the plugin folder:

```bash
unzip -Z1 icc-gg-llm-files-generator.zip | cut -d/ -f1 | sort -u
# exactly one line: icc-gg-llm-files-generator
```

Any other result (several lines) means the archive was built from the repository root and will fail
with *"The package contains more than one folder."*

## Forced update check (no WordPress needed)

`includes/icc-gg-llm-files-generator-github-updater.php` only needs a handful of WordPress functions,
so the asset selection logic can be exercised with a stub harness in `/tmp`:

```php
<?php
define( 'ABSPATH', '/tmp/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ICC_GG_LLM_FILES_GENERATOR_BASENAME', 'icc-gg-llm-files-generator/icc-gg-llm-files-generator.php' );

$transients = array();
function get_site_transient( $key ) { global $transients; return isset( $transients[ $key ] ) ? $transients[ $key ] : false; }
function set_site_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_site_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function wp_remote_get( $url, $args = array() ) { return $GLOBALS['http_response']; }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return $response; }

class ICC_GG_LLM_Files_Generator { const VERSION = '2.0.0'; }

require '/path/to/plugin/includes/icc-gg-llm-files-generator-github-updater.php';

// Feed the real asset list captured from the GitHub API.
$assets = json_decode( '[{"name":"wordpressiccllmfiles-2.0.0.zip","browser_download_url":"https://github.com/ivancarlosti/wordpressiccllmfiles/releases/download/v2.0.0/wordpressiccllmfiles-2.0.0.zip"},{"name":"icc-gg-llm-files-generator.zip","browser_download_url":"https://github.com/ivancarlosti/wordpressiccllmfiles/releases/download/v2.0.0/icc-gg-llm-files-generator.zip"}]' );
$GLOBALS['http_response'] = json_encode( array( 'tag_name' => 'v2.0.1', 'html_url' => 'https://github.com/ivancarlosti/wordpressiccllmfiles/releases/tag/v2.0.1', 'assets' => $assets ) );

$result = ICC_GG_LLM_Files_Generator_Github_Updater::check_for_updates();
// state: update, version: 2.0.1, installed: 2.0.0
```

`get_package_url()` must return the `…/download/v2.0.1/icc-gg-llm-files-generator.zip` URL even though
`wordpressiccllmfiles-2.0.0.zip` is listed first — that ordering is what made "Update now"
intermittently fail.

## Manual verification in WordPress

1. `wp plugin list --name=icc-gg-llm-files-generator` and note the installed version.
2. **Plugins → Installed Plugins** → **Check for updates**. Expect the success notice and, when a newer
   tag exists, a warning notice with **Update now** and **Release notes**.
3. Click **Update now** → the native upgrader runs and ends on the plugin page with the new version.
4. Open **Dashboard → Updates** and confirm the plugin is gone from the list.
5. Deactivate/activate the plugin to confirm the settings footer renders the version and the cached
   update state.
