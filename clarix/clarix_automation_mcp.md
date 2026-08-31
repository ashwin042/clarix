# Clarix — MCP, Plugins & Automation

**Handoff document.** Everything built on the *MCP & Plugins* and *Scheduled Tasks*
pages, the Telegram integration behind them, and the configuration all of it needs.

Written so that another engineer or AI agent can pick this up without reading the
whole codebase first. Last verified against `main` @ `3dd9616` on 2026-08-22.

---

## 0. The one thing to understand first

**Almost none of this is live.** The plugin library and the automations page are
*presentational* — deliberately, and documented as such in the code. There is no
trigger engine, no OAuth, no MCP server, and nothing to persist.

There are exactly **two working integrations**, and they are both Telegram
account linking — binding a `chat_id` to a Clarix user so an external bot can
act as that person. Both are real, tested and deployed. **They are separate
bots and share no code, no table and no key.**

| Thing | Status |
|---|---|
| Plugin library (11 cards) | **Mock**, except the two below |
| Scheduled Tasks (6 automations) | **Mock.** Dimmed preview, every control disabled |
| AXOKAI account linking (§5–6) | **Live.** Identity only — it never writes |
| **Task Bot** (§7) | **Live.** Files tasks from Telegram. **It writes** |
| The bots themselves | **External.** Not in this repo — see §8 |
| MCP protocol support | **Not built.** The page is named for a direction, not a feature |

The Task Bot is the one that writes, so it carries everything the other does
not: an acting-person middleware, tenant context around the whole request,
idempotency on the attach, and an admin branch that lets somebody with no unit
of their own say which unit the work is for. §7 is the section to read before
touching any of it.

Do not "fix" the disabled inputs by wiring them up. They are placeholders for a
data shape, and the class docblocks say so.

---

## 1. Naming — read this before writing any user-facing string

Four names are in play. Getting them wrong is the most likely mistake here.

| Name | What it is | May a user see it? |
|---|---|---|
| **AXOKAI** | The assistant persona, used across the whole AI section | **Yes — this is the only correct name in UI copy** |
| **Hermes** | Internal codename for the *first* bot integration (§5–6) | **No. Never.** |
| **Jarvis** | That bot's actual Telegram handle, `@Jarvis_clarix_assistant_bot` | Unavoidably — see below |
| **Task Bot** | The §7 integration, in UI copy and in the plugin library | **Yes** — it is the plugin card's own name |

"Task Bot" is not a codename and needs no hiding — it is what the card is
called. Its handle is `@clarix_task_bot`, which mercifully matches. `n8n` is an
implementation detail and appears in class names, env vars, routes and headers
but **not** in UI copy; a user is told they are connecting the Task Bot, not a
workflow engine.

`Hermes` survives only in places no user reads:

- `EnsureHermesRequest` (class name)
- `X-Hermes-Key`, `X-Hermes-Timestamp`, `X-Hermes-Signature` (HTTP headers)
- `HERMES_API_KEY`, `HERMES_SIGNING_SECRET` (env vars)
- `config('services.hermes.*')` (config keys)
- `hermes` (middleware alias), `hermes-verify` / `hermes-resolve` (rate limiters)

These are a live wire contract with the deployed bot and its Railway config.
**Renaming them requires a coordinated two-sided deploy** — every request 401s
if the two sides disagree. Leave them alone.

A test enforces the UI rule: `ConnectTelegramTest::test_no_view_says_hermes`
walks every `.blade.php` under `resources/views` and fails on any occurrence of
"hermes" (case-insensitive). The bot handle is stripped before the check,
because BotFather owns that string, not this repo.

**Known wart:** the handle reads as "Jarvis" in the `t.me` deep link and inside
Telegram, so the app says AXOKAI and the bot the user lands on says Jarvis.
BotFather refused the rename. The bot's own `/start` message is where this could
be reconciled, and that is bot-side config.

---

## 2. File map

Everything relevant, grouped by role.

### Pages (Livewire full-page components)

| File | Purpose |
|---|---|
| `app/Livewire/AI/McpPlugins.php` | The plugin library. Holds `plugins()`, `brand()`, `CATEGORY_TINT` |
| `resources/views/livewire/ai/mcp-plugins.blade.php` | Card grid, accordion, per-card panel |
| `app/Livewire/AI/ScheduledTasks.php` | Automation previews. Holds `AUTOMATIONS`, `TRIGGER_TINT` |
| `resources/views/livewire/ai/scheduled-tasks.blade.php` | Node-graph cards |

### The live Telegram integration (AXOKAI / Hermes)

The **Task Bot** is a second, separate integration with its own file map — see
**§7.1**. Nothing below is shared with it.

| File | Purpose |
|---|---|
| `app/Livewire/Profile/ConnectTelegram.php` | The connect card component (mint / show / disconnect) |
| `resources/views/livewire/profile/connect-telegram.blade.php` | Its four render states |
| `app/Services/TelegramLinkService.php` | Issue, verify, resolve, unlink. **The core.** |
| `app/Http/Middleware/EnsureHermesRequest.php` | Bot authentication (shared key + HMAC) |
| `app/Http/Controllers/Api/TelegramLinkController.php` | The two bot endpoints |
| `app/Http/Requests/Api/VerifyTelegramLinkRequest.php` | Shape validation for `/verify` |
| `app/Http/Requests/Api/ResolveTelegramChatRequest.php` | Shape validation for `/resolve` |
| `app/Http/Resources/TelegramIdentityResource.php` | The identity envelope returned to the bot |
| `app/Exceptions/TelegramLinkException.php` | The two refusal shapes |
| `database/migrations/2026_08_30_000001_add_telegram_link_columns_to_users_table.php` | The four columns |

### Wiring

| File | What it contributes |
|---|---|
| `routes/web.php` (~L182) | `/ai/mcp` and `/ai/scheduled-tasks` behind `plan:automation` |
| `routes/api.php` (~L69) | `/api/v1/telegram/verify` and `/resolve` behind `hermes` |
| `bootstrap/app.php` | Middleware aliases: `plan`, `hermes`, `ability`, `permission` |
| `app/Providers/AppServiceProvider.php` | Rate limiters `hermes-verify`, `hermes-resolve` |
| `config/services.php` (~L96) | The `hermes` config block |
| `config/plans.php` | `automation => pro` — the gate everything here sits behind |
| `resources/views/layouts/app.blade.php` (~L310) | Sidebar entries, plan-filtered |

### Supporting layers (not owned by this feature, but it depends on them)

| File | Role |
|---|---|
| `app/Services/PlanFeatures.php` | "Did this agency buy it?" |
| `app/Http/Middleware/EnsurePlanIncludes.php` | Route-level plan gate, aborts 402 |
| `app/Livewire/Traits/RequiresPlan.php` | Component-level repeat of the same check |
| `app/Services/TenantContext.php` | Organization scoping — **critical here**, see §5.4 |
| `app/Models/User.php` | `hasLinkedTelegram()`, `planAllows()`, `hasPermission()` |

---

## 3. The MCP & Plugins page

Route: `GET /ai/mcp` → `App\Livewire\AI\McpPlugins` → layout `layouts.app`.

### Structure

1. An MCP explainer card at the top ("Coming soon" pill, link to the Chatbot).
2. A grid of 10 plugin cards, `sm:grid-cols-2 lg:grid-cols-3`, `items-start`.

### The accordion

One `x-data="{ openPlugin: null }"` lives on **the grid**, not on each card.
A card is open when `openPlugin === $loop->index`; clicking the open one shuts it.
This is why opening one closes the rest without any card knowing about its
neighbours.

`McpPluginsTest::test_the_plugin_grid_holds_one_shared_open_state` asserts there
is exactly **one** `x-data` in the rendered HTML. A stray `x-data` on a card
would silently restore multi-open behaviour, so that test is the guard.

`items-start` on the grid matters: without it, expanding one card stretches the
whole row and leaves neighbours with dead space.

### The plugin data shape

`McpPlugins::plugins()` returns an array of:

```php
[
    'name'     => 'Slack',                 // also the key brand() looks up
    'category' => 'Communication',         // must exist in CATEGORY_TINT
    'colour'   => '#4A154B',               // brand hex, used for icon + 12% tint bg
    'blurb'    => 'Get task updates and alerts in Slack',
    'fields'   => [                        // [label, placeholder, input type]
        ['Workspace URL', 'https://your-agency.slack.com', 'url'],
    ],
    'logo'     => 'M5.04 15.17a2.53...',   // single SVG path, 24x24 viewBox
]
```

