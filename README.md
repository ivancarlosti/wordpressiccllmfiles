# ICC.gg LLM Files Generator

A WordPress plugin that generates and serves `llms.txt` and `llms-full.txt` files for AI agents, LLM-powered tools, and search assistants.

<!-- buttons -->
[![Stars](https://img.shields.io/github/stars/ivancarlosti/wordpressiccllmfiles?label=⭐%20Stars&color=gold&style=flat)](https://github.com/ivancarlosti/wordpressiccllmfiles/stargazers)
[![Watchers](https://img.shields.io/github/watchers/ivancarlosti/wordpressiccllmfiles?label=Watchers&style=flat&color=red)](https://github.com/sponsors/ivancarlosti)
[![Forks](https://img.shields.io/github/forks/ivancarlosti/wordpressiccllmfiles?label=Forks&style=flat&color=ff69b4)](https://github.com/sponsors/ivancarlosti)
[![Downloads](https://img.shields.io/github/downloads/ivancarlosti/wordpressiccllmfiles/total?label=Downloads&color=success)](https://github.com/ivancarlosti/wordpressiccllmfiles/releases)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/ivancarlosti/wordpressiccllmfiles?label=Activity)](https://github.com/ivancarlosti/wordpressiccllmfiles/pulse)
[![GitHub Issues](https://img.shields.io/github/issues/ivancarlosti/wordpressiccllmfiles?label=Issues&color=orange)](https://github.com/ivancarlosti/wordpressiccllmfiles/issues)  
[![License](https://img.shields.io/github/license/ivancarlosti/wordpressiccllmfiles?label=License)](LICENSE)
[![GitHub last commit](https://img.shields.io/github/last-commit/ivancarlosti/wordpressiccllmfiles?label=Last%20Commit)](https://github.com/ivancarlosti/wordpressiccllmfiles/commits)
[![Security](https://img.shields.io/badge/Security-View%20Here-purple)](https://github.com/ivancarlosti/wordpressiccllmfiles/security)
[![Code of Conduct](https://img.shields.io/badge/Code%20of%20Conduct-2.1-4baaaa)](https://github.com/ivancarlosti/wordpressiccllmfiles?tab=coc-ov-file)
<!-- endbuttons -->

## Features

- **AI-Assisted Generation** — Optionally connect an OpenAI-compatible chat completions API to refine the generated files. Works with OpenAI, DeepSeek, or any compatible endpoint.
- **Diff-Based Incremental Updates** — When content changes, the plugin sends a compact context to the AI and applies only the necessary insert/remove operations, minimizing token usage.
- **Reliable Full Regeneration Fallback** — If the AI response is invalid or cannot be applied, the plugin automatically falls back to a deterministic full regeneration built from live site data.
- **Automatic Updates** — Files are regenerated automatically when a page, post, or custom post type is added, updated, trashed, deleted, restored, published, or unpublished.
- **Smart Debouncing** — Bulk edits are coalesced into a single deferred regeneration so you never trigger dozens of AI calls.
- **Independent Delivery Toggles** — Enable or disable delivery of each file independently.
- **Clean Rewrite Rules** — `/llms.txt` and `/llms-full.txt` are served directly with `text/plain` content type, `noindex` robots tag, and correct content length.
- **Developer Hooks** — Filters and actions for customizing collected content, generated files, and AI request bodies.

## Requirements

- WordPress 5.0+
- PHP 8.1+
- Optional: an OpenAI-compatible chat completions API for AI-assisted generation

## Installation

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings > LLM Files Generator** and configure the plugin to meet your needs.
4. Click **Force Generate/Update Files** to generate the files immediately.

## Quick Setup

The plugin generates `/llms.txt` and `/llms-full.txt` from your live site data automatically. AI-assisted generation is optional.

To enable AI-assisted generation:

1. On the settings page, enter your AI API URL (e.g., OpenAI: `https://api.openai.com/v1/chat/completions`, DeepSeek: `https://api.deepseek.com/v1/chat/completions`).
2. Enter your API Key.
3. Enter the Model name (e.g., OpenAI: `gpt-4o-mini`, DeepSeek: `deepseek-v4-flash`).
4. Click **Save Changes**, then **Force Generate/Update Files**.

If you do not configure an AI API, the plugin generates the files using deterministic site data automatically.

## Configuration Reference

### File Delivery

| Setting | Description |
|---|---|
| **Enable llms.txt** | Deliver the concise `/llms.txt` index to visitors and AI agents |
| **Enable llms-full.txt** | Deliver the full `/llms-full.txt` content to visitors and AI agents |

### AI Settings

| Setting | Description |
|---|---|
| **AI API URL** | The OpenAI-compatible chat completions endpoint URL. Leave empty to disable AI-assisted generation |
| **API Key** | The API key used to authenticate with the AI API. Stored in the WordPress options table |
| **Model** | The AI model name used for chat completions (e.g., `gpt-4o-mini`, `deepseek-v4-flash`) |

## What is llms.txt?

`llms.txt` is an emerging convention that gives LLM/AI agents a concise, machine-readable index of a website's content. It typically contains an H1 site title, a short summary, and Markdown lists of links to important pages with short descriptions.

This plugin generates two plain-text files:

- `/llms.txt` — a concise Markdown index of your site: H1 site title, a blockquote summary, and organized lists of links to your pages, posts, and custom post types with short descriptions.
- `/llms-full.txt` — the full Markdown content of every published, public page, post, and custom post type.

The generated content is stored in WordPress options (not the filesystem), so it works on any host, including managed WordPress hosts with read-only filesystems.

## Hooks & Filters

The plugin provides hooks for customization. See the main plugin file for the complete list.

### Filters

- `icc_gg_llm_files_generator_settings` — Modify settings values early in plugin bootstrap
- `icc_gg_llm_files_generator_settings_fields` — Modify the fields provided on the settings page
- `icc_gg_llm_files_generator_content_types` — Modify the post types collected for generation
- `icc_gg_llm_files_generator_collect_item` — Modify each collected item (2 args: item array, `WP_Post`)
- `icc_gg_llm_files_generator_request_body` — Modify the AI request body before it is sent
- `icc_gg_llm_files_generator_llms_txt_content` — Modify the final `llms.txt` content before it is stored
- `icc_gg_llm_files_generator_llms_full_txt_content` — Modify the final `llms-full.txt` content before it is stored

### Actions

- `icc_gg_llm_files_generator_files_updated` — Fires after files are regenerated (1 arg: array of generated content)
- `icc_gg_llm_files_generator_generation_failed` — Fires when generation fails (1 arg: `WP_Error`)

### Helper Functions

- `icc_gg_llm_files_generator_generate_files()` — Force (re)generation of the `llms.txt` and `llms-full.txt` files
- `icc_gg_llm_files_generator_get_llms_txt()` — Retrieve the generated `llms.txt` content
- `icc_gg_llm_files_generator_get_llms_full_txt()` — Retrieve the generated `llms-full.txt` content

## Frequently Asked Questions

### Do I need an AI API to use this plugin?

No. The plugin always generates the files from your live site data. The AI API is optional and is used to refine the generated content. If it is not configured, or the API fails, the plugin falls back to deterministic generation.

### Where are the generated files stored?

The generated content is stored in WordPress options, not on the filesystem. This makes the plugin compatible with any host, including managed WordPress platforms.

### How do the two files differ?

`/llms.txt` is a concise index of your content, while `/llms-full.txt` contains the full Markdown content of every published, public page, post, and custom post type.

### How do automatic updates work?

The plugin hooks into WordPress post transitions (`save_post`, `wp_trash_post`, `delete_post`, `untrashed_post`, `transition_post_status`) and regenerates the files. A debounce lock coalesces bulk edits so only a single final regeneration runs.

### What is the difference between diff mode and full regeneration?

In diff mode, the plugin sends the AI the changed post(s) plus the current file content, and the AI returns strict JSON insert/remove operations that are applied safely. If the AI response is invalid or cannot be applied, the plugin automatically falls back to a full regeneration.

### How do I force regeneration?

Go to **Settings > LLM Files Generator** and click the **Force Generate/Update Files** button.

### What happens if the AI returns an error?

The plugin records the error in the last-error option and falls back to deterministic generation, so the previous files are never left broken.

## Credits

**ICC.gg LLM Files Generator** is maintained by [Ivan Carlos](https://github.com/ivancarlosti).

<!-- footer -->
---

## 🧑‍💻 Consulting and technical support
* For personal support and queries, please submit a new issue to have it addressed.
* For commercial related questions, please [**contact me**][ivancarlos] for consulting costs.

[cc]: https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/adding-a-code-of-conduct-to-your-project
[contributing]: https://docs.github.com/en/articles/setting-guidelines-for-repository-contributors
[security]: https://docs.github.com/en/code-security/getting-started/adding-a-security-policy-to-your-repository
[support]: https://docs.github.com/en/articles/adding-support-resources-to-your-project
[it]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository#configuring-the-template-chooser
[prt]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository
[funding]: https://docs.github.com/en/articles/displaying-a-sponsor-button-in-your-repository
[ivancarlos]: https://ivancarlos.me
