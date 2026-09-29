# AutoBlogix WordPress API

Contract between the AutoBlogix WordPress plugin and the AutoBlogix server.
This document is the complete reference for building the plugin side: every
endpoint, the signing algorithm with a worked example, all error codes, and a
ready-to-run signing script.

- **Base URL:** `https://<your-autoblogix-host>` (routes live under `/api`,
  so the full path always starts with `/api/v1/wordpress/…`)
- **Method:** `POST` for every endpoint (GET returns 405)
- **Content type:** `application/json` (the raw bytes are signed — never
  re-encode the body after signing)
- **Transport:** HTTPS in production

---

## 1. Authentication (both directions)

Every request carries four headers:

| Header | Format | Example |
| --- | --- | --- |
| `X-ABX-Key` | `abx_` + 32 lowercase alphanumerics | `abx_0123456789abcdef0123456789abcdef` |
| `X-ABX-Timestamp` | Unix time in **seconds** (10 digits) | `1790000000` |
| `X-ABX-Nonce` | 16–128 chars of `[A-Za-z0-9_-]`, unique per request | `f4d0a1b2c3d4e5f6a7b8c9d0e1f2a3b4` |
| `X-ABX-Signature` | lowercase hex HMAC-SHA256 (64 chars) | `47735b96…` |

### Canonical string and signature

```
payload  =  METHOD  "\n"  PATH  "\n"  TIMESTAMP  "\n"  NONCE  "\n"  HEX(SHA256(raw_body))
signature = HEX(HMAC_SHA256(secret, payload))
```

- `METHOD` — uppercase (`POST`)
- `PATH` — the full request path **including `/api`**, no query string
  (`/api/v1/wordpress/connect`)
- `raw_body` — the exact bytes in the request body
- `secret` — the plaintext API secret shown **once** when the credentials were
  created in AutoBlogix (starts with `abxs_`); it is never transmitted

Server-side checks, in order:

1. All four headers present → else `401 missing_headers`
2. Nonce format valid → else `401 invalid_nonce`
3. Key exists (and is not revoked) → else `401 unknown_key`
4. `|now − timestamp| ≤ 300 s` → else `401 stale_timestamp`
5. Signature recomputed and compared with `hash_equals` → else
   `401 invalid_signature` (also logged against the site)
6. Nonce unseen within the last 600 s (atomic single-use) → else
   `401 replayed_nonce`

### Worked example

Request body:

```json
{"wordpress_version":"6.7.1","plugin_version":"1.0.0"}
```

```
sha256(body) = e5857a84aa1c9ef456567fc8dabfa260615c588fb5f56f79757957bf8fd7659e

canonical payload ("\n" = newline):
POST
/api/v1/wordpress/connect
1790000000
f4d0a1b2c3d4e5f6a7b8c9d0e1f2a3b4
e5857a84aa1c9ef456567fc8dabfa260615c588fb5f56f79757957bf8fd7659e

secret   = abxs_WorkedExampleSecret0123456789abcdefghij
signature = 47735b965a1aff867324993d52ddee929d34c780f6afbf4767453636b589ac83
```

### Signing helper (plugin side, PHP)

```php
/**
 * @return array<string, string> Headers to add to the request.
 */
function abx_sign_request(string $method, string $path, string $body, string $key, string $secret): array
{
    $timestamp = (string) time();
    $nonce     = bin2hex(random_bytes(16)); // 32 hex chars, single use

    $payload = implode("\n", [
        strtoupper($method),
        $path,
        $timestamp,
        $nonce,
        hash('sha256', $body),
    ]);

    return [
        'X-ABX-Key'        => $key,
        'X-ABX-Timestamp'  => $timestamp,
        'X-ABX-Nonce'      => $nonce,
        'X-ABX-Signature'  => hash_hmac('sha256', $payload, $secret),
    ];
}
```

### Verifying requests AutoBlogix sends to WordPress

The identical algorithm protects AutoBlogix → plugin traffic (outbound
endpoints such as publish are documented in **§7** — verify them with the
same canonical string using the full request path, including any
subdirectory of the site URL). When a
request arrives with `X-ABX-*` headers, the plugin must:

1. Reject if any header is missing, the timestamp is outside ±300 s, or the
   nonce was seen before (store nonces transiently for 10 minutes).
2. Recompute `payload` with the **same canonical string** using the stored
   secret and the raw `php://input` body.
3. Compare with `hash_equals` before running any handler — reject with
   `401` and a JSON `{ "message", "error" }` envelope otherwise.

Never log or echo the secret or signature.

### Retries

Always re-sign with a **fresh timestamp and nonce** on retry. `replayed_nonce`
means the server already received that exact signed request — for
`publish-result` the outcome is recorded exactly once
(`already_recorded: true`), and the connection endpoints are naturally
idempotent, so retrying with new headers is always safe. Back off on `429`.

