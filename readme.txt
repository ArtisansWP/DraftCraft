=== DraftCraft ===
Contributors: artisanswp
Tags: ai, content generator, auto blog, post scheduler, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated AI blog post scheduler via OpenRouter. Features SEO plugin sync, auto featured images, internal linking, FAQ schema, and bulk CSV queue.

== Description ==

DraftCraft is an automated AI blogging and content generation plugin for WordPress. Connect your OpenRouter API key to generate SEO-optimized articles on a schedule using models like Claude, GPT-4o, DeepSeek, Gemini, and Llama.

Every generated post lands in your WordPress dashboard as a draft for you to review, or goes live automatically if you prefer.

= Comprehensive Features =

* **📅 Automated Scheduling & Cron Engine** — Hourly, twice-daily, daily, or weekly automation via WordPress Cron, plus on-demand "Generate Post Now".
* **🤖 Multi-Model AI Integration** — Choose any OpenRouter language or image model with live dynamic model fetching and 24-hour caching.
* **✍️ Writing & Image Style Controls** — Customize brand voice, target audience, tone, and visual guidelines through dedicated system prompts.
* **🗂️ Category Selection & Smart Rotation** — Assign posts to chosen categories with automated cycling and drag-and-drop hierarchy sorting.
* **🏷️ Taxonomy AI Description Generator** — Generate rich, SEO-friendly category and tag descriptions with one click directly from the term editor.
* **📝 Post Editor AI Assistant Metabox** — Refine, expand, or rewrite generated drafts directly inside the Gutenberg or Classic editor.
* **📈 Complete SEO Plugin Sync** — Auto-populate focus keywords, SEO titles, and meta descriptions for Rank Math, Yoast SEO, and All in One SEO (AIOSEO).
* **🖼️ Automated Featured Images** — Generate AI images using OpenRouter models (Flux, DALL·E, etc.) or fetch curated photos via Unsplash.
* **🔗 Smart Internal Linking** — Intelligent semantic matching and keyword insertion to connect your articles and boost site architecture.
* **❓ FAQ Schema (JSON-LD)** — Generate relevant FAQs formatted with valid Schema.org `FAQPage` JSON-LD structured data.
* **📑 Table of Contents (TOC)** — Auto-generate navigation TOCs from H2/H3 headings with custom smooth scroll offsets and Rank Math compatibility.
* **📥 Bulk Keyword CSV Queue** — Import keyword spreadsheets with custom instructions and target dates to automate your editorial calendar.
* **📋 AI Drafts Queue Dashboard** — Track, preview, edit, and publish AI-generated posts from a clean admin panel.
* **🔒 Strict Security Standards** — Enterprise-level sanitization, nonce protection, capability verification, and masked database key storage.

= How is the content quality? =

DraftCraft gives you full control over the writing style through a custom system prompt. You can tell the AI to write in your brand voice, target a specific audience, match your existing tone, and include any topic-specific instructions. You choose the AI model — use a faster, lighter model for drafts or a more powerful one for near-publish-ready content.

All posts are created as drafts by default so you review them first. Think of it as having a dedicated first-draft writer on your team: it does the heavy lifting, you apply the finishing touches.

= Is it safe for SEO? =

Yes — with the right setup. DraftCraft-generated posts are regular WordPress posts. Whether they help or hurt SEO depends on the quality of your prompts and the model you choose. We recommend:
- Keeping Post Status set to **Draft** and reviewing before publishing.
- Writing a detailed system prompt that matches your site's niche and voice.
- Enabling SEO Sync so each post gets a proper focus keyword, title, and meta description.

= Security Practices =

DraftCraft adheres strictly to WordPress security best practices: capability checks (`manage_options`, `edit_posts`), nonce verification on all submissions and AJAX actions, thorough sanitization of inputs, and complete escaping on outputs. API keys are safely stored in your WordPress database and masked in the admin interface.

= Third-Party Services =

DraftCraft connects to external services when you configure and use those features. Your prompts, settings, and related content may be sent to these providers so they can generate text or images.

**OpenRouter** (required for AI content; optional for AI images)

