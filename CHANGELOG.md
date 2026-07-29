# Changelog

All notable changes to the Smart AI Chatbot plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `findBySessionId()` method alias in ChatSession model
- Session message limit (100 messages per session)
- Rate limiting on public API endpoints (/resolve, /sessions)
- SettingsValidator service for input validation
- Constants.php with centralized magic numbers
- Secondary fallback in RAGRetriever for error resilience

### Changed
- Replaced deprecated `get_page_by_title()` with WordPress 6.2+ compatible code
- Improved `$_SERVER` sanitization with `wp_unslash()`
- SQL queries now use `$wpdb->prepare()` consistently
- Refactored `goto` statement to flag-based control flow

### Fixed
- Duplicate log statements in get_ai_response()
- WC() null check before cart operations
- Agent ID validation in ChatController

### Security
- Added rate limiting to prevent API abuse
- Improved input sanitization across endpoints

## [1.0.0] - 2026-01-24

### Added
- Initial release with multi-agent chat system
- WooCommerce integration with 22 shopping tools
- RAG-based knowledge base with vector search
- 40+ AI provider support via ProviderBridge
- Real-time streaming responses
- Analytics dashboard with cost tracking
- Proactive engagement system
- Mobile-responsive chat widget