Plus two optional keys, carried by the live entries only:

```php
'connect'   => true,                        // LIVE — render the real component
'component' => 'profile.connect-telegram',  // which component to mount
```

`component` rather than a branch on `name`, because the display name is copy and
copy changes. `McpPluginsTest::test_every_live_plugin_names_a_component_the_view_can_mount`
asserts every live entry names a component the view actually has a branch for —
and that a *non*-live entry carries no `component` key at all.

### The two panel branches

```blade
@php $connect = ($plugin['connect'] ?? false) ? ($plugin['component'] ?? null) : null; @endphp

@if ($connect === 'profile.connect-telegram')
    <livewire:profile.connect-telegram wire:key="connect-telegram" />
@elseif ($connect === 'profile.connect-task-bot')
    <livewire:profile.connect-task-bot wire:key="connect-task-bot" />
@else
    {{-- disabled fields + amber "in development" notice + dead Save button --}}
@endif
```

The flag lives on the data, not as `$plugin['name'] === 'Telegram'` in the view,
so a live integration drops its mock fields without touching Blade.

**Written out as a branch per component rather than as `<livewire:dynamic-component>`**,
so the set of mountable components is fixed in the template and a stray
`component` key in the library cannot mount something arbitrary.

The two cards are deliberately independent — connecting one says nothing about
the other, because they are two different bots.

`McpPluginsTest::test_only_the_live_plugins_drop_their_save_button` asserts
`$live === 2`, written out rather than derived, **so that marking a third card
live is a decision rather than an accident.** It is designed to fail then — that
is the moment to revisit the surrounding copy, not a bug.

### Current roster

| # | Name | Category | Live? |
|---|---|---|---|
| 1 | Slack | Communication | mock |
| 2 | WhatsApp | Communication | mock |
| 3 | **Telegram** | Communication | **LIVE** — `profile.connect-telegram` |
| 4 | **Task Bot** | Productivity | **LIVE** — `profile.connect-task-bot` |
| 5 | Gmail | Communication | mock |
| 6 | Google Sheets | Data | mock |
| 7 | Google Drive | Storage | mock |
| 8 | Cloudflare R2 | Storage | mock |
| 9 | Notion | Productivity | mock |
| 10 | Zoom | Communication | mock |
| 11 | Stripe | Payments | mock |

**Task Bot is filed under Productivity, not Communication, and carries its own
violet mark rather than the Telegram one** — it is a second bot with a different
job, and giving it Telegram's badge would read as a second way to configure the
card above it.

Categories are `Communication`, `Data`, `Storage`, `Productivity`, `Payments`.
`McpPluginsTest::test_every_plugin_in_the_library_has_a_categorised_tint` fails
if you add a plugin in a category with no tint.

### `brand()` is a cross-page contract

`McpPlugins::brand(string $name): ?array` returns `['name', 'colour', 'logo']` or
`null`. **Scheduled Tasks draws its integration marks through this**, so the two
pages cannot drift to different logos for the same product.

`ScheduledTasksTest` has a test whose entire purpose is that a typo in an
automation's integration name fails there. If you rename a plugin, check
`ScheduledTasks::AUTOMATIONS` for references.

---

## 4. The Scheduled Tasks page

Route: `GET /ai/scheduled-tasks` → `App\Livewire\AI\ScheduledTasks`.

Six preview automations, each a **trigger → integrations → output** chain drawn
as a small node graph. Everything interactive is disabled: the create button and
each card's enable/disable switch.

The previews are drawn from work Clarix already tracks (completions, credit
allocations, stalled tasks, delivery deadlines) so they read as a roadmap rather
than filler. **When a real engine lands, `AUTOMATIONS` is the only thing that
goes.**

The six, as currently specified:

| Trigger kind | Trigger | Produces | Integrations |
|---|---|---|---|
| `done` | Task Completed | Deliver | WhatsApp, Gmail |
| `schedule` | Weekly (Every Monday) | Report | Slack, Gmail |
| `stall` | Task Stalled 48hrs | Notify | Slack, Gmail |
| `flag` | Task Flagged by AI | Alert | WhatsApp, Slack |
| `schedule` | Monthly (1st of month) | Archive | Google Drive, Cloudflare R2 |
| `chat` | Task Update via Chat | Upload | MCP |

Trigger tints carry meaning: `done` emerald, `schedule` sky, `stall` amber,
`flag` rose, `chat` violet.

Two integration-drawing details:

- `INK_ON_DARK` overrides Slack to `#E9E4EC` and Notion to `#E8E8E8`, because
  their true brand colours are too dark for the graph's near-black node circles.
  The chip below the flow still shows the true colour on its light background.
- `MCP_BRAND` is a local constant — MCP itself is not in the plugin library, so
  `integration('MCP')` short-circuits before `brand()`.

An unknown integration name degrades to a grey `#6B7280` mark with an empty logo
path rather than throwing.

---

## 5. Telegram linking — the live flow, end to end

### 5.1 The user's journey

1. User opens **MCP & Plugins**, expands the **Telegram** card.
2. Clicks **Generate code** → `ConnectTelegram::generate()`.
3. An 8-character code appears, valid **15 minutes**, plus an "Open Telegram"
   deep link: `https://t.me/Jarvis_clarix_assistant_bot?start={CODE}`.
4. User sends the code to the bot **in a direct message** (the card says so
   explicitly — in a group, everyone could read it).
5. The bot calls `POST /api/v1/telegram/verify` with the code and its `chat_id`.
6. Clarix binds the chat to the user, **burns the code**, returns the identity.
7. The card is polling `wire:poll.5s` while a code is outstanding, so it flips
   to "Connected" by itself, then stops polling.
8. On every later message, the bot calls `POST /api/v1/telegram/resolve` with
   just the `chat_id` to find out who it is talking to.

### 5.2 The card's four states

`ConnectTelegram` renders exactly one of these, and only one is ever on screen:

| State | Condition | Shows |
|---|---|---|
| Locked | `! $planAllows` | Blurb + "Upgrade to Pro" (see §10 — unreachable in practice) |
| Connected | `$linked` | "Connected", linked-ago, Disconnect button |
| Code pending | `$code` | The code, Open Telegram link, "Generate a new code" |
| Idle | otherwise | Blurb + "Generate code" |

A `$refusal` banner renders above all four when an action refuses.

`wire:poll.5s` is attached **only while a code is outstanding and not yet
linked**, so the page stops polling the moment there is nothing to wait for.

Rate limit on minting: **5 codes per 15 minutes per user**
(`ConnectTelegram::CODES_PER_WINDOW`). Each new code invalidates the last, so an
unbounded loop would be a way to keep somebody permanently unable to finish
linking — and it writes to the `users` table every time.

### 5.3 The schema — four columns on `users`

```
telegram_link_code_hash        char(64)  nullable UNIQUE   sha256 of the code
telegram_link_code_expires_at  timestamp nullable
telegram_chat_id               unsignedBigInteger nullable UNIQUE
telegram_linked_at             timestamp nullable
```

Four columns rather than a table: there is exactly one live code and one linked
account per user, and no history worth keeping.

Three decisions worth knowing:

- **Only the sha256 is stored**, the way `personal_access_tokens` stores its
  token. The plaintext exists just long enough to render the card. Consequence:
  reopening the card **mints a fresh code**, because the old one cannot be read
  back. That is intended, not a bug.
- **Single use is a property of the schema.** Consuming a code nulls the hash,
  so a replay matches zero rows. There is no "already used" flag to forget.
- **`telegram_chat_id` is a BIG integer.** Telegram documents ids up to 52
  significant bits; a 32-bit column silently truncates newer accounts. Its
  unique index is **platform-wide, not per-organization** — Telegram accounts
  are global, so one Telegram user is one Clarix user.

`down()` drops the two unique indexes **before** the columns. MySQL refuses to
drop a column a unique index still covers.

### 5.4 `TelegramLinkService` — the core

| Method | Does |
|---|---|
| `issueFor(User): string` | Mints a code, stores the hash, returns plaintext once |
| `verify(string $code, int $chatId): User` | Binds the chat, burns the code |
| `resolve(int $chatId): ?User` | Who owns this chat |
| `unlink(User): void` | Drops the link and any outstanding code |
| `normalize(string): string` | Uppercase, strip non-alphanumerics |
| `hashOf(string): string` | `sha256(normalize($code))` |

**Code alphabet:** `ABCDEFGHJKMNPQRSTUVWXYZ23456789` — 31 symbols, no `I L O 0 1`,
because the code is read off a screen and retyped on a phone. 8 characters is a
little under 40 bits, which is only safe *because of* the 15-minute expiry and
the throttle in front of the endpoint.