* Used when you generate posts or OpenRouter-based featured images.
* Data sent may include your API key (in the Authorization header), model choice, system/user prompts, category context, and article JSON responses.
* Website: [https://openrouter.ai/](https://openrouter.ai/)
* Terms: [https://openrouter.ai/terms](https://openrouter.ai/terms)
* Privacy: [https://openrouter.ai/privacy](https://openrouter.ai/privacy)

**Unsplash** (optional, only if Image Provider = Unsplash)

* Used to search and download a stock photo for the featured image.
* Data sent may include your Unsplash access key and a search query derived from the post title/keyword.
* Website: [https://unsplash.com/](https://unsplash.com/)
* API / Developer Terms: [https://unsplash.com/api-terms](https://unsplash.com/api-terms)
* Privacy Policy: [https://unsplash.com/privacy](https://unsplash.com/privacy)

No account with ArtisansWP is required. You supply your own API keys directly to OpenRouter and Unsplash. DraftCraft does not proxy those keys through ArtisansWP servers.

Rank Math, Yoast SEO, All in One SEO, OpenRouter, Unsplash, and model names are trademarks of their respective owners. DraftCraft is not affiliated with or endorsed by those projects.

== Installation ==

1. Upload the `draftcraft` folder to the `/wp-content/plugins/` directory, or install through **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **DraftCraft** in the left admin menu.
4. On the **API & Model** tab, enter your OpenRouter API key, choose a content model, and click **Save Changes**.
5. On the **Content** tab, choose categories and set your writing style / system prompt.
6. (Optional) On the **SEO** tab, enable SEO sync, internal linking, FAQ schema, table of contents, and bulk keyword queue.
7. (Optional) On the **Schedule** tab, enable automation and pick your preferred frequency (Hourly, Twice Daily, Daily, or Weekly).
8. Click **Generate Post Now** to create a test draft immediately.

== Frequently Asked Questions ==

= Do I need an API key? =
Yes. You need an [OpenRouter](https://openrouter.ai/) API key. OpenRouter gives you access to hundreds of AI models through one account. Unsplash is optional; if you use it for stock featured images, you will need a free Unsplash access key.

= Can I use ChatGPT, Claude, DeepSeek, or Gemini? =
Yes. By connecting your OpenRouter API key, you have instant access to OpenAI (GPT-4o, o1, o3-mini), Anthropic (Claude 3.5 Sonnet / Haiku), DeepSeek (V3, R1), Google Gemini (2.0 Flash, 1.5 Pro), Meta Llama, Mistral, and dozens of other cutting-edge language models.

= Does DraftCraft support automatic internal linking and featured images? =
Yes. DraftCraft includes an intelligent internal linking engine that semantically matches your new posts to existing published content. It also generates and sets featured images automatically using OpenRouter image models (like Flux or DALL·E) or Unsplash stock photos.

= Will Google penalise my AI-generated content? =
Google's guidelines state that high-quality, helpful content is rewarded regardless of how it is produced. Use DraftCraft to generate a solid first draft, then review, customize, and publish.

= Does the content actually sound good? =
Yes. Quality depends on the model selected and your system prompt instructions. Top-tier models produce publication-quality writing when provided with clear brand and voice guidelines.

= Will this publish posts automatically? =
By default, DraftCraft creates posts as **Draft** so you can review them first. You can change the default post status to **Published** in settings if you want fully automated, hands-free publishing.

= Which SEO plugins are supported? =
Rank Math, Yoast SEO, and All in One SEO (AIOSEO) are all supported. DraftCraft auto-detects whichever plugin is active on your site.

= How does category rotation work? =
Select your desired categories and enable Category Rotation on the Content tab. DraftCraft will cycle through them sequentially on each scheduled run.

= Can I plan content in advance with a CSV? =
Yes. Use the Bulk Keyword CSV Importer on the SEO tab. Upload a spreadsheet with keywords, target categories, instructions, and scheduled dates. DraftCraft will process the queue automatically.

= What data is sent to third-party services? =
When generating content, DraftCraft communicates directly with OpenRouter (and Unsplash if configured). Prompts and category context are transmitted securely over HTTPS. No data or API keys are ever routed through ArtisansWP servers.

= Does DraftCraft store my API key securely? =
Your API key is stored safely in your own WordPress database (`wp_options`) and is always masked (••••••••) in the administrative interface.

== Screenshots ==

1. API & Model settings — OpenRouter key configuration and model selection.
2. Content settings — Brand writing style, category selection, and rotation order.
3. SEO & Content features — SEO sync, smart internal linking, FAQ/TOC, and bulk CSV queue.
4. Schedule settings — Automation frequency and cron controls.
5. AI Drafts Queue — Manage and review generated posts.
6. Quick Generate sidebar — Trigger manual runs and inspect live status.

== Changelog ==

= 1.2.2 =
* Initial release on WordPress.org.
