# Enterprise Chat Module

## Status

This increment delivers the secure chat foundation: normalized schema, RBAC resources, direct/group/context-capable conversation records, member-scoped conversation/message APIs, cursor-ready message reads, idempotent sends, per-member read state, user search, in-app notifications, a responsive React workspace, deep links, and visibility-aware polling.

## Deployment

1. Back up the MySQL database.
2. Apply `migrations/20260827_enterprise_chat.sql` once after the 2026 enterprise RBAC migrations.
3. Assign `chat` view/create permissions to allowed positions or users. Super Admin is seeded automatically.
4. Deploy the PHP repository and the React production build together.
5. Verify `/chat/conversations` with a JWT and refresh `/dashboard/chat` directly through the SPA rewrite.

No Redis, queue, or WebSocket service is required. The client polls only the active conversation every four seconds and pauses when the document is hidden.

## API

All routes require JWT authentication and the `chat` resource. The server derives the user ID from the token and checks active conversation membership.

- `GET /chat/conversations`
- `POST /chat/conversations` — `{type, name?, description?, member_ids, context?}`
- `GET /chat/conversations/{id}/messages?before={messageId}`
- `POST /chat/conversations/{id}/messages` — `{body, client_id, reply_to_id?}`
- `POST /chat/conversations/{id}/read`
- `GET /chat/users/search?q={query}`
- `GET /chat/notifications`

## Security

Prepared statements are used throughout. Conversation access is denied unless an active `chat_members` row exists. Direct and contextual uniqueness is database-enforced. Message retry IDs are unique per sender. Message content is rendered as React text, not HTML. API errors do not expose SQL details. Search returns only non-sensitive active-user fields.

## Current limitations / next increments

The schema reserves mentions, reactions, attachments, edits, deletion, pin/mute/archive, and administration state, but their API/UI controls are not part of this initial stable increment. Group management, notification read controls, contextual buttons in every business view, moderation/audit UI, attachment validation, and automated PHP integration tests remain to be implemented before calling the complete specification production-ready.