Codes are generated with `random_int` (CSPRNG), never `rand` or `str_shuffle`.

`unlink()` **frees** the chat id rather than remembering it, so the same Telegram
account can afterwards be claimed by anybody — including a colleague taking over
a shared handset.

#### Two non-obvious implementation details

**`issueFor` writes through the query builder, not `save()`.** This is
load-bearing. `save()` writes only attributes Eloquent judges dirty. A `User`
held since before a `verify()` is stale exactly where it breaks: `verify()` nulls
both code columns through the builder, so the row's expiry is null while the
in-memory copy still holds the old one — and the old one can equal the new one to
the second. Eloquent sees no change, skips the column, and leaves a **hash with
no expiry**: an issued code that can never expire. After the write it
`forceFill(...)->syncOriginal()` so a later `save()` cannot write the columns
back again.

**`verify()` runs in a transaction with `lockForUpdate()`**, so two bot calls
racing the same code serialise. sqlite ignores the lock, so that guarantee is
only truly exercised against MySQL — the `$affected !== 1` check afterwards is
what holds on both.

### 5.5 Tenant scoping — the reason this design exists at all

Every method the bot reaches runs inside `TenantContext::runWithoutScope()`.

This is **the feature, not an optimisation.** The bot authenticates as *no user*,
so `TenantContext` has no organization and a scoped query would find nobody.

Authenticating the bot as a Sanctum service account — the way the task API does —
would be *worse*: the token resolves to a real user in one agency, so the lookup
would be silently confined to that agency, and every code from every other agency
would read as invalid **with no error anywhere to explain it**.

This single constraint is why the endpoints do not use Sanctum, why they need
their own middleware, and why the commercial checks live in the controller
instead of in middleware (§6.6).

---

## 6. The bot-facing API

Two endpoints. Prefix `/api/v1/telegram`, route names `api.v1.telegram.*`.

### 6.1 Authentication — `EnsureHermesRequest`

Three headers on every request:

| Header | Value |
|---|---|
| `X-Hermes-Key` | The shared key, compared with `hash_equals` |
| `X-Hermes-Timestamp` | Unix **seconds**, digits only (`ctype_digit`) |
| `X-Hermes-Signature` | `hash_hmac('sha256', "{timestamp}.{rawBody}", $secret)` |

- Signature is **lowercase hex, 64 chars** (`raw_output = false`). Base64 or
  uppercase will 401.
- The signed string is `timestamp` + `.` + the **raw request body**, before any
  re-serialization.
- Clock tolerance is **±300 seconds** (`TOLERANCE`).
- **Fails closed:** if either the key or the secret is unset, every request is
  refused. A half-configured deploy is shut, not open.
- Every failure returns the identical `401 {"message":"Unauthenticated."}`.
  Which check failed is not the caller's business — saying would help tune a
  forgery.
- **No nonce store, on purpose.** Replaying a verify after the code is burned
  simply fails; replaying a resolve reveals nothing the caller did not already
  have. The timestamp window covers the rest, and a nonce table would be a write
  on every bot message for no gain.

Signing the body as well as the timestamp is what stops a captured request being
edited into a different one — with only the key, any body would be authorised.

Reference implementation of the caller side:

```
timestamp = str(int(time.time()))
body      = json.dumps({"code": "K7M2QX9P", "chat_id": 123456789})
signature = hmac_sha256_hex(key=HERMES_SIGNING_SECRET, msg=f"{timestamp}.{body}")

POST /api/v1/telegram/verify
X-Hermes-Key: <HERMES_API_KEY>
X-Hermes-Timestamp: <timestamp>
X-Hermes-Signature: <signature>
Content-Type: application/json

<body>          # byte-identical to what was signed
```

### 6.2 `POST /api/v1/telegram/verify`

Throttle: **10/min per IP** (`hermes-verify`). It answers "is this code real",
which makes it a guessing oracle, and an 8-character code is only safe behind a
limit. Keyed on IP, not on the key — every bot request carries the same key, so
keying on it would be one global bucket a single noisy caller could exhaust.

```json
{ "code": "K7M2QX9P", "chat_id": 123456789 }
```

`code` is normalised before comparison (case, spaces and dashes are all things a
phone keyboard adds by itself), so length is checked *after* cleanup. Validation
is `max:64` on the raw string; `chat_id` is bounded as a big integer
(`min:1`, `max:9223372036854775807`) rather than left to a default that would
reject real accounts.

### 6.3 `POST /api/v1/telegram/resolve`

Throttle: **60/min per IP** (`hermes-resolve`). Not a guessing oracle — the
caller already holds the chat id — so this bounds abuse rather than protecting a
secret.

```json
{ "chat_id": 123456789 }
```

### 6.4 Success response (both endpoints)

`200`, wrapped in `data` (Laravel's default `JsonResource` envelope — wrapping is
**not** disabled anywhere in this app):

```json
{
  "data": {
    "user_id": 42,
    "name": "Asha Rai",
    "email": "asha@agency.com",
    "role": "pm",
    "organization": { "id": 3, "name": "Agency A", "slug": "agency-a" },
    "unit": { "id": 7, "name": "Design" },
    "chat_id": 123456789,
    "linked_at": "2026-08-22T10:14:00+05:45"
  }
}
```

`unit` is `null` when the user has none. The field list is written out rather
than inherited **deliberately** — serialising the whole user would put the
password hash's neighbours, the link-code hash and every future column on the
wire by default. `TelegramLinkApiTest` asserts
`assertJsonMissingPath('data.telegram_link_code_hash')`.

The relations are loaded inside `runWithoutScope()` for the same reason the
lookup is: no user is authenticated, so a scoped read would return nothing and
the envelope would name a null agency.

### 6.5 Failure responses

| Status | When | Message |
|---|---|---|
| `401` | Any auth failure | `Unauthenticated.` |
| `422` | Bad/expired/used code | `That code is not valid. It may have expired or already been used.` |
| `409` | Chat already linked elsewhere | `This Telegram account is already linked to another Clarix user. Disconnect it there first.` |
| `404` | `resolve` finds nobody | `No Clarix user is linked to that chat.` |
| `402` | Agency suspended | `This organization's subscription is suspended.` |
| `402` | Agency below Pro | `Telegram linking is not included in this organization's plan.` |
| `429` | Throttled | Laravel default |

**The 422 covers three distinct facts** — no such code, expired, already used —
behind one sentence, on purpose. Telling them apart would tell an attacker
whether a guess had ever been a real code, which is exactly the oracle a short
human-typed code cannot afford.

The `409` leaves the requesting user's code outstanding, so they can disconnect
the other end and retry.

### 6.6 Why the commercial checks are in the controller

`EnsureSubscriptionActive` and `EnsurePlanIncludes` both read `$request->user()`,
which is **null** on a bot-authenticated route. Attached to this group they would
wave every request through while *appearing* to guard it.

So both questions are asked in `TelegramLinkController::commercialRefusalFor()`,
once the code has resolved to a person, against **that person's** agency. Refusing
in only one of the two places would make the bot a way around the other.

---

## 7. The Task Bot — the second live integration

A **second Telegram bot**, serving a different pipeline and a different job.
AXOKAI/Hermes (§5–6) answers "who is this person". The Task Bot **files work and
reads it back**, and the writing half drives almost every decision below.

It shares *nothing* with §6 by design: its own bot token, its own table, its own
key, its own middleware, its own throttles, its own controllers. One shared
credential would mean rotating one bot's key silently breaks the other.

| | AXOKAI / Hermes (§6) | Task Bot (this section) |
|---|---|---|
| Handle | `@Jarvis_clarix_assistant_bot` | `@clarix_task_bot` |
| Driven by | A bot Clarix operates | **An n8n workflow** |
| Auth | Static key **+ HMAC per request** | Static key **only** — see 7.2 |
| Storage | 4 columns on `users` | Its own `n8n_telegram_links` table |
| Prefix | `/api/v1/telegram` | `/api/v1/n8n/telegram` |
| Writes? | No | **Yes** |
| Reads task data? | No | **Yes** — see 7.4b for the scoping rule |

### 7.1 File map

