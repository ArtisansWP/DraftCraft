# DraftCraft

AI Blog Post Writer and Drafting Assistant for WordPress.

DraftCraft connects your WordPress site to OpenRouter to prepare SEO-ready blog post drafts that you review, edit, and publish in your own voice. It includes SEO metadata sync, featured images, internal linking, Table of Contents, FAQ schema markup, and a keyword CSV queue.

---

## Features

### 1. Automated Scheduling
Set your content schedule to run hourly, twice daily, daily, or weekly using WordPress Cron. You can also generate posts on demand with one click.

### 2. Multi-Model AI Routing
Connect your OpenRouter API key to access models from OpenAI (GPT-4o), Anthropic (Claude), Google (Gemini), DeepSeek, Meta (Llama), and others. Fetch and refresh live models directly from the admin dashboard.

### 3. Writing Style and Voice Control
Define custom system prompts to set tone, audience, structure, and formatting. Built-in platform rules ensure every post includes valid semantic HTML, subheadings, and minimum word counts.

### 4. Category Selection and Rotation
Select target categories and set their priority order. DraftCraft cycles through categories automatically so your content coverage stays balanced across all topics.

### 5. Keyword-Driven Content Queue
Upload a CSV file with target keywords, assigned categories, custom instructions, and scheduled dates. DraftCraft works through queued keywords first before resuming category rotation.

### 6. SEO Plugin Sync
Automatically write focus keywords, SEO titles, and meta descriptions into active SEO plugins:
- Rank Math
- Yoast SEO
- All in One SEO (AIOSEO)

### 7. Automated Featured Images
Generate and attach featured images to WordPress posts automatically:
- **OpenRouter Image Models**: Flux 1.1 Pro, DALL-E 3, Stable Diffusion
- **Unsplash API**: High-quality stock photography based on post keywords

### 8. Smart Internal Linking
Scan published posts and insert high-relevance internal links automatically using topical context and category overlap.

### 9. Table of Contents
Generate an automatic Table of Contents based on H2 and H3 headings. Includes smooth scrolling with sticky header offset support.

### 10. FAQ Schema Markup
Generate relevant FAQs for each post. Adds accessible FAQ HTML and schema.org `FAQPage` JSON-LD structured data for search engines.

### 11. Taxonomy Description Generator
Generate rich descriptions for categories and tags with one click directly inside the term edit screen.

### 12. Post Editor AI Assistant
Refine, expand, or rewrite generated drafts directly inside the WordPress Block Editor (Gutenberg) or Classic Editor.

---

## Requirements

