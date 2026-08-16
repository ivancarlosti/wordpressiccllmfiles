=== ICC.gg LLM Files Generator ===
Contributors: ivancarlosti
Tags: ai, llms.txt, markdown, seo, automation
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generates and serves llms.txt and llms-full.txt files so AI agents and LLMs can easily discover and read your site's content.

== Description ==

The ICC.gg LLM Files Generator plugin generates the two files described by the emerging llms.txt convention, making your WordPress content readily consumable by AI agents, LLM-powered tools, and search assistants.

* `/llms.txt` — a concise Markdown index of your site: H1 site title, a blockquote summary, and organized lists of links to your pages, posts and custom post types with short descriptions.
* `/llms-full.txt` — the full Markdown content of every published, public page, post and custom post type.

The generated content is stored in WordPress options (not the filesystem), so it works on any host, including managed WordPress hosts with read-only filesystems.

**Features:**

* **AI-Assisted Generation** — Optionally connect an OpenAI-compatible chat completions API to refine the generated files. Works with OpenAI, DeepSeek, or any compatible endpoint.
* **Diff-Based Incremental Updates** — When content changes, the plugin sends a compact context to the AI and applies only the necessary insert/remove operations, minimizing token usage.
* **Reliable Full Regeneration Fallback** — If the AI response is invalid or cannot be applied, the plugin automatically falls back to a deterministic full regeneration built from your live site data.
* **Automatic Updates** — Files are regenerated automatically when a page, post, or custom post type is added, updated, trashed, deleted, restored, published or unpublished.
* **Smart Debouncing** — Bulk edits are coalesced into a single deferred regeneration so you never trigger dozens of AI calls.
* **Independent Delivery Toggles** — Enable or disable delivery of each file independently.
* **Clean Rewrite Rules** — `/llms.txt` and `/llms-full.txt` are served directly with `text/plain` content type, `noindex` robots tag, and correct content length.
* **Developer Hooks** — Filters and actions for customizing collected content, generated files, and AI request bodies.

Translations are managed through the WordPress.org translation platform. The plugin is fully internationalized and ready for translation into any language.

Much of the documentation can be found on the **Settings > LLM Files Generator** dashboard page.

Please submit issues to the Github repo: https://github.com/ivancarlosti/icc-gg-llm-files-generator

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > LLM Files Generator** and configure the plugin to meet your needs.
4. Click **Force Generate/Update Files** to generate the files immediately.

**Optional AI Setup:**

1. On the settings page, enter your AI API URL (e.g., OpenAI: `https://api.openai.com/v1/chat/completions`, DeepSeek: `https://api.deepseek.com/v1/chat/completions`).
2. Enter your API Key.
3. Enter the Model name (e.g., OpenAI: `gpt-4o-mini`, DeepSeek: `deepseek-v4-flash`).
4. Click **Save Changes**, then **Force Generate/Update Files**.

If you do not configure an AI API, the plugin generates the files using deterministic site data automatically.

== Frequently Asked Questions ==

= What is llms.txt? =

`llms.txt` is an emerging convention that gives LLM/AI agents a concise, machine-readable index of a website's content. It typically contains an H1 site title, a short summary, and Markdown lists of links to important pages with short descriptions.

= Do I need an AI API to use this plugin? =

No. The plugin always generates the files from your live site data. The AI API is optional and is used to refine the generated content. If it is not configured, or the API fails, the plugin falls back to deterministic generation.

= Where are the generated files stored? =

The generated content is stored in WordPress options, not on the filesystem. This makes the plugin compatible with any host, including managed WordPress platforms.

= How do the two files differ? =

`/llms.txt` is a concise index of your content, while `/llms-full.txt` contains the full Markdown content of every published, public page, post and custom post type.

= How do the automatic updates work? =

The plugin hooks into WordPress post transitions (`save_post`, `wp_trash_post`, `delete_post`, `untrashed_post`, `transition_post_status`) and regenerates the files. A debounce lock coalesces bulk edits so only a single final regeneration runs.

= What is the difference between diff mode and full regeneration? =

In diff mode, the plugin sends the AI the changed post(s) plus the current file content, and the AI returns strict JSON insert/remove operations that are applied safely. If the AI response is invalid or cannot be applied, the plugin automatically falls back to a full regeneration.

= How do I force regeneration? =

Go to **Settings > LLM Files Generator** and click the **Force Generate/Update Files** button.

= What happens if the AI returns an error? =

The plugin records the error in the last-error option and falls back to deterministic generation, so the previous files are never left broken.

== Changelog ==

= 1.0.0 =
* Initial release.
* Generate and serve `/llms.txt` and `/llms-full.txt`.
* Optional OpenAI-compatible AI integration with diff-based incremental updates and full regeneration fallback.
* Automatic updates on post/page/CPT changes with debouncing.
* Admin settings page with delivery toggles, AI configuration, and force generation.