| File | Purpose |
|---|---|
| `app/Services/N8nTelegramLinkService.php` | Issue / verify / resolve / unlink. The core |
| `app/Services/N8nPipelineAccess.php` | The two commercial gates, returned as data |
| `app/Services/N8nDirectory.php` | The admin's unit and PM lists, and who may be assigned |
| `app/Services/N8nTaskQuery.php` | **The read ceiling.** Who may see which tasks — see 7.4b |
| `app/Services/N8nIdempotencyStore.php` | Claim / complete / release, and the replay |
| `app/Http/Middleware/EnsureN8nRequest.php` | Proves *the pipeline* is calling |
| `app/Http/Middleware/ResolveN8nActor.php` | Proves *who it is calling for*. See 7.3 |
| `app/Http/Middleware/EnsureN8nIdempotency.php` | Makes the attach retryable |
| `app/Http/Controllers/Api/N8nTelegramLinkController.php` | verify + resolve |
| `app/Http/Controllers/Api/N8nDirectoryController.php` | units + unit PMs |
| `app/Http/Controllers/Api/N8nTaskController.php` | read + create + attach files |
| `app/Http/Requests/Api/StoreN8nTaskRequest.php` | Intake shape, **and the admin branch** |
| `app/Http/Requests/Api/ListN8nUnitsRequest.php` | Directory authorization |
| `app/Http/Requests/Api/ListN8nUnitPeopleRequest.php` | Same, plus scoped unit resolution |
| `app/Http/Requests/Api/ListN8nTasksRequest.php` | Read filters, and the `unit_id` **403** |
| `app/Http/Resources/N8nTaskCollection.php` | The `tasks`/`count`/`truncated` envelope |
| `app/Livewire/Profile/ConnectTaskBot.php` | The connect card — the second live plugin |
| `app/Models/N8nTelegramLink.php` | Pointedly **not** `BelongsToOrganization` |
| `database/migrations/2026_08_31_000001_*` | `n8n_telegram_links` |
| `database/migrations/2026_09_01_000001_*` | `n8n_idempotency_keys` |
| `app/Console/Commands/PruneN8nIdempotencyKeys.php` | Nightly at 04:00 |

Wiring: middleware aliases `n8n`, `n8n.actor`, `n8n.idempotent` in
`bootstrap/app.php`; rate limiters `n8n-verify`, `n8n-resolve`, `n8n-directory`,
`n8n-read`, `n8n-intake` in `AppServiceProvider`; the `n8n` block in `config/services.php`;
the route group at the foot of `routes/api.php`.

### 7.2 Authentication — `EnsureN8nRequest`

**One header. That is the whole of it.**

| Header | Value |
|---|---|
| `X-N8n-Key` | The shared key, compared with `hash_equals` |

No timestamp, no signature. **This is a deliberate step down from §6.1, not an
oversight**, and the reasoning is worth understanding before anyone "fixes" it:
the caller is an n8n workflow assembled in a visual node editor, where an HMAC
over `{timestamp}.{raw body}` is a function call the editor cannot make without
a code node. The practical outcome of demanding one is a signing secret pasted
into a JavaScript step — strictly worse than not signing at all.

What that costs, stated rather than hidden:

- **A captured request is replayable for as long as the key lives.** Replaying
  `verify` after the code is burned fails; replaying `resolve` reveals nothing
  the caller did not already have. Replaying an **attach** would duplicate a
  file — which is exactly why 7.6 exists.
- Anyone holding the key can send any body. `N8N_API_KEY` belongs in the secret
  store, **never in a workflow export**.
- TLS is doing real work here: the key travels in a header in plaintext.

**Fails closed** like Hermes — an unset key refuses everything — and that matters
*more* here, because an empty configured key would otherwise match an absent
header exactly. Every failure is the identical
`401 {"message":"Unauthenticated."}`.

Rotation is a config change on both sides with a moment where neither value
works. If that becomes painful, **accept a list of keys here before reaching for
signing.**

### 7.3 `ResolveN8nActor` — the piece with no equivalent elsewhere

`EnsureN8nRequest` proves the pipeline is calling. This proves **who it is
calling for**, which is a different question and the one that matters the moment
an endpoint writes. Two middleware rather than one, because the link endpoints
need the first without the second: `verify` is how a chat *becomes* known, so it
cannot require the chat to already be known.

It does three things, each load-bearing:

1. Resolves `chat_id` to a `User`, live, through the link service. An unlinked
   chat is answered exactly as `/resolve` answers it, so a workflow handles one
   shape of "not linked" rather than two.
2. Asks `N8nPipelineAccess` the two commercial questions of **that person's**
   agency (7.7).
3. **Runs the rest of the request inside `TenantContext::actingAsOrganization()`.**

Without step 3 the endpoints are silently broken rather than loudly:

- `Task::create()` stamps `organization_id` from `TenantContext`. With no
  authenticated user that is **null**, and null is not "the actor's agency" — it
  is a row belonging to nobody that `OrganizationScope` never filters and every
  agency's task list may show.
- `notifyAdmins()` queries the tenant-scoped `User` model. Unscoped it would
  notify **every admin on the platform** about one agency's task.
- The task and unit lookups behind the attach and directory endpoints are scoped
  by it, which is what makes another agency's id a **404 rather than a target**.

Wrapping `$next()` rather than setting and restoring by hand means the context
covers form-request validation, the policy check and the write, and is unwound
even if any of them throws.

It uses `setUserResolver`, **not** `Auth::setUser`. That gives `$request->user()`
to the form requests, the policy and the creation service without logging anyone
in. Authenticating the guard would make `TenantContext` report the user's own
organization ambiently — which sounds equivalent and is not: it would apply to
the link lookups too, and those must stay unscoped to reach across agencies.

### 7.4 The endpoints

Prefix `/api/v1/n8n/telegram`, route names `api.v1.n8n.telegram.*`.

| Method | Path | Middleware | Throttle |
|---|---|---|---|
| POST | `/verify` | `n8n` | 10/min per IP |
| POST | `/resolve` | `n8n` | 60/min per IP |
| GET | `/units` | `n8n` + `n8n.actor` | 60/min **per chat** |
| GET | `/units/{unit}/pms` | `n8n` + `n8n.actor` | 60/min **per chat** |
| GET | `/tasks` | `n8n` + `n8n.actor` | 60/min **per chat** |
| POST | `/tasks` | `n8n` + `n8n.actor` | 30/min **per chat** |
| POST | `/tasks/{task}/files` | + `n8n.idempotent:tasks.files` | 30/min **per chat** |

**The throttle is listed before `n8n.actor` in the route group, and the order is
load-bearing**: behind it, a caller probing chat ids that are not linked would be
answered 404 without ever touching the limiter. Counting the refusals is the
point of having one.

**The link pair keys on IP; everything past `n8n.actor` keys on the chat.** Every
call arrives from the same n8n host, so an IP key would be one bucket shared by
every agency — one busy team filing a morning's work would lock out the platform.
The chat id is the closest thing the request has to an actor, and it is present
by definition: the request is refused without it.

**`{task}` and `{unit}` are deliberately not model-bound.** Implicit binding
resolves *before* `ResolveN8nActor` has established who is acting, so the lookup
would run with no tenant context and another agency's id would resolve happily.
The form requests load them under the acting scope instead.

#### `POST /verify` and `POST /resolve`

The same journey as §5.1 — an 8-character code from the same alphabet, a
15-minute TTL, 5 codes per 15 minutes per user — but against
`n8n_telegram_links` and a different bot.

`n8n_telegram_links` is a table rather than four more columns on `users`, which
is where this parts company with the AXOKAI link: that one is one person's
assistant and belongs on the person, while this serves a separate pipeline with
a separate audience, and putting it on `users` would mean every future bot adding
four more columns to the widest table in the schema. It carries **no
`organization_id` and no `unit_id`** — both are derivable from `user_id`, and a
stored copy goes stale: somebody moved between units would keep filing against
the unit they left, silently, until an accountant noticed the credit landed in
the wrong place. `chat_id` is a **string**, because Telegram's group ids are
negative and its user ids already run past 32 bits.

The response envelope is **flat, not wrapped in `data`** — the one shape
difference from §6.4, and not cosmetic: n8n addresses fields by path in a visual
editor, and a `data.` prefix is one more thing for every node downstream to get
wrong.

```json
{ "user_id": 42, "organization_id": 3, "unit_id": 7, "role": "pm" }
```

Four fields, all read off the `User` at render time, none stored on the link row.
Narrower than §6.4 on purpose: a task row needs a creator, a unit and an owning
agency, and the workflow needs the role to know which conversation to have. The
display name is not part of filing work, so it is not on the wire — an n8n
execution log tends to be readable by more people than the database is.