---

## 2. Endpoints

Success envelope: `{ "data": { … } }`.
Error envelope: `{ "message": "…", "error": "<code>" }` (plus `errors` for
`validation_failed`).

### POST `/api/v1/wordpress/connect`

Full handshake when the plugin first validates the saved credentials (or is
reconnected by the user).

Request:

```json
{ "wordpress_version": "6.7.1", "plugin_version": "1.0.0" }
```

- `wordpress_version` — required, `[A-Za-z0-9._-]`, max 32
- `plugin_version` — required, `[A-Za-z0-9._-]`, max 32

Response `200`:

```json
{
  "data": {
    "website_id": 1,
    "name": "My Blog",
    "status": "connected",
    "timezone": "UTC",
    "server_time": "2026-09-29T10:00:00+00:00"
  }
}
```

Side effects: website status → `connected`, versions stored,
`last_connected_at`/`last_sync_at` set, connection log written.

### POST `/api/v1/wordpress/verify`

Read-only credential check (plugin "Test connection" button — do **not**
poll this endpoint).

Request: `{}` · Response `200`:

```json
{
  "data": {
    "website_id": 1,
    "name": "My Blog",
    "status": "pending",
    "timezone": "UTC",
    "wordpress_version": "6.7.1",
    "plugin_version": "1.0.0",
    "server_time": "2026-09-29T10:00:00+00:00"
  }
}
```

Side effects: only a connection-log entry; status and timestamps unchanged.

### POST `/api/v1/wordpress/disconnect`

User removed the credentials in WordPress.

Request: `{}` · Response `200`:

```json
{ "data": { "website_id": 1, "status": "disconnected", "server_time": "…" } }
```

Side effects: status → `disconnected`, connection log written.

### POST `/api/v1/wordpress/heartbeat`

Keep-alive (recommended every 5 minutes). Refreshes versions and
`last_sync_at`; if the site was `pending`/`disconnected`/`error` it becomes
`connected` again. Routine heartbeats write no log — only status changes do.

Request (same shape as connect): `{ "wordpress_version": "6.7.1", "plugin_version": "1.0.0" }`

Response `200`:

```json
{
  "data": {
    "website_id": 1,
    "status": "connected",
    "last_sync_at": "2026-09-29T10:05:00+00:00",
    "server_time": "…"
  }
}
```

### POST `/api/v1/wordpress/publish-result`

Plugin reports the outcome of a publish AutoBlogix asked for. Carries the
`idempotency_key` AutoBlogix generated for that attempt.

Request:

```json
{
  "idempotency_key": "0f4a8a9e-6b0a-4c0e-9a5c-2f5e3d1b7a11",
  "success": true,
  "wordpress_post_id": 123,
  "url": "https://blog.example.com/hello-world"
}
```

- `idempotency_key` — required UUID (echo back exactly)
- `success` — required boolean
- when `success` is `true`: `wordpress_post_id` (integer ≥ 1) and `url` (valid
  URL, max 500) are **required**
- when `success` is `false`: `message` (string, max 500) is **required**

Response `200`:

```json
{
  "data": {
    "post_id": 7,
    "status": "published",
    "wordpress_post_id": 123,
    "url": "https://blog.example.com/hello-world",
    "failure_reason": null,
    "already_recorded": false,
    "server_time": "…"
  }
}
```

Duplicate delivery returns the same shape with `"already_recorded": true` and
changes **nothing** (the post row is locked; the final state wins). A key
that belongs to another site — or to no post — returns
`422 unknown_idempotency_key`; call AutoBlogix only with keys it issued.

---

## 3. Error codes

| HTTP | `error` | Meaning | Fix |
| --- | --- | --- | --- |
| 401 | `missing_headers` | One of the four `X-ABX-*` headers absent | Send all headers |
| 401 | `invalid_nonce` | Nonce not 16–128 chars of `[A-Za-z0-9_-]` | Use `bin2hex(random_bytes(16))` |
| 401 | `unknown_key` | Key unknown or revoked | Re-create credentials in AutoBlogix |
| 401 | `stale_timestamp` | Clock off by more than 300 s | Sync server clock |
| 401 | `invalid_signature` | Wrong secret, tampered body/path/method, or wrong canonical string | Verify signing code against §1 |
| 401 | `replayed_nonce` | This exact signed request was already processed | Re-sign with fresh timestamp/nonce |
| 422 | `validation_failed` | Body failed field validation (`errors` object included) | Match the documented payload |
| 422 | `unknown_idempotency_key` | No post for that key under this site | Only report keys AutoBlogix issued |
| 429 | *(message only)* | Named rate limit exceeded | Back off and retry later |
| 405/404 | *(framework)* | Wrong method or path | Use POST on the documented paths |

## 4. Rate limits

