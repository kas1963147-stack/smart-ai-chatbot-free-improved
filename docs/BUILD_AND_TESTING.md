# Building and Testing the Plugin

This guide covers two methods for testing the Smart AI Chatbot plugin:

1. **ZIP Compilation** - For distribution and remote testing
2. **Direct Link (Symlink)** - For rapid local development

---

## Method 1: ZIP Compilation

### Prerequisites

- Node.js 18+ and npm
- PHP 8.0+ and Composer
- zip command (included on macOS/Linux)

### Quick Build

Run the build script from the plugin root:

```bash
cd QuarksolAIAgent
./build_plugin.sh
```

This creates `smart-ai-chatbot-full.zip` (~2MB).

### Manual Build Steps

If you need more control:

```bash
# 1. Install dependencies and build assets
npm install
npm run build

# 2. Install PHP dependencies (production only)
composer install --no-dev --optimize-autoloader

# 3. Create zip (excluding dev files)
zip -r smart-ai-chatbot.zip . \
    -x "node_modules/*" \
    -x ".git/*" \
    -x "*.zip" \
    -x "tests/*" \
    -x "src/*" \
    -x "skills-reference/*" \
    -x "knowledge-samples/*" \
    -x "provider-source/*" \
    -x ".DS_Store"
```

### Installing the ZIP

1. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
2. Select `smart-ai-chatbot-full.zip`
3. Click **Install Now** → **Activate**

---

## Method 2: Direct Link (Symlink)

This method links your development folder directly to WordPress - **changes are instant**.

### Setup (One-Time)

```bash
# Navigate to your WordPress plugins directory
cd "/Users/usamaelmolla/Documents/01)Projects_by_Usama/WordPress Projects/Wordpress Sites/app/public/wp-content/plugins"

# Create symlink to your dev folder
ln -s "/Users/usamaelmolla/Documents/01)Projects_by_Usama/WordPress Projects/QuarksolAIAgent" smart-ai-chatbot
```

### Verify Symlink

```bash
ls -la wp-content/plugins/ | grep smart
# Should show: smart-ai-chatbot -> /path/to/QuarksolAIAgent
```

### Development Workflow

With the symlink in place:

1. **PHP changes** - Instant (just refresh the browser)
2. **React/JS changes** - Run `npm run dev` for hot reload:

```bash
cd QuarksolAIAgent
npm run dev   # Watches for changes and rebuilds
```

3. **Build production assets**:

```bash
npm run build
```

---

## Quick Reference

| Task | Command |
|------|---------|
| Build production zip | `./build_plugin.sh` |
| Watch for JS changes | `npm run dev` |
| Build assets once | `npm run build` |
| Install PHP deps | `composer install` |
| Run E2E tests | `npm run test:e2e` |

---

## Test Site

Local development site: **http://quarksol1.local**

- Plugin page: http://quarksol1.local/wp-admin/admin.php?page=swc-chatbot