- WordPress 6.0 or higher (Tested up to WordPress 7.1)
- PHP 8.0 or higher
- OpenRouter API key ([openrouter.ai](https://openrouter.ai))
- Unsplash Access Key (Optional, for stock photos)

---

## Installation

1. Download the plugin zip file.
2. Go to **WordPress Admin -> Plugins -> Add New -> Upload Plugin**.
3. Select the zip file and click **Install Now**.
4. Click **Activate**.
5. Navigate to the **DraftCraft** menu in your WordPress dashboard.
6. Enter your OpenRouter API key on the **API & Model** tab and save settings.

---

## Developer Hooks

DraftCraft provides hooks and filters to customize prompts, payloads, schedules, and generated content.

### Action Hooks

| Hook | Parameters | Description |
| :--- | :--- | :--- |
| `draftcraft_activated` | `$settings` | Runs when the plugin is activated |
| `draftcraft_deactivated` | none | Runs when the plugin is deactivated |
| `draftcraft_before_generate` | `$settings` | Runs before the post generation pipeline starts |
| `draftcraft_api_response_received` | `$decoded, $settings` | Runs after receiving the raw API response |
| `draftcraft_post_created` | `$post_id, $settings` | Runs after a post is saved to WordPress |
| `draftcraft_generation_failed` | `$error_message, $settings` | Runs if the generation pipeline encounters an error |
| `draftcraft_settings_saved` | `$new_settings, $old_settings` | Runs after settings are updated in admin |
| `draftcraft_before_seo_sync` | `$post_id, $seo_data, $plugin` | Runs before saving SEO metadata |
| `draftcraft_after_seo_sync` | `$post_id, $seo_data, $plugin` | Runs after saving SEO metadata |
| `draftcraft_faqs_saved` | `$post_id, $faqs` | Runs after FAQ schema and HTML are saved |
| `draftcraft_before_image_generate` | `$post_id, $prompt, $provider` | Runs before requesting a featured image |
| `draftcraft_after_image_generate` | `$attachment_id, $post_id, $provider` | Runs after featured image is attached to media library |
| `draftcraft_csv_imported` | `$new_rows, $queue` | Runs after keywords CSV is imported |
| `draftcraft_bulk_item_completed` | `$queue_item, $post_id` | Runs after a queued keyword post is completed |

### Filter Hooks

| Filter | Parameters | Description |
| :--- | :--- | :--- |
| `draftcraft_system_prompt` | `$prompt, $settings` | Modify the generation system prompt |
| `draftcraft_user_prompt` | `$prompt, $settings` | Modify the generation user prompt |
| `draftcraft_api_payload` | `$payload, $settings` | Modify the request payload sent to OpenRouter |
| `draftcraft_api_headers` | `$headers, $settings` | Modify HTTP headers sent to OpenRouter |
| `draftcraft_api_timeout` | `$timeout` | Modify the HTTP request timeout (default: 120s) |
| `draftcraft_post_args` | `$post_args, $post_data, $settings` | Modify arguments passed to `wp_insert_post` |
| `draftcraft_models_list` | `$models, $type` | Modify the list of available models in admin |
| `draftcraft_schedule_options` | `$options` | Add or modify cron recurrence schedules |
| `draftcraft_image_prompt` | `$prompt, $title, $keyword, $settings` | Modify the prompt used for image generation |
| `draftcraft_image_system_prompt` | `$system_prompt, $prompt, $settings` | Modify the image style system instructions |
| `draftcraft_image_api_payload` | `$payload, $prompt, $settings` | Modify the image generation API payload |
| `draftcraft_image_url` | `$image_url, $post_id, $settings` | Filter the downloaded image URL |
| `draftcraft_allowed_image_hosts` | `$allowed, $url` | Filter allowed remote hosts for image download |
| `draftcraft_internal_link_thresholds` | `$thresholds, $settings` | Adjust score thresholds for internal linking |
| `draftcraft_internal_link_stopwords` | `$stopwords` | Modify stopwords used in content matching |
| `draftcraft_link_candidates` | `$candidates, $post_type` | Filter candidates available for internal links |
| `draftcraft_ranked_link_candidates` | `$ranked, $context` | Filter scored and ranked link candidates |
| `draftcraft_toc_html` | `$toc_html, $headings, $content` | Modify generated Table of Contents HTML |
| `draftcraft_toc_title` | `$title` | Modify the Table of Contents heading label |
| `draftcraft_faq_jsonld` | `$schema, $post_id, $faqs` | Modify the FAQ JSON-LD structured data array |
| `draftcraft_term_desc_system_prompt` | `$prompt, $name, $slug, $taxonomy` | Filter the system prompt for term descriptions |
| `draftcraft_term_desc_user_prompt` | `$prompt, $name, $slug, $taxonomy` | Filter the user prompt for term descriptions |
| `draftcraft_term_description` | `$description, $name, $slug, $taxonomy` | Filter generated taxonomy term description |
| `draftcraft_gutenberg_content` | `$content` | Filter block content after Gutenberg normalization |

---

## Code Examples

### Customize the System Prompt

```php
add_filter( 'draftcraft_system_prompt', function( $prompt, $settings ) {
    return $prompt . "\n\nAlways include a bulleted takeaways section at the start.";
}, 10, 2 );
```

### Add a Custom Recurrence Interval

```php
add_filter( 'draftcraft_schedule_options', function( $options ) {
    $options['every_three_days'] = array(
        'interval' => 3 * DAY_IN_SECONDS,
        'display'  => __( 'Every 3 Days', 'textdomain' ),
    );
    return $options;
} );
```

---

## File Structure

```text
draftcraft/
├── assets/
│   ├── admin.css          # Admin panel styling
│   ├── admin.js           # Admin UI interactions, AJAX polling, model fetching
│   ├── editor.js          # Post editor AI assistant
│   └── term-ai.js         # Category description generator
├── includes/
│   ├── class-draftcraft-admin.php          # Admin screens, AJAX handlers, settings save
│   ├── class-draftcraft-blocks.php         # HTML to Gutenberg block converter
│   ├── class-draftcraft-bulk.php           # CSV import, keyword queue management
│   ├── class-draftcraft-cron.php           # WP-Cron scheduler and interval registry
│   ├── class-draftcraft-internal-links.php # Semantic internal linking engine
│   ├── class-draftcraft-media.php          # Featured image generator (OpenRouter / Unsplash)
│   ├── class-draftcraft-pipeline.php       # Content generation workflow
│   ├── class-draftcraft-schema.php         # FAQ HTML and JSON-LD schema builder
│   ├── class-draftcraft-seo.php            # Rank Math, Yoast, AIOSEO sync
│   ├── class-draftcraft-toc.php            # Table of Contents generator
│   └── helpers.php                         # Settings defaults, string utilities, rotation
├── languages/                              # Translation files (.pot)
├── templates/
│   └── admin/
│       ├── editor-metabox.php              # Editor AI metabox template
│       ├── settings-page.php               # Main admin settings page wrapper
│       ├── sidebar.php                     # Quick generate and summary sidebar
│       └── tabs/
│           ├── tab-api.php                 # API and model configuration
│           ├── tab-content.php             # Category selection and rotation
│           ├── tab-drafts.php              # Draft queue list
│           ├── tab-schedule.php            # Automation settings and execution summary
│           └── tab-seo.php                 # SEO, TOC, internal links, keyword queue
├── draftcraft.php                          # Main plugin bootstrap
├── readme.txt                              # WordPress.org readme
├── LICENSE                                 # GNU General Public License v2
└── uninstall.php                           # Clean uninstallation handler
```

---

## Security

- Nonces verify all settings forms, manual trigger requests, and AJAX actions.
- Capability checks ensure only users with `manage_options` or `edit_post` can perform actions.
- API keys are masked in the UI and never exposed in frontend scripts.
- Input data is sanitized using WordPress helper functions (`sanitize_text_field`, `sanitize_key`, `wp_unslash`).
- Output data is escaped using `esc_html`, `esc_attr`, `esc_url`, and `wp_kses_post`.

---

## License

DraftCraft is open source software licensed under the [GNU General Public License v2 or later](LICENSE).

---

## Credits

Developed and maintained by [ArtisansWP](https://artisanswp.com).