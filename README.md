# 🤖 VColonna AI — AI Assistant for WordPress

VColonna AI integrates artificial intelligence directly into the WordPress admin panel. It's not just a chatbot: it can perform **real actions** on your site through agentic function calling.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0-green)

---

## ✨ Features

- **AI Chat with streaming** — Floating widget on every admin page with real-time token-by-token responses, markdown rendering, and conversation history
- **Agentic function calling** — The AI can create, edit and delete posts, pages, Custom Post Types, comments, products, menu items and site settings autonomously
- **Content generation** — Metabox in the post editor to generate article drafts and SEO meta (title, description, excerpt)
- **Content analysis** — Ask the AI to analyze your existing posts and suggest improvements for SEO, readability and brand consistency
- **Knowledge base** — Upload documents (TXT) as custom context for the AI, with FULLTEXT search and automatic chunking
- **RAG (Retrieval-Augmented Generation)** — Index your site's posts, pages and CPTs so the AI knows your existing content
- **Current page context** — When chatting from the post editor, the AI automatically knows which post you're working on
- **ACF support** — Automatically discovers and fills Advanced Custom Fields with type-based validation
- **Alt text generation** — Generate AI-powered alt text for images directly in the media library
- **Multi-provider** — Supports Google Gemini, OpenAI (GPT), Anthropic Claude and Groq (free) with up-to-date model selection
- **Analytics dashboard** — Charts showing API calls over time, token consumption and provider distribution, with clickable error details
- **WooCommerce support** — Create products, view orders (tools appear only when WooCommerce is active)
- **Site context** — Configure name, sector, tone and target audience for content consistent with your brand
- **Toast notifications** — Visual feedback when the AI performs actions, with direct links to the editor
- **Rate limiting** — Configurable per-user hourly request limit
- **Auto-cleanup** — Daily cron job to automatically delete old conversations

## 🧠 Available AI Tools

The AI assistant can execute these actions on your WordPress site:

| Tool | Description |
|------|-------------|
| `create_post` | Create a new post or page |
| `update_post` | Edit an existing post or page |
| `delete_post` | Move a post to trash |
| `get_posts` | Retrieve and search posts with content preview |
| `get_media` | Browse the media library |
| `get_categories` | List all categories |
| `get_site_info` | Get site name, URL, theme, plugins, stats |
| `get_custom_post_types` | Discover all CPTs with their ACF fields |
| `create_custom_post` | Create a CPT entry and populate ACF fields |
| `update_custom_post` | Update a CPT entry and its ACF fields |
| `get_comments` | Retrieve comments with status filter |
| `moderate_comment` | Approve, spam or trash a comment |
| `reply_comment` | Reply to a comment as the current user |
| `update_site_settings` | Update site title, tagline, posts per page |
| `get_users` | List users with role filter |
| `get_menus` | List navigation menus with items |
| `add_menu_item` | Add a page or custom link to a menu |
| `get_products` | List WooCommerce products *(only if WooCommerce is active)* |
| `create_product` | Create a simple WooCommerce product *(only if WooCommerce is active)* |
| `get_orders` | List WooCommerce orders *(only if WooCommerce is active)* |

## 🔌 Supported Providers

| Provider | Models | Free Tier |
|----------|--------|-----------|
| **Google Gemini** | 2.5 Flash-Lite, 2.5 Flash, 2.5 Pro, 3 Flash, 3.1 Flash-Lite, 3.1 Pro | Up to 1,000 req/day |
| **OpenAI** | GPT-5.4 Nano/Mini/Full, GPT-4.1 Nano/Mini/Full | No free tier |
| **Anthropic Claude** | Haiku 4.5, Sonnet 4.6, Opus 4.6 | No free tier |
| **Groq** | Llama 3.3 70B, Llama 3.1 8B, GPT-OSS 120B/20B, Llama 4 Scout, Qwen3 32B | Free (rate limited) |