| Endpoint(s) | Limiter | Limit |
| --- | --- | --- |
| `connect` | per key / per IP | 10 / min each |
| `verify` | per key / per IP | 30 / min each |
| `disconnect`, `heartbeat` | per key / per IP | 60 / min · 120 / min |
| `publish-result` | per key / per IP | 60 / min each |

## 5. Website status lifecycle

`pending → connected` (connect/heartbeat) · `connected → disconnected`
(disconnect) · any → `connected` (heartbeat) · `error` is reserved for failed
outbound connection tests (Phase 5). Only `connected` sites are in sync.

---

## 6. Signed request script (curl)

Save as `sign-request.php`, then run it to print ready-to-send headers:

```php
<?php
// php sign-request.php <path> <key> <secret> '<json body>'
[, $path, $key, $secret, $body] = $argv + [null, null, null, null, null];
$body = $body ?? '{}';

$timestamp = (string) time();
$nonce     = bin2hex(random_bytes(16));
$payload   = implode("\n", ['POST', $path, $timestamp, $nonce, hash('sha256', $body)]);
$signature = hash_hmac('sha256', $payload, $secret);

echo "curl -X POST https://YOUR-HOST{$path} \\\n",
     "  -H 'Content-Type: application/json' \\\n",
     "  -H 'X-ABX-Key: {$key}' \\\n",
     "  -H 'X-ABX-Timestamp: {$timestamp}' \\\n",
     "  -H 'X-ABX-Nonce: {$nonce}' \\\n",
     "  -H 'X-ABX-Signature: {$signature}' \\\n",
     "  --data '{$body}'\n";
```

Example:

```
php sign-request.php /api/v1/wordpress/connect abx_… abxs_… \
  '{"wordpress_version":"6.7.1","plugin_version":"1.0.0"}'
```

---

## 7. Outbound: POST publish (AutoBlogix → WordPress)

The one endpoint AutoBlogix calls on the plugin. Added with Phase 5; the
plugin implements it as a WordPress REST route under the `autoblogix/v1`
namespace.

- **URL:** `https://<site>/wp-json/autoblogix/v1/publish` — for
  subdirectory installs the site URL already contains the path, so the full
  request path is e.g. `/blog/wp-json/autoblogix/v1/publish`
- **Signed path:** the full request path **including the subdirectory**,
  no query string — recompute with `REQUEST_URI`'s path component
- **Headers:** the four `X-ABX-*` headers from §1, fresh timestamp/nonce on
  every attempt (retries included), `Content-Type: application/json`

### Request

```json
{
  "idempotency_key": "0f4a8a9e-6b0a-4c0e-9a5c-2f5e3d1b7a11",
  "post_id": 7,
  "title": "Hello world",
  "slug": "hello-world",
  "content": "<p>Post body HTML…</p>",
  "excerpt": "Short summary",
  "category": "News",
  "tags": ["launch", "tutorial"],
  "keywords": ["weekly planner"],
  "meta_description": "Shown by search engines"
}
```

- `idempotency_key`, `post_id`, `title`, `slug` — always present
- `content`, `excerpt`, `category`, `tags`, `keywords`,
  `meta_description` — omitted when empty (never sent as `null`)
- The plugin creates the post **published** immediately (AutoBlogix only
  calls this endpoint once the schedule fired or the user clicked
  *Publish now*).

### Response

Success `200`:

```json
{ "success": true, "wordpress_post_id": 123, "url": "https://blog.example.com/hello-world" }
```

Failure (any status, `200` or `4xx`):

```json
{ "success": false, "code": "permission_denied", "message": "Friendly, user-visible reason" }
```

`message` is shown to the AutoBlogix user — keep it friendly; put detail in
`code`.

### Idempotency (required)

Treat `idempotency_key` as the dedupe token: if a post for that key already
exists, return the **same** success response instead of creating a second
post. AutoBlogix reuses one key per post across retries, so a retry after a
lost response can never duplicate content. The plugin may additionally call
`/api/v1/wordpress/publish-result` (§2) with the same key to re-report —
outcomes are recorded exactly once either way.

### How AutoBlogix interprets the reply

| Outcome | User sees (`failure_reason`) |
| --- | --- |
| connection failure / timeout | Could not reach \<host\> — site offline or plugin inactive |
| 401 / 403 | WordPress rejected the API credentials — rotate and update the plugin |
| 404 / 405 | The publish endpoint was not found — plugin not installed/active |
| 429 | The website is rate limiting AutoBlogix — try again in a minute |
| 5xx | The website returned a server error (HTTP nnn) |
| 2xx, body not valid success JSON | The website returned an unexpected response |
| `success: false` | the plugin's `message` (or its `code`) |

Timeout: `WORDPRESS_API_TIMEOUT` (default 30 s). One HTTP attempt per queue
job; retrying is a manual action that reuses the same idempotency key.
Technical detail (status, body snippet) is stored in `publishing_logs`, never
shown as the primary message.
