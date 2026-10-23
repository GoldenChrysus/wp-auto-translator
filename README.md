# WP Auto Translator

A small WordPress plugin that translates posts and pages with OpenAI. It is intentionally much narrower than WPML: one base language, a list of target languages, and a real post per translation.

The plugin does nothing until a base locale is saved. Until then it does not tag posts, queue translations, or change queries.

## What it does

1. On activation, asks for the base locale and tags existing posts and pages with it.
2. Asks for an OpenAI API key. `WAT_OPENAI_API_KEY` in `wp-config.php` overrides the value saved in the database, and the database copy is not autoloaded.
3. Asks which languages to translate into.
4. Asks whether newly published base-language posts should be queued automatically.
5. Offers a one-click queue of every published base-language post that is missing a translation.
6. Shows the translation status of each base-language post and can translate one language on demand.
7. Adds a `lang` argument to the REST API and to `WP_Query`.

A translation is its own post, linked to the source through the `{prefix}wat_translations` table. Originals have `source_post_id = 0`. Each target locale gets a sibling post in the same `group_id`.

```http
GET /wp-json/wp/v2/posts?lang=ja
GET /wp-json/wp/v2/pages?lang=fr_FR
```

`lang` accepts a WordPress locale (`en_US`, `ja`) or the same code with a hyphen (`en-US`). An unknown code is a 400. Each post in the response includes a `lang` field.

`slug` and `lang` together return the post in that locale. The slug can be the translated post's own slug, or the base post's slug. A base slug still returns the translation, not the original. If that locale has no translation, the collection is empty.

```http
GET /wp-json/wp/v2/posts?slug=my-post&lang=ja
```

Without `lang`, REST collections, the main front-end query, and the admin list hide translation posts so the site does not list every language at once. A translation is still available by ID, and `WP_Query` accepts `lang` directly:

```php
new WP_Query([
    'post_type' => 'post',
    'lang' => 'ja',
]);
```

`get_posts()` suppresses filters, so pass `suppress_filters => false` or set `lang` on a `WP_Query` instead.

Only the title, content, and excerpt are sent to the model. HTML, Gutenberg block comments, and shortcodes are meant to be preserved. Translated HTML is passed through `wp_kses_post()` after block comments are held aside, because kses would otherwise delete them. Script tags are removed.

The queue runs one post/locale pair per request so a long run does not time out. Publishing a post only records pending work. wp-cron picks up the next job about 15 seconds later and schedules another single event if anything remains. The status screen also walks the queue in the browser, which is the reliable path on a machine that does not receive public traffic.

Posts longer than 80,000 characters are rejected instead of being truncated into a broken translation.

## What it does not do

- Translate theme strings, menus, widgets, taxonomies, media, or custom fields.
- Add language prefixes to permalinks (`/ja/hello/`).
- Detect the visitor's language.
- Provide a translation memory or glossary.
- Delete translated posts on uninstall. Uninstall drops the map and the settings only.

Changing the base locale later does not retag posts that already have one. The settings screen requires an explicit confirmation and can tag posts that still have no locale.

## Neuron

Translations go through [Neuron](https://github.com/neuron-core/neuron-ai)'s OpenAI Responses provider. Neuron's default message store and workflow persistence are in memory, so a translation does not create its `chat_messages` table. That table is only for chat history you explicitly persist. The upload zip from `npm run plugin:zip` already contains Neuron. For local development, run `composer install` in the plugin directory.

The request uses structured output (`text.format.type` of `json_schema`). Temperature is omitted because several current models reject that parameter on the Responses API. `wat_openai_request_parameters` can add provider parameters, including `temperature` for a model that accepts it.

`wat_translator_factory` still replaces the whole translator. Return an object that implements `WpAutoTranslator\Translation\TranslatorFactory`.

Other filters:

- `wat_translatable_post_types` — default `post` and `page`. Register this before `rest_api_init`.
- `wat_translation_post_data` — the `wp_update_post()` array for a finished translation.
- `wat_openai_request_parameters` — extra Responses API fields passed to Neuron. Temperature is not included unless you add it.
- `wat_capability` — defaults to `manage_options`.

## Install on a site

WordPress.com can install this plugin on any paid plan. A free site has to be upgraded first. In wp-admin open **Plugins → Add New Plugin → Upload Plugin**, choose `dist/wp-auto-translator.zip`, and activate it. Leave the zip intact. WordPress expects the plugin folder inside the archive.

Build that zip from this repository:

```bash
npm run plugin:zip
```

The script copies the plugin, installs production Composer packages (Neuron, without PHPUnit), and writes `dist/wp-auto-translator.zip`. The same file works on a self-hosted site.

After activation, open **Translations → Setup**.

## Local development

PHP libraries and the PHP runtime are separate problems. Composer isolates libraries in `vendor/`. It does not isolate the PHP binary, extensions, or system packages. `phpenv` and `phpbrew` switch the host binary, but they still share the machine's libraries and are a poor fit next to WordPress, MySQL, and Node. This repo uses two containers instead:

- The Dev Container pins PHP 8.2, Composer, and Node 22, and mounts the host Docker socket.
- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) pins the WordPress site, its PHP, and MariaDB. The plugin directory is mounted in as itself.

Docker Desktop's WSL integration has to be enabled so the `docker` command is available inside WSL. wp-env and the Dev Container both use that client. Without it, unit tests can still run in any PHP 8.1+ image that has the `mbstring` extension.

Open the folder in the Dev Container, then:

```bash
npm run env:start
```

The site is at http://localhost:8888. wp-env prints the admin password. Activate **WP Auto Translator** if it is not already active and open **Translations → Setup**.

Unit tests do not need WordPress. They cover locale parsing, the prompt, response parsing, the OpenAI request, and the block-comment sanitizer.

```bash
composer install
composer test
```

The WordPress integration tests use the PHPUnit files wp-env already mounts at `WP_TESTS_DIR`. Current wp-env keeps that suite in a normal `cli` container and expects a second config for a second site, instead of the older `tests-cli` environment.

```bash
npm run env:start:tests
npm run test:php
```

`npm run test:php` runs both the unit suite and `tests/Integration` inside the test site. Integration coverage includes tagging existing posts, queueing a translation on publish, and filtering REST plus `WP_Query` by `lang`.

`npm run wp -- option get siteurl` runs WP-CLI in the development site.

## Layout

`wp-auto-translator.php` loads Composer's autoloader, then the classes under `src/`. `composer install` is required on any site that runs the plugin.

| Path | Role |
| --- | --- |
| `src/LocaleQuery.php` | `lang` query var, REST field, hiding translations by default |
| `src/PostWatcher.php` | Tag new posts and queue them when auto-translate is on |
| `src/TranslationQueue.php` | Create pending sibling posts |
| `src/Translation/TranslationProcessor.php` | One model call, then update the sibling post |
| `src/Admin/` | Setup wizard, settings, and the status table |

The default model is `gpt-6-luna`. Change it on the settings screen. The key, when stored by the plugin, is a non-autoloaded option and is redacted from error messages.