`unit_id` is **genuinely nullable**: admins, supervisors and HR belong to no unit.
`role` is what the workflow branches on, and it exists precisely because a null
`unit_id` is *not* a usable proxy for "is this an admin" — HR and supervisors
carry none either, and neither files work.

#### `GET /units` and `GET /units/{unit}/pms` — the admin branch

A PM carries their unit on their own user row, so the pipeline knows where their
work goes the moment `/resolve` answers. **An admin belongs to no unit** — that
is what the role means here — so the bot has to offer them the agency's units,
then that unit's people, and carry both ids into the intake call.

Both return a **bare JSON array**, same shape:

```json
[ { "id": 7, "name": "Design" }, { "id": 9, "name": "Content" } ]
```

- **Admins only.** A PM gets **403**, not an empty list. An empty array is a
  legitimate answer meaning "your agency has no units", and a workflow branching
  on `length === 0` would show a PM that message instead of a bug.
- `isAdmin()`, **not** `reachesEveryUnit()`. A supervisor is equally unitless and
  holds `tasks.create`, but the intake endpoint does not accept their targeting
  either — opening the directory without the write would be a picker leading to a
  403. **The two move together or not at all.**
- **The role check runs before the unit lookup**, and a test asserts it. A PM is
  refused identically whether or not the id in the path exists; checking the unit
  first would turn the 403/404 difference into a way for any linked user to
  enumerate their agency's unit ids.
- A cross-agency or non-existent unit is a **404, never a 403** — a unit in
  another agency and a unit that never existed must be indistinguishable from
  outside, or the endpoint reports whether an id is in use platform-wide.

The list and the validator behind `assigned_pm_id` are **the same query**, in
`N8nDirectory`. Written twice they drift the moment a role is added: the bot
would list somebody it then refuses to accept, and the failure would surface as
a validation error in a Telegram reply with nothing to explain it.

`N8nDirectory::ASSIGNABLE_ROLES` is `['pm', 'writer']`. Today only PMs are given
a `unit_id` (see `Admin\ManageUsers`), so a writer never actually appears — the
role is listed anyway so that giving writers units changes one fact rather than
four queries.

#### `POST /tasks`

```json
{
  "chat_id": "123456789",
  "title": "Landing page copy",
  "task_code": "LP_014",
  "priority": "medium",
  "deadline": "2026-09-30",
  "credit_amount": "3.50",
  "task_type": "content",
  "important_notes": "…",

  "target_unit_id": 7,
  "assigned_pm_id": 42
}
```

Rules are **`TaskCreationService::rules()`** — the same set the create-task screen
and the token API validate against. Only what is about *this transport* lives in
`StoreN8nTaskRequest`. `assigned_admin_id` is dropped from the rules rather than
ignored in the controller, because **a field with no rule never reaches
`validated()`**, and `create()` reads the key with a null default.

**Who decides what:**

| | PM / writer | Admin |
|---|---|---|
| `unit_id` | Their own, always, no exception | `target_unit_id`, **required** |
| `pm_id` | Themselves | `assigned_pm_id`, or **null** (unassigned is a real state) |
| `created_by` | Themselves | **Themselves** — the admin filed it, whoever it is for |
| `status` | `pending` | `pending` |
| `target_unit_id` / `assigned_pm_id` | **Ignored** | Required / optional |

**A PM's targeting fields are ignored, not rejected — and that is the stronger
option, not the lazier one.** Rejection is a rule that has to keep being right;
ignoring is structural: the fields have no rule when the actor is not an admin,
so they never reach `validated()`, and `create()` takes the target from a
*separate argument*. There is no path from a PM's payload to the `unit_id`
column, in the same way there is none to `assigned_admin_id`. It also means a
workflow can send the fields unconditionally, which is the shape n8n makes
easiest to build.

Three details worth knowing:

- `target_unit_id` uses **`TenantExists`, not `exists`**. The validator builds its
  queries straight on the query builder and never sees `OrganizationScope`, so
  `exists:units,id` answers "does this id exist *anywhere on the platform*" and
  would let an admin file into another agency's unit. This is the single check
  standing between the payload and a cross-tenant write.
- `assigned_pm_id` must belong to **that specific unit**. The pairing matters as
  much as the membership: a PM of unit one holding a task filed against unit two
  would see work their own unit filter hides.
- `task_code` is unique **per unit**, so its uniqueness rule runs against the
  *targeted* unit — for an admin that is the one they named, not their own, which
  is null and would make every code in the agency look free.

`TaskCreationService::create()` takes the target as a **fourth argument** rather
than two more keys in `$data`. `$data` is the caller's own validated payload; a
unit id read out of it would be a field the caller can set, which is exactly what
must not decide ownership.

⚠️ `TaskCreationService::rules(null)` renders the code-uniqueness rule as
`unit_id,NULL`, deliberately, **not** by interpolating the null. An interpolated
null produced a trailing empty parameter that reaches the driver as
`where unit_id = ''` — sqlite quietly matches nothing and MySQL coerces to 0 with
a truncation warning, so the two agree by accident rather than because the rule
means anything. Covered by `tests/Unit/Services/TaskCreationRulesTest.php`.

#### `POST /tasks/{task}/files`

Two calls rather than one multipart create, matching the token API and for the
same reasons — which are about **failure** rather than tidiness:

- The R2 put cannot join a database transaction. A create-with-file request that
  fails partway leaves a task behind that the caller **cannot retry**, because
  `task_code` is now taken. Split in two, each half is retryable on its own: the
  task exists, so the attach can be repeated until it works. That matters more
  here than anywhere else in the codebase, because the caller retrying is an
  **n8n error branch rather than a person**.
- It sidesteps PHP's `post_max_size`, which caps the whole multipart body.

**The pipeline's side of the bargain: keep the task id from the create response
and use it in the attach path.** A submission whose file fails to attach is a
real task with no brief on it, so the workflow's error branch should say so in
the chat rather than swallow it.

Limits are the token API's, deliberately — same allowed types, same size ceiling,
same quota rule. A file arriving from Telegram is no more trusted than one
arriving from a script, and having two answers to "what may be uploaded" is how
one of them ends up wrong.

### 7.4b Reading tasks back — `GET /tasks`

One endpoint for the three questions the bot asks, because they differ only in
which optional filters they set:

1. **Is this code already taken here?** — `task_code` + `unit_id`, before a create.
2. **How is my work doing?** — a PM's own status query.
3. **How much is pending?** — an admin's count, over a unit or the agency.

Three routes would have been three copies of one scoping rule, and the copy that
drifts is the one that leaks.

| Param | Type | Meaning |
|---|---|---|
| `chat_id` | string | **Required.** Who is asking. Consumed by `ResolveN8nActor`, not a filter. |
| `unit_id` | int | Narrow to one unit. **Authorized, not just validated — see below.** |
| `task_code` | string | Exact match, never a prefix. |
| `pm_id` | int | Narrow to one PM. |
| `status` | string | One of `Task::STATUSES`. Anything else is a 422. |

All filters are optional and combine with AND. Empty strings are treated as
absent, so a workflow may send every field unconditionally — which is the shape
n8n makes easiest to build.

```json
// GET /tasks?chat_id=…&status=pending -> 200
{ "tasks": [ { "id": 448, "task_code": "abc_123", "title": "Example task",
               "task_type": null, "important_notes": null, "unit_id": 43,
               "pm_id": 85, "priority": "medium", "status": "pending",
               "deadline": "2026-09-01", "credit_amount": "2.00",
               "created_at": "2026-08-30T09:00:00+05:45" } ],
  "count": 1, "truncated": false, "limit": 50 }
```

**`count` is the total that matched, not `tasks.length`.** The list stops at
`limit` (50, newest first); the count does not, because "how many are pending" is
one of the three questions and a count that stopped at the page size would answer
it wrongly *while looking correct*. `truncated` is `count > tasks.length`,
derived server-side because that comparison is easy to get wrong in an n8n
expression — and getting it wrong tells someone with 213 pending tasks that they
have 50.

**No match is `200` with `"tasks": [], "count": 0` — never a 404.** A 404 here
would be indistinguishable from a routing mistake or a bad deploy, so "there is
no such task" and "this endpoint is broken" would read identically in an error
branch.

#### The scoping rule — why `pm_id` is safe to accept

This is the part worth understanding before changing anything here.

The pipeline authenticates with a **static shared key**, which names the *caller*
and says nothing about the *person* — every request from n8n carries the same
one. So the backend cannot take `pm_id` as a claim about who is asking. **It does
not.** `ResolveN8nActor` has already turned `chat_id` into a real Clarix user row
against Clarix's own records, and `N8nTaskQuery` derives a **ceiling** from that
person's role. The query string can only ever narrow that ceiling:

