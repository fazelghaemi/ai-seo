The ultimate AI-powered SEO & Content Engine for WordPress, featuring a modular architecture, AI Brain, and direct integration with Google Gemini via Cloudflare Workers.

== Description ==
Ready Studio SEO Engine is not just an SEO plugin; it's an enterprise-grade, AI-powered content and strategy platform built specifically for demanding sites like prompt galleries and digital asset libraries.
This plugin connects directly to your Google Gemini API key via a secure Cloudflare Worker, ensuring high speed and complete privacy. It bypasses the limitations of traditional SEO tools by understanding content on a semantic and even visual level.

Core Features (Nexus Core v12)
Modular Architecture: The plugin is built on a clean, object-oriented framework. The Core handles API, Data, and Settings, while all features (SEO, Content, Vision) are independent modules.
Nexus AI Brain (System Prompt): A central settings panel where you can define your site's "Knowledge Base" (brand voice, audience) and "Custom Rules" (e.g., "Always include #ReadyPrompt in the meta description").
Secure Cloudflare Worker: All API requests are proxied through your own Cloudflare Worker. Your Gemini API key never leaves your worker, ensuring 100% security on the WordPress side.
Bulk Generation: A powerful admin panel to generate SEO, content, and alt text for hundreds of posts in a queue. Features a progress bar and a detailed log console.
CPT-Aware Logic: Specialized logic for Custom Post Types. For example, it's designed to read from prompts-text meta fields (for JetEngine) and generate "Strict, Descriptive" titles instead of clickbait.

Included Modules

SEO Module:
Generates high-CTR SEO titles and meta descriptions.
Extracts a relevant Focus Keyword.
Generates 5 relevant tags.
Automatically syncs with Rank Math & Yoast fields.
Generates "Strict" descriptive titles and Latin slugs for CPTs.

Content Module:
Solves "Thin Content": A "Content Writer" button that generates a 150-word descriptive paragraph (analyzing artistic style, mood, and use cases) to be used as the main post content.
Generates context-aware Image Alt Text based on the text of the prompt.

Vision Module (Gemini Vision API):
True Visual Analysis: This module "looks" at the post's Featured Image.
It bypasses the text prompt and analyzes the actual resulting image.
Generates hyper-accurate Art Style tags (e.g., "Photorealistic, Macro, Cinematic Lighting").
Generates Visual Keywords (e.g., "gold coin, wood, treasure").
Generates the most accurate Image Alt Text possible, based on what is visually present.

== Installation ==

Cloudflare Worker:
Deploy the worker.js script (from the repo) to a new Cloudflare Worker.
In the Worker's settings, add a secret variable named GEMINI_API_KEY and paste your Google Gemini API key.
(Note: The v12 worker is "smart" and handles both text and vision requests based on the 'action_type' parameter.)

WordPress Plugin:
Clone or download the repository: git clone https://github.com/fazelghaemi/ai-seo.git ready-seo
Upload the ready-seo folder to your wp-content/plugins/ directory.
Activate the plugin through the 'Plugins' menu in WordPress.
Configuration:
Navigate to the "Ready Studio" menu in your WordPress admin dashboard.
In the "اتصال (API)" tab, enter your Cloudflare Worker URL.
(Optional) Go to the "مغز هوش مصنوعی (AI Brain)" tab to add your custom knowledge base and rules.
You are ready to go!

== Changelog ==

= 12.0.0 (Nexus Core) =
MAJOR: Complete architectural refactor into a modular (Core + Modules) framework.
NEW: Added Vision Module (class-rs-module-vision.php) for Gemini Vision analysis.
NEW: Upgraded Cloudflare Worker (worker.js) to handle both 'text' and 'vision' action_type.
NEW: Added Core Loader (class-rs-core-loader.php) to manage all dependencies and module loading.
NEW: Added Core Metabox (class-rs-core-metabox.php) to act as a shell for modules.
NEW: All JS/CSS assets split into core (style-core.css, admin-core.js) and module-specific files.
FIX: Ensured all PHP classes are final and syntactically correct. No more Parse Errors.

= 10.0.0 =
FIX: Definitive fix for the "Unclosed '{'" Parse Error by removing a duplicate function.

= 8.0.0 =
FIX: Hardened code against Parse Errors by replacing ?? with isset() for older PHP.

= 6.0.0 =
NEW: Added "Content Writer" to solve Thin Content.
NEW: Added automated Image Alt Text generation.
NEW: Metabox UI updated with tabs.

= 4.0.0 =
NEW: Added "Bulk Generator" admin page with queuing.
NEW: Added "AI Brain" (Knowledge Base & Custom Prompts) settings page.
NEW: Added CPT-specific logic for 'prompts'.

= 1.0.0 =
Initial release.