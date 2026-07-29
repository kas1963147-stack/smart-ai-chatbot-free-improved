# Production Test Matrix

Minimal smoke checks to validate production readiness after deployment.

## 1) Anonymous chat (frontend)
- Start a new chat session and send a message.
- Confirm a valid reply, session creation, and no privileged tool usage.

## 2) Session token refresh
- Use an older (legacy) session token and call `GET /sessions/{id}`.
- Expect `X-Session-Token` header with a `v2:` token in response.

## 3) Admin workspace
- Send a workspace message as admin.
- Confirm a response, tool calls persisted to history, and rate-limit headers present.

## 4) Tool access policy
- As anonymous user, attempt to trigger CLI/file/integrations/security tools.
- Verify tools are not available and no execution occurs.

## 5) Permissions enforcement
- Attempt restricted REST endpoints without `manage_options`.
- Expect `403` or permission callback failure.

## 6) Rate limiting
- Exceed chat/session/workspace request limits.
- Confirm `429` responses and `X-RateLimit-*` headers where applicable.

## 7) Data retention
- Manually run the retention hook and verify old sessions/action logs are pruned:
  `do_action('swc_retention_cleanup')`.

## 8) MCP access
- As non-admin, confirm MCP tools are not loaded.
- As admin, confirm MCP tools load and work as configured.

## 9) Logging controls
- Toggle `enable_logging` and verify debug logs are suppressed or recorded accordingly.

## 10) Regression sanity
- Test a basic admin agent chat in the UI.
- Verify no PHP warnings in `debug.log`.