| Actor | Reaches |
|---|---|
| admin, supervisor | every task in their agency |
| pm | **their whole unit** — not just their own `pm_id` |
| writer | the tasks assigned to them |
| anyone else | nothing (empty list, not an error) |

These arms are `TaskPolicy::owns()`, deliberately. The bot is a second window
onto the same data, and a PM told "4 pending" in Telegram while six cards sit on
their board has been told one of those by a bug.

**A PM's ceiling is the unit, which is the one place this diverges from how the
endpoint was first specified.** On the board a PM already sees every task in
their unit — a colleague's, and the ones nobody owns yet. Confining the bot to
`pm_id` would have hidden their unit's unassigned work from the count while
leaving it visible on screen. `pm_id` survives as a *filter*, so "just mine" is
one query away; it is simply not the ceiling.

**Consequence for the n8n side: a wrong `pm_id` shows a PM fewer tasks, never
another person's.** `pm_id` cannot widen anything, so a bug in the workflow's
user-mapping is a cosmetic fault rather than a disclosure.

**`unit_id` is the exception, and is refused rather than ignored.** It is the one
filter that could look like widening, so it is authorized: a PM may name only
their own unit, everybody else only a unit their agency has. Out of reach is a
**403**, not an empty list — an empty list is a truthful answer to a legitimate
question ("that unit has no tasks"), so using it for a refusal would leave a
workflow unable to tell the two apart. Another agency's unit and a unit that
never existed give the *same* 403, which is what stops this reporting whether an
id is in use anywhere on the platform.

`tasks.view` is asked of the **person**, so switching it off for a role in the
Authorization panel stops the bot for that role with no workflow change. HR is
the live example — the role holds no task permissions by default and is refused.

⚠️ **The residual risk, stated plainly: `chat_id` is bearer-equivalent.** Anyone
holding `N8N_API_KEY` can send any linked chat's id and act fully as that person.
That is already true of the create endpoint and is not new here, and a signed
per-user assertion would not help because n8n would hold the signing secret too.
Key secrecy and rotation remain the whole control. See 7.2.

### 7.5 Success responses

Flat and unwrapped, for the reason given in 7.4.

```json
// POST /tasks -> 201
{ "id": 812, "task_code": "LP_014", "title": "Landing page copy",
  "task_type": "content", "important_notes": null, "unit_id": 7, "pm_id": 42,
  "priority": "medium", "status": "pending", "deadline": "2026-09-30",
  "credit_amount": "3.50", "created_at": "2026-08-23T10:14:00+05:45" }

// POST /tasks/{task}/files -> 201, a bare array
[ { "id": 55, "task_id": 812, "original_name": "brief.pdf",
    "file_size": 91240, "mime_type": "application/pdf",
    "uploaded_by": 42, "created_at": "…" } ]
```

`organization_id` is **absent on purpose** — tenancy bookkeeping stays internal,
and a test asserts it is not there.

⚠️ **`$wrap` does not travel the way it looks like it should.** Setting it to
null on a *resource* governs a single resource, while `Resource::collection()`
builds an `AnonymousResourceCollection` whose own static `$wrap` is still
Laravel's `'data'`. That is why `N8nTaskFileCollection`, `N8nDirectoryCollection`
and `N8nTaskCollection` are declared classes rather than inline collections.
The mismatch is invisible until a workflow node reads null.

⚠️ **The same trap has a second door: `with()` and `additional()`.**
`ResourceResponse` wraps the payload in `'data'` whenever a resource has **no
`$wrap` *and* returns anything from `with()`**. So adding one informational key
via `with()` silently re-wraps the whole response. `N8nTaskCollection` hit this
during development — `limit` was in `with()` and the entire envelope moved under
`data`. Everything it publishes is assembled in `toArray()` for that reason.

### 7.6 Idempotency — `Idempotency-Key`

**On the attach endpoint only**, and only it needs one. A replayed *create* is
answered by the schema — `task_code` is unique per unit, so the second attempt is
a 422 rather than a duplicate task. Attaching has no such natural key: **the same
bytes posted twice are two perfectly valid attachments**, and 7.2 leaves a
captured request replayable.

So the caller supplies `Idempotency-Key` per submission (8–128 chars), which asks
for nothing the visual editor cannot already do. Four outcomes, and the second is
the one that matters most:

| State | Answer |
|---|---|
| No holder | Claim taken, work runs, response kept |
| Holder, **completed** | **The original response, verbatim**, plus `Idempotent-Replay: true` |
| Holder, in flight | `409` — retry shortly |
| Holder, **different request** | `422` |

The stored response is the point, not merely the lock. **An n8n retry usually
means the first call timed out, not that it failed** — the work may well have
happened, and the workflow needs the same answer to carry on with. Answering 409
would be correct and useless.

The `422` on a reused key matters as much: a workflow reusing one key across two
different submissions would otherwise be handed the first one's response for the
second file, silently, and **the second file would never be stored** — a lost
attachment that looks like a success in every log.

Keys are **scoped per user**, which is a confidentiality requirement rather than
tidiness: the stored body names task and file ids, so a shared namespace would
hand agency B agency A's response for a key A happened to choose — a cross-tenant
leak through a cache. The unique index is `(user_id, scope, key)`. `scope` names
the operation, so a key minted for an attach can never satisfy a create.

Only **successful** responses are remembered. A refusal has left nothing behind
to duplicate, so the key goes back and the caller can correct the request and try
again — which, for a workflow re-fetching a file from Telegram, is the ordinary
path rather than an edge case.

`response_status` null means **in flight**: the row was claimed and the work has
not finished. The insert itself is the lock, with no separate status column to
get out of step with reality.

Retention is **24 hours** (`N8nIdempotencyStore::TTL_HOURS`), pruned nightly at
04:00. Nothing depends on that job landing — `claim()` drops an expired row before
taking the key — so a missed night costs storage and nothing else.

### 7.7 Failure responses

| Status | When | Notes |
|---|---|---|
| `401` | Bad or missing `X-N8n-Key` | Always `Unauthenticated.` |
| `422` | Bad `chat_id` shape | From `ResolveN8nActor`, in validation-error shape |
| `404` | Chat not linked | `{ "message": "…", "linked": false }` |
| `402` | Agency suspended | Same message as §6.5 |
| `402` | Agency below Pro | `The Task Bot is not included in this organization's plan.` |
| `403` | Non-admin on a directory endpoint | Or actor lacks `tasks.create`, or has no unit and is not an admin |
| `403` | Actor lacks `tasks.view` on `GET /tasks` | HR by default |
| `403` | `unit_id` outside the actor's ceiling on `GET /tasks` | **Deliberately not 404 and not an empty list** — see 7.4b |
| `404` | Another agency's task or unit | **Never 403** — see 7.4 |
| `422` | Validation, incl. duplicate `task_code` | Messages written for a Telegram reply |
| `422` | Unknown `status` on `GET /tasks` | Lists the accepted values in the message |
| `409` | Idempotency claim in flight | Retry shortly |
| `429` | Throttled | Laravel default |

⚠️ **Every failure under `/api` is JSON, and that took a fix.** Laravel picks the
shape of an error response from `expectsJson()`, which reads the `Accept` header
— so a caller that omits it gets the *web* treatment on the failure path even on
an API route. This was live and biting: `POST /tasks` with no `Accept` header and
an invalid payload returned **`302` to the site homepage with an HTML body**
instead of a 422, so n8n saw an unparseable response and the real validation
error was invisible. An unauthenticated request was worse — it redirected to the
login page rather than answering 401.

The fix is `shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())`
in `bootstrap/app.php`. It is deliberately **not** per-route: the next endpoint
added to `routes/api.php` would otherwise inherit the same bug and nothing would
say so. The path test scopes it, so the web routes still redirect, which is what
a browser wants. `N8nTaskReadTest` pins all four outcomes — success, no results,
bad auth, bad params — with the plain `get()` helper, which sends no `Accept`
header at all.

**Validation messages here are read in a Telegram reply by somebody who cannot
see the API.** That is why `task_code.unique`, `deadline.date`, `priority.in`,
`target_unit_id.*` and `status.in` are written out rather than left to Laravel's
defaults.

The commercial gates live in `N8nPipelineAccess`, **shared** between the link
endpoints and the intake endpoints — the two halves of one integration must not
be able to disagree about what a suspended agency may do. Asking them on
`resolve` as well as on `verify` is deliberate: `resolve` runs on every incoming
message, so it is the gate that actually stops a suspended agency's work reaching
the pipeline. Checking only at link time would let an agency that lapsed six
months ago keep filing tasks off a link it made while it was paying.