Get your API keys:
- **Gemini** → [Google AI Studio](https://aistudio.google.com)
- **OpenAI** → [OpenAI Platform](https://platform.openai.com/api-keys)
- **Claude** → [Anthropic Console](https://console.anthropic.com/settings/keys)
- **Groq** → [Groq Console](https://console.groq.com/keys) (free, no credit card required)

## 📦 Installation

### 1. Download and install

Clone or download this repository into your WordPress plugins directory:

```bash
cd wp-content/plugins/
git clone https://github.com/ColoVinc/vcolonna-ai-assistant.git
```

### 2. Install vendor dependencies

This repository does not include third-party CSS/JS libraries. You need to download them and place them in `assets/vendor/`:

**Bootstrap 5.3.3** (used only in plugin admin pages, not globally):
- Download from [getbootstrap.com](https://getbootstrap.com/docs/5.3/getting-started/download/)
- Place `bootstrap.min.css` and `bootstrap.bundle.min.js` in `assets/vendor/`

**Font Awesome 6.5.1 (Free):**
- Download from [fontawesome.com](https://fontawesome.com/download)
- Place `fontawesome.min.css` (the `all.min.css` renamed) in `assets/vendor/`
- Place the `webfonts/` folder in `assets/vendor/webfonts/`

**Marked.js 16.x** (markdown rendering in chat):
- Download from [cdnjs](https://cdnjs.cloudflare.com/ajax/libs/marked/16.3.0/lib/marked.umd.min.js)
- Save as `assets/vendor/marked.min.js`

**Chart.js 4.5.1** (analytics dashboard):
- Download from [jsdelivr](https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js)
- Save as `assets/vendor/chart.min.js`

Your `assets/vendor/` folder should look like this:

```
assets/vendor/
├── bootstrap.min.css
├── bootstrap.bundle.min.js
├── fontawesome.min.css
├── marked.min.js
├── chart.min.js
└── webfonts/
    ├── fa-solid-900.woff2
    ├── fa-solid-900.ttf
    ├── fa-regular-400.woff2
    ├── fa-regular-400.ttf
    ├── fa-brands-400.woff2
    └── fa-brands-400.ttf
```

### 3. Activate and configure

1. Activate the plugin from **Plugins** in WordPress admin
2. Go to **VColonna AI → Settings**
3. Select your AI provider and enter the API key
4. Optionally configure the site context (name, sector, tone, target audience)
5. Click the 🤖 robot icon in the bottom-right corner to start chatting

## 🏗️ Project Structure

```
vcolonna-ai-assistant/
├── vcolonna-ai-assistant.php      # Main plugin file, bootstrap, DB tables
├── includes/
│   ├── class-core.php             # Core singleton, hooks, auto-reindex on save
│   ├── class-api-connector.php    # Abstract base class for AI providers
│   ├── class-tools.php            # 20 tool declarations and execution engine
│   ├── class-history.php          # Conversation and message CRUD
│   ├── class-logger.php           # API call logging, stats, daily/provider aggregation
│   ├── class-knowledge.php        # Knowledge base: documents, chunking, FULLTEXT search, RAG
│   └── connectors/
│       ├── class-gemini.php       # Google Gemini (with thought signatures support)
│       ├── class-openai.php       # OpenAI GPT (base for OpenAI-compatible providers)
│       ├── class-claude.php       # Anthropic Claude
│       └── class-groq.php         # Groq (extends OpenAI connector)
├── admin/
│   ├── class-admin.php            # Settings, menu, connector factory, rate limit, alt text
│   ├── class-chat.php             # Chat widget with SSE streaming + AJAX endpoints
│   └── class-metabox.php          # Editor metabox for content/SEO generation
├── templates/
│   ├── settings-page.php          # Settings page
│   ├── chat-widget.php            # Chat widget HTML
│   ├── metabox.php                # Metabox HTML
│   ├── logs-page.php              # Logs page with Chart.js dashboard
│   └── knowledge-page.php         # Knowledge base management + RAG indexing
└── assets/
    ├── css/
    │   ├── admin.css              # Admin pages styles
    │   └── chat.css               # Chat widget styles (self-contained)
    └── js/
        ├── admin.js               # Settings, knowledge base, RAG logic
        ├── chat.js                # Chat widget with SSE streaming + markdown
        ├── metabox.js             # Metabox (Gutenberg + Classic Editor)
        ├── media-alt.js           # Alt text generation in media library
        └── logs-charts.js         # Chart.js initialization for analytics
```

## 🗄️ Database

The plugin creates 4 custom tables on activation:

| Table | Purpose |
|-------|---------|
| `wp_vcai_conversations` | Chat conversations per user |
| `wp_vcai_messages` | Individual messages (role: user/model) |
| `wp_vcai_logs` | API call logs with token counts |
| `wp_vcai_knowledge` | Knowledge base chunks with FULLTEXT index |

All tables are automatically removed when the plugin is deleted (not deactivated).

## 🔒 Security

- Nonce verification on all AJAX endpoints
- Capability checks (`manage_options` for admin settings, `edit_posts` for chat and metabox)
- Input sanitization on all user data
- Conversation ownership verification (users can only access their own conversations)
- Configurable per-user hourly rate limiting
- API keys stored in `wp_options`, never hardcoded
- Thought signatures support for Gemini 3 models

## 📋 Requirements

- WordPress 5.8+
- PHP 7.4+
- An API key from at least one supported provider

## 📄 License

This project is licensed under the [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html) license.

## 👤 Author

**Vincenzo Colonna** — [GitHub](https://github.com/ColoVinc)
