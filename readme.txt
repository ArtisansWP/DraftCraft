=== DraftCraft – AI Blog Post Writer & Drafting Assistant ===
Contributors: artisanswp
Tags: ai writer, ai content, blog post, seo, content generator
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write blog posts faster. DraftCraft creates SEO-ready AI drafts you review, edit, and publish in your own voice. Works with any OpenRouter model.

== Description ==

DraftCraft is an AI drafting assistant for WordPress bloggers, content teams, and agencies. It prepares well-structured, SEO-ready blog post drafts, so you spend your time editing and adding your expertise instead of staring at a blank page.

DraftCraft is built to support writers, not replace them. Every post is saved as a draft by default. You review it, edit it with the built-in AI assistant, and publish it when it sounds like you.

Connect your own OpenRouter API key and choose the model that fits the job, including Claude, GPT-4o, Gemini, DeepSeek, and Llama. Use a fast, low-cost model for early drafts, or a more capable one when you want a draft that is close to ready.

= Who DraftCraft is for =

* Bloggers who want to publish consistently without losing their own voice.
* Content teams that need a steady flow of first drafts for editors to shape.
* Agencies managing content for several client sites.
* Small businesses that want helpful, search-friendly articles without hiring a full content team.

= How it works =

1. Add your OpenRouter API key and pick a model.
2. Describe your brand voice, audience, and tone in the writing style prompt.
3. Choose categories, add keywords, or import a keyword CSV.
4. DraftCraft creates drafts on your schedule or on demand.
5. Review, refine with the editor assistant, and publish.

= Drafting and editing =

* **Post editor AI assistant:** refine, expand, or rewrite any section of a draft directly in the block editor or the Classic editor.
* **Writing style controls:** set your brand voice, target audience, tone, and topic-specific instructions through a custom system prompt.
* **Drafts queue dashboard:** track, preview, edit, and publish AI-assisted drafts from one clean admin screen.
* **Draft-first by default:** posts are saved as drafts for review. Automatic publishing is available if you choose it.

= SEO features =

* **SEO plugin sync:** fills in the focus keyword, SEO title, and meta description for Rank Math, Yoast SEO, and All in One SEO (AIOSEO).
* **FAQ schema:** generates relevant FAQs with valid Schema.org FAQPage JSON-LD structured data.
* **Table of contents:** builds a TOC from H2 and H3 headings, with smooth-scroll offsets and Rank Math compatibility.
* **Internal linking:** suggests and inserts links to related posts on your site using semantic and keyword matching.
* **Category and tag descriptions:** generates helpful, SEO-friendly term descriptions with one click from the term editor.

= Planning and scheduling =

* **Scheduled drafts:** hourly, twice daily, daily, or weekly via WP-Cron, plus a "Generate Post Now" button.
* **Keyword CSV queue:** import a keyword spreadsheet with custom instructions and target dates to plan your editorial calendar.
* **Category rotation:** assign drafts to chosen categories, with automatic rotation and drag-and-drop ordering.

= Featured images =

* **AI images:** generate featured images with OpenRouter image models such as Flux.
* **Stock photos:** or fetch a relevant photo from Unsplash instead.
* **Visual style guidelines:** describe the look you want so images stay consistent across your site.

= Models and flexibility =

* **Any OpenRouter model:** DraftCraft fetches the current model list live and caches it for 24 hours.
* **Your keys, your account:** you connect directly to OpenRouter and Unsplash. No ArtisansWP account is needed, and your keys are never routed through our servers.

= Built with WordPress security standards =

DraftCraft follows WordPress security best practices: capability checks (`manage_options`, `edit_posts`), nonce verification on every form and AJAX action, sanitization of all input, and escaping of all output. API keys are stored in your WordPress database and masked in the admin screens.

DraftCraft is built and maintained by ArtisansWP, a senior-led WordPress engineering studio. We built it for our own content work first, then released it for everyone.

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

= Does DraftCraft replace writers? =

No. DraftCraft handles the first draft, including structure, headings, FAQs, and SEO fields. You add the experience, opinions, and finishing touches that make content worth reading. Posts are saved as drafts by default so nothing goes live without your review.

= How good is the writing? =

That depends on two things you control: the model and the writing style prompt. Tell DraftCraft who you write for, how you sound, and what to include or avoid. Lighter models are faster and cheaper for early drafts. More capable models produce drafts that need less editing.

= Is AI-assisted content safe for SEO? =

Search engines reward helpful, original content, whether or not AI was involved in drafting it. For the best results:

* Keep Post Status set to Draft and review every post before publishing.
* Write a detailed style prompt that matches your niche and voice.
* Add your own examples, experience, and insights while editing.
* Enable SEO sync so each post has a proper focus keyword, title, and meta description.

= Which AI models can I use? =

Any text or image model available on OpenRouter, including Claude, GPT-4o, Gemini, DeepSeek, Llama, and Flux. The model list updates automatically.

= Does it work with Rank Math, Yoast SEO, and All in One SEO? =

Yes. DraftCraft fills in the focus keyword, SEO title, and meta description for all three.

= Does it work with the block editor and the Classic editor? =

Yes. The AI assistant metabox is available in both.

= What does it cost to use? =

DraftCraft itself is free. AI usage is billed by OpenRouter to your own account at their published rates, and some models are free. Unsplash is optional.

= Do I need an ArtisansWP account? =

No. You enter your own API keys, and DraftCraft connects directly to OpenRouter and Unsplash.

== Third-Party Services ==

DraftCraft connects to external services only when you configure and use the related features. Your prompts, settings, and related content may be sent to these providers so they can generate text or images.

= OpenRouter (required for AI text; optional for AI images) =

Used when you generate posts, editor suggestions, term descriptions, or OpenRouter-based featured images.
Data sent may include your API key (in the Authorization header), the selected model, system and user prompts, category context, and article content returned as JSON.

* Website: [https://openrouter.ai/](https://openrouter.ai/)
* Terms: [https://openrouter.ai/terms](https://openrouter.ai/terms)
* Privacy: [https://openrouter.ai/privacy](https://openrouter.ai/privacy)

= Unsplash (optional, only when Image Provider is set to Unsplash) =

Used to search for and download a stock photo for the featured image.
Data sent may include your Unsplash access key and a search query based on the post title or keyword.

* Website: [https://unsplash.com/](https://unsplash.com/)
* API terms: [https://unsplash.com/api-terms](https://unsplash.com/api-terms)
* Privacy: [https://unsplash.com/privacy](https://unsplash.com/privacy)

No ArtisansWP account is required. You supply your own API keys directly to OpenRouter and Unsplash, and DraftCraft does not proxy them through ArtisansWP servers.

Rank Math, Yoast SEO, All in One SEO, OpenRouter, Unsplash, and all model names are trademarks of their respective owners. DraftCraft is not affiliated with or endorsed by those projects.

== Changelog ==

= 1.2.4 =
* Docs: Rewrote the plugin description, FAQ, and third-party services section to focus on AI-assisted drafting.
* Tweak: Updated the plugin header description and readme tags.

= 1.2.3 =
* Fix: Multi-layer resilient JSON parser for AI model responses preventing JSON decode errors.
* Fix: Remove unsupported response_format parameter causing HTTP 400 errors on OpenRouter.
* Enhancement: Seamless AJAX retry and deletion in keyword queue without page reload or jumping.
* Enhancement: Added smart scheduling tooltips and failure reason popups on queue status badges.
* Enhancement: Added keyword queue navigation callout in Content settings tab.

= 1.2.2 =
* Initial release on WordPress.org.