They are in a service rather than middleware for the §6.6 reason:
`EnsureSubscriptionActive` and `EnsurePlanIncludes` both read `$request->user()`,
which is null on a key-authenticated route — attached to this group they would
**wave every request through while appearing to guard it**.

### 7.8 A worked admin conversation

```
message        -> POST /resolve          -> { role: "admin", unit_id: null }
role == admin  -> GET  /units            -> [ {7,"Design"}, {9,"Content"} ]
picks 7        -> GET  /units/7/pms      -> [ {42,"Asha Rai"} ]
picks 42       -> POST /tasks            -> { id: 812, … }
                      target_unit_id: 7
                      assigned_pm_id: 42
has a file?    -> POST /tasks/812/files  -> [ { id: 55, … } ]
                      Idempotency-Key: <one per submission>
```

A PM's conversation is the same with **the middle two steps removed** and neither
targeting field sent — `/resolve` already returned their `unit_id`.

---

## 8. What is NOT in this repo

**Both bots.** Clarix exposes endpoints; something external drives Telegram in
each case. Two separate deployments, two separate sets of secrets.

### The AXOKAI bot (§5–6)

- The Telegram webhook / long-poll loop
- Calling `/verify` on `/start {CODE}` and `/resolve` on every later message
- **Restricting linking to private chats** — Clarix cannot see chat type
- **Deleting the message containing the code** after a successful link
- The `/start` copy (the place the AXOKAI-vs-Jarvis mismatch could be smoothed)
- Storing `HERMES_API_KEY` / `HERMES_SIGNING_SECRET` and signing correctly

### The n8n workflow (§7)

- The Telegram webhook, the conversation state, and the prompts
- **Branching on `role` from `/resolve`**, and the unit/PM pickers for an admin
- Generating an `Idempotency-Key` per submission
- Downloading the file from Telegram and posting it to the attach endpoint
- **Reporting an attach failure in the chat** rather than swallowing it — a
  submission whose file failed to attach is a real task with no brief on it
- Storing `N8N_API_KEY` outside any exported workflow JSON
- Restricting linking to private chats, and deleting the code message

Also not built: any MCP server or client, OAuth for any listed plugin, and any
automation trigger engine.

---

## 9. Configuration

### Environment variables

| Var | Required | Default | Notes |
|---|---|---|---|
| `HERMES_API_KEY` | **Yes** | none | 32 random bytes hex (64 chars) recommended |
| `HERMES_SIGNING_SECRET` | **Yes** | none | Same. HMAC hashes keys >64 bytes down to 32 anyway |
| `TELEGRAM_BOT_USERNAME` | No | `Jarvis_clarix_assistant_bot` | **No leading `@`** — interpolated into the deep link |
| `N8N_API_KEY` | **Yes** | none | The Task Bot's whole authentication (§7.2). Secret store only |
| `N8N_TELEGRAM_BOT_USERNAME` | No | `clarix_task_bot` | **No leading `@`**. A different bot from the one above |

Generate with `openssl rand -hex 32`. No format or length is enforced by code —
the middleware only requires non-empty and byte-identical on both sides. Prefer
hex or base64url so a stray quote or newline cannot break `hash_equals` and
produce a 401 that explains nothing.

Both key and secret are **required in any environment where the endpoints are
reachable**; the middleware refuses everything while either is unset.

### `config/services.php`

```php
'hermes' => [
    'key'          => env('HERMES_API_KEY'),
    'secret'       => env('HERMES_SIGNING_SECRET'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'Jarvis_clarix_assistant_bot'),
],
```

```php
'n8n' => [
    'key'          => env('N8N_API_KEY'),
    'bot_username' => env('N8N_TELEGRAM_BOT_USERNAME', 'clarix_task_bot'),
],
```

Each `bot_username` is used in exactly one place: building the `t.me` deep link
in the matching connect card. **The env var beats the config default** — if
Railway has it set to an old handle, that wins over anything changed in code.

⚠️ **The two blocks must never be merged.** They are separate bots registered
separately in BotFather; one shared key would mean rotating one bot's credential
silently breaks the other, and one shared handle would send half the users to
the wrong bot.

### Deployment state

Hermes configured on Railway on 2026-08-21. The env var names were deliberately
**not** renamed during the AXOKAI rebrand, to avoid reconfiguring them and to
avoid a two-sided deploy.

`N8N_API_KEY` must be set on Railway **and** in the n8n instance's credential
store — the endpoints refuse everything while it is unset, so a half-configured
deploy is closed rather than open. Rotating it is a two-sided change with a
moment where neither value works (§7.2).

---

## 10. Access control — three independent layers

All three must pass. None can stand in for another.

### Layer 1 — Role

`Route::middleware(['role:admin,pm,writer'])` on the whole `/ai` group. Listed
explicitly so a role added later is **denied until named**, and it mirrors the
sidebar check.

### Layer 2 — Plan (the commercial layer)

`config/plans.php`:

```php
'order'   => ['base', 'standard', 'pro'],   // cheapest first; position is meaning
'default' => 'base',                        // unknown plan -> least, never most
'minimum' => [
    'tasks' => 'base',     'files'   => 'base',
    'erp'   => 'standard', 'ai_chat' => 'standard', 'calendar' => 'standard',
    'automation' => 'pro',                  // <- MCP & Scheduled Tasks
],
```

Expressed as minimums, not three lists, so "Standard is Base plus ERP" is
structurally true. **A feature not named here is denied on every plan** — a typo
in a gate closes it rather than opening it.

The plan is read from `organization_subscriptions`, **never** from
`organizations.subscription_type`. Those disagreed in production once — an agency
reading `base` while paying for `standard` — which is why only one is allowed to
be the answer. Newest `started_at` wins, `id` breaks ties.

`PlanFeatures` memoises **per request only**. A five-minute cache would mean a
superadmin upgrading an agency watched nothing happen for five minutes.

Refusal is **402**, not 403 — the answer is "not purchased", not "not permitted".
The refusal sentence is built once in `EnsurePlanIncludes::refusalFor()` and
shared with the component guards, so the two cannot phrase it differently.

Superadmins are never plan-gated: they belong to no organization, so there is no
plan to consult.

### Layer 3 — The component's own repeat

`RequiresPlan::assertPlanIncludes()` inside `render()`. A Livewire action POSTs
to `/livewire/update` and **never passes through the route's middleware**, so
without this a crafted request could mount a gated component directly.

So `/ai/mcp` is gated twice: `plan:automation` on the route, and
`assertPlanIncludes('automation')` in `McpPlugins::render()`.

`ConnectTelegram` is the exception that proves the rule: it does **not** abort,
it sets `$refusal`, because an action refusing needs to say so inside the card.
Its plan check is belt-to-the-page's-braces.

### The sidebar

`resources/views/layouts/app.blade.php` filters AI nav entries by
`planAllows($aiFeature)`, so MCP & Plugins and Scheduled Tasks simply do not
appear below Pro. `ai.overview` is deliberately ungated (`null` feature) so the
section never empties and a Base agency has somewhere to read what upgrading
would give them.

Note there is a second, older layout at
`resources/views/components/app-layout.blade.php` whose AI nav tuples carry **no**
feature slot. `McpPlugins` renders through `layouts.app`, so the gated one is the
one in play — but be aware the two exist if you touch navigation.

### ⚠️ Known consequence: the Base-plan upsell is unreachable

`ConnectTelegram` has a "locked" render state:

> *Not included in your plan. Upgrade to Pro to unlock Telegram linking.*

When the card lived on `/settings` (an ungated page) a Base-plan user saw it.
Now that it lives on `/ai/mcp`, **the page 402s before the component renders**,
so no real user can ever reach that copy.

The state and its `$refusal` machinery still exist and still pass their tests —
because `Livewire::test` mounts the component directly, bypassing the route. So
**the tests will not tell you this is dead code.**

If the upsell is wanted back, it needs to live somewhere ungated. This is a
product decision, deliberately left open.

---

## 11. Tests

