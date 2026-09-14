# WordPress.org Review Remaining Issues Report (V2)

Based on the latest review email and the current state of the codebase, here are the outstanding issues that the WordPress Plugin Review Team rejected. **Even though you defended some of these in your previous email, the reviewer has rejected the submission with `CHANGESNOTMADE`**, meaning we MUST fix them in the code to get approved.

## 1. Tested Up To Value is Out of Date, Invalid, or Missing
**Issue**: Your `readme.txt` contains `Tested up to: 6.9`, but your main plugin file `agentflow-ai.php` is missing this header entirely.
**Location**: `agentflow-ai.php`
**Action Required**: Add `* Tested up to: 6.9` to the header comment block of `agentflow-ai.php`.

## 2. Out of Date Libraries (PHPUnit)
**Issue**: The review flagged `phpunit/phpunit` as outdated (10.5.63 ~ 13.1.10). Even if it's not in your production `composer.json`, if the `vendor/phpunit` or `tests/` folder is included in the ZIP file you uploaded, the scanner will flag it.
**Action Required**: Ensure that the `vendor/` directory is either rebuilt without `require-dev` packages (`composer install --no-dev`), and ensure the `tests/` directory is completely excluded from the final ZIP you upload.

## 3. Using CURL Instead of HTTP API
**Issue**: The review flagged `curl_multi_exec` and `curl_init`. You defended this in your email saying it's for SSE streaming, but the reviewer's response implies they do not accept this excuse (`CHANGESNOTMADE`).
**Location**: Likely inside your AI Provider API classes.
**Action Required**: You must replace `curl_multi_exec` with the native WordPress HTTP API (`wp_remote_get` / `wp_remote_post`). If streaming is absolutely required, you might need to use `wp_remote_request` with the `'stream' => true` argument, or disable streaming for the free WordPress.org version.

## 4. Determine files and directories locations correctly (WP_CONTENT_DIR)
**Issue**: The code is still using `\WP_CONTENT_DIR` to define custom directories instead of using the recommended `wp_upload_dir()`.
**Locations**:
- `src/Knowledge/KnowledgeConfig.php:44` (`\WP_CONTENT_DIR . '/swc-knowledge'`)
- `src/Api/Controllers/McpController.php:227` (`\WP_CONTENT_DIR . '/debug.log'`)
- `includes/agent/class-tool-registry.php:108` (`\WP_CONTENT_DIR . '/swc-files/'`)
**Action Required**: Replace `\WP_CONTENT_DIR` with `wp_upload_dir()['basedir'] . '/smart-ai-chatbot'`.

## 5. Saving data in the plugin folder (__DIR__)
**Issue**: Logs and registries are being written directly into the plugin's own directory using `__DIR__`. Plugin folders must be strictly read-only.
**Locations**:
- `toolkits/toolkits/WooCommerce/ProductSearchTool.php:160` (`dirname(__DIR__, 2) . '/debug_tools.txt'`)
- `toolkits/toolkits/WooCommerce/ProductManageTool.php:177` (`__DIR__ . '/../../logs/execution_trace.log'`)
- `src/Agent/AgentRegistry.php:346` (Saving JSON registry to disk)
**Action Required**: Move all file writing operations to the WordPress uploads directory using `wp_upload_dir()`.

## 6. Sanitization & Escaping ($_SERVER)
**Issue**: Raw access to `$_SERVER` variables without `sanitize_text_field()`. Even if it's a server variable, WordPress requires it to be sanitized.
**Locations**:
- `toolkits/toolkits/WordPress/SiteHealthTool.php:239` (`$_SERVER['SERVER_PROTOCOL']`, `$_SERVER['HTTP_HOST']`)
- `src/Services/ServerInput.php:85` (`$_SERVER['CONTENT_TYPE']`)
- `src/Api/Controllers/ToolsController.php:634` (`$_SERVER['HTTP_HOST']` and `$_SERVER['REQUEST_URI']`)
**Action Required**: Wrap every `$_SERVER[...]` call with `sanitize_text_field(wp_unslash(...))`.

## 7. Do not use HEREDOC syntax
**Issue**: HEREDOC syntax (`<<<INSTRUCTIONS`) is strictly banned because it breaks automated security scanners.
**Locations**:
- `src/Workflows/Supervisor/SupervisorWorkflow.php:152`
- Check `scratch/backup_fixes/ContentWriterAgent.php` or `ResearchAgent.php` if they are included in the ZIP.
**Action Required**: Rewrite these blocks using standard double quotes (`"..."`) or single quotes with concatenation.

## 8. Internationalization: Text domain mismatch
**Issue**: The text domain must exactly match your plugin slug (`smart-ai-chatbot`). You currently have 18 elements using `swc-chatbot`.
**Action Required**: Search the entire project for `'swc-chatbot'` and replace it with `'agentflow-ai'`.

## 9. Nonces and User Permissions
**Issue**: Missing nonce check in OAuth callback.
**Location**: `src/Api/Controllers/ToolsController.php:610` (`handleGoogleCallback()`)
**Action Required**: Add a nonce check (`wp_verify_nonce($_GET['state'], 'google_oauth_state')`) to the callback.

## Conclusion
The reviewer's `CHANGESNOTMADE` flag means they require **code changes** for all these items, regardless of the explanations provided in the email reply. Do not upload the plugin again until every single item on this list has been rewritten to comply with the WordPress.org standards.