### The pages and the AXOKAI integration

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/AI/McpPluginsTest.php` | 11 | Library renders, accordion state, tints, live cards, component mounting |
| `tests/Feature/AI/ScheduledTasksTest.php` | 5 | Previews, `brand()` lookups |
| `tests/Feature/Telegram/ConnectTelegramTest.php` | 12 | Card states, rate limit, plan lock, settings absence, no-Hermes sweep |
| `tests/Feature/Telegram/HermesAuthTest.php` | 8 | Key, timestamp, signature, fail-closed |
| `tests/Feature/Telegram/TelegramLinkApiTest.php` | 14 | Both endpoints, envelope, cross-org, commercial refusals |
| `tests/Feature/Telegram/TelegramLinkServiceTest.php` | 16 | Issue/verify/resolve/unlink, expiry, races |
| `tests/Feature/Telegram/TelegramColumnsTest.php` | 7 | Schema shape |
| `tests/Feature/Plans/PlanLivewireGuardTest.php` | 2 | Component-level plan guards |

### The Task Bot (§7)

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/TaskBot/ConnectTaskBotTest.php` | 17 | Card states, rate limit, plan lock, deep link |
| `tests/Feature/TaskBot/N8nAuthTest.php` | 7 | `X-N8n-Key`, fail-closed, one refusal shape |
| `tests/Feature/TaskBot/N8nTelegramLinkServiceTest.php` | 24 | Issue/verify/resolve/unlink, expiry, races |
| `tests/Feature/TaskBot/N8nTelegramLinkApiTest.php` | 27 | verify + resolve, the flat envelope, `role`, cross-org |
| `tests/Feature/TaskBot/N8nTelegramLinkSchemaTest.php` | 13 | `n8n_telegram_links` shape and indexes |
| `tests/Feature/TaskBot/N8nDirectoryTest.php` | 16 | Units + unit PMs, admin-only, cross-org 404s, check order |
| `tests/Feature/TaskBot/N8nTaskIntakeTest.php` | 28 | Create, ownership stamping, permission and unit gates |
| `tests/Feature/TaskBot/N8nAdminTargetingTest.php` | 16 | `target_unit_id` / `assigned_pm_id`, and that a PM's are ignored |
| `tests/Feature/TaskBot/N8nTaskFileIntakeTest.php` | 18 | Attach, quota, scoped task lookup |
| `tests/Feature/TaskBot/N8nIdempotencyTest.php` | 26 | Claim, replay, in-flight, fingerprint mismatch, per-user scope |
| `tests/Feature/TaskBot/N8nTaskReadTest.php` | 42 | `GET /tasks` — role ceilings, per-unit `task_code`, the cap, JSON-without-`Accept` |
| `tests/Unit/Services/TaskCreationRulesTest.php` | 2 | The unique rule with and without a unit |

Run the relevant slice:

```bash
php artisan test tests/Feature/AI tests/Feature/Telegram tests/Feature/TaskBot
# -> 363 passing
```

### ⚠️ Baseline failures — do not chase these

**14 tests fail on a clean checkout** (measured 2026-08-23 on a full
`php artisan test` — 1182 passing; re-confirmed 2026-08-31, same 14, 1224 passing). None are in `tests/Feature/AI`,
`tests/Feature/Telegram` or `tests/Feature/TaskBot`, all three of which are
fully green:

| Suite | Failing | Cause |
|---|---|---|
| `Tests\Unit\CreditListExportTest` | 6 | Export formatting |
| `Tests\Feature\SettingsTest` | 5 | `PublicPropertyNotFoundException` — Livewire scaffolding drift |
| `Tests\Feature\Auth\*` | 2 | Rendering the auth scaffolding |
| `Tests\Feature\Tasks\CreateTaskTest` | 1 | `removeUpload` no longer on the component |

**All unrelated to this feature.** Capture the baseline before blaming your
change:

```bash
git stash && ./vendor/bin/phpunit <suites> > before.txt; git stash pop
```

### Other testing gotchas

- `abort()` inside a Livewire component **renders** rather than throws under
  `Livewire::test` — assert on rendered output, not exceptions.
- Static Blade text is **not** escaped, so `assertSee("isn't")` fails where the
  HTML holds `&#039;`. Pass `false` as the second argument or assert on the
  escaped form.
- Policies that query relations return `false` under `Gate::forUser` because
  tenant context is missing — use `actingAs`.
- A freshly migrated DB denies nearly everything until `PermissionSeeder` runs
  **inside an organization**.
- sqlite hides MySQL enum and locking behaviour. A green suite proves nothing
  about enum columns or `lockForUpdate` — check against a MySQL clone.
- Pint fails repo-wide on `binary_operator_spaces` (aligned `=>`) and
  `not_operator_with_successor_space` (`! $foo`). **That is house style.** Do not
  run `pint --fix` broadly; compare against the baseline for the files you touch.

### Checks that run outside `php artisan test`

Two things the sqlite suite structurally cannot prove, so both live outside it.

**Against a clone of the production copy** —
`tests/Manual/N8nTaskReadOnProductionCopyTest.php` (10 tests). Real units, real
PMs, the agency's own permission rows and a **real MySQL enum**. It is what
proves the page cap and `count` behave over 348 tasks rather than the two a
fixture builds, and that every status the endpoint accepts is a status the
column actually declares. Outside `tests/Feature` so `php artisan test` never
picks it up, and without `RefreshDatabase` so the production-shaped data
survives; everything it creates it removes in `tearDown`.

```bash
mysql -uroot -e "DROP DATABASE IF EXISTS clarix_supclone; CREATE DATABASE clarix_supclone"
mysqldump -uroot --single-transaction clarix | mysql -uroot clarix_supclone
DB_DATABASE=clarix_supclone php artisan migrate --force   # the copy predates the bot
./vendor/bin/phpunit -c phpunit-clone.xml
```

⚠️ **Clone it; never point a test at `clarix` itself** — that database is a
production copy. Confirm `.env`'s `DB_DATABASE` is back to `clarix` before you
finish; it has drifted mid-session before.

**Against a deployed Clarix** — `scripts/verify-n8n-task-read.sh` (21 checks).
Tests prove the rules; this proves the *deploy*, which fails differently: a route
that 404s on Railway, a key that does not match n8n's credential store, a proxy
rewriting an error into HTML.

```bash
BASE_URL=https://<app>.up.railway.app N8N_KEY=<live key> \
ADMIN_CHAT=<linked admin chat id> PM_CHAT=<linked pm chat id> \
TASK_CODE=<a real code> UNIT_ID=<its unit> OTHER_UNIT_ID=<another unit> \
./scripts/verify-n8n-task-read.sh
```

No `jq` dependency, exits non-zero on failure. The check worth reading twice is
"same code, other unit": a match there means the endpoint is scoping `task_code`
globally, and the bot will start refusing codes that are free.

---

## 12. Extending this

### Adding a mock plugin

Append to `McpPlugins::plugins()`. Give it a `name`, a `category` that exists in
`CATEGORY_TINT`, a `colour`, a `blurb`, a `fields` list and a 24×24 `logo` path.
Nothing else. The card, the accordion and the disabled form come for free.

### Making an integration live

1. Build its Livewire component (model it on `ConnectTelegram` or, if it needs
   to *write*, on the §7 stack — which is a much larger commitment).
2. Set `'fields' => []`, `'connect' => true` and `'component' => '…'` on its
   plugin entry.
3. Add a branch to the view's `@if` chain. It is written out per component
   rather than as `<livewire:dynamic-component>` on purpose, so the set of
   mountable components stays fixed in the template.
4. Update `McpPluginsTest::test_only_the_live_plugins_drop_their_save_button`,
   which asserts `$live === 2` and **will fail by design** —
   `test_every_live_plugin_names_a_component_the_view_can_mount` will too, until
   the component is added to its whitelist.

### Building the automation engine

`ScheduledTasks::AUTOMATIONS` is the spec. Each entry already names a trigger
kind, a set of integrations and an output. When the engine lands, that constant
is the only thing that goes — the card, the node graph and the tints stay.

### Open threads

- [ ] Rename the bot handle if BotFather ever allows it → update
      `config/services.php`, the whitelist in `test_no_view_says_hermes`, the two
      `ConnectTelegramTest` fixtures, **and** `TELEGRAM_BOT_USERNAME` on Railway
- [ ] Decide where the Base-plan upsell lives (§10)
- [ ] Fix the 14 baseline test failures (§11) — separate issue, four unrelated causes
- [ ] Bot side, **both bots**: private-chats-only, delete the code message after
      linking
- [ ] Task Bot: decide whether a supervisor should get the admin branch (§7.4).
      It needs the directory endpoints *and* the intake targeting, or neither
- [ ] The plugin grid's intro copy still says "Link the apps your team already
      uses… AXOKAI and Clarix automations can work across all of them", which
      sits slightly at odds with a card that binds one person rather than the agency
