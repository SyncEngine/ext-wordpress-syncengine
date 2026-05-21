# SyncEngine — WordPress Plugin

WordPress plugin that enhances and extends the integration between WordPress and a [SyncEngine](https://syncengine.io) instance.

## Features

- **Enhanced REST API queries** — adds advanced meta filtering params to all post type, taxonomy, user, and attachment endpoints
- **Automatic event triggers** — fires connected SyncEngine automations on WordPress Core events (posts, terms, users)
- **Custom hook mappings** — map any WordPress action hook to one or more SyncEngine automation endpoints via the admin UI
- **On-demand endpoint status checks** — endpoint status is loaded per endpoint via AJAX only when requested from the actions column
- **Trace counters in status view** — endpoint status responses show `running`, `scheduled`, and `queued` counters inline
- **Auto-refresh** — the trigger endpoint map is automatically refreshed when a WordPress connection is tested or a related automation is saved in SyncEngine
- **Developer extension points** — code-level filters for modifying payloads, blocking dispatches, and reacting to results
- **WooCommerce support** (optional) — triggers automations on WooCommerce events (customers, orders, products, product variations, coupons) when WooCommerce is active

---

## Requirements

- WordPress 6.0+
- An active and reachable [SyncEngine](https://syncengine.io) installation
- A SyncEngine API token with at least read access to automations and connections
- For common blueprints and automated mapping of event triggers to such endpoints you'll need the corresponding modules installed. [Please check our marketplace](https://marketplace.syncengine.io/)

---

## Installation

1. Upload the plugin folder to `wp-content/plugins/` and activate it, or install it via the Plugins screen.
2. Navigate to **Tools → SyncEngine** in the WordPress admin.
3. Enter your SyncEngine **Domain/Host** and **API Token**.
4. Save. The plugin will connect and begin discovering relevant automations automatically.

---

## Settings

All settings are stored under the WordPress option key `syncengine`.

### API Connection

| Field | Description |
|---|---|
| **Domain/Host** | Full URL of your SyncEngine instance (e.g. `https://syncengine.example.com`) |
| **Token** | SyncEngine API token used to authenticate requests |
| **Auth Header** | Optional custom auth header name (default: `Bearer token`) |

### Custom Hook Mappings

Map arbitrary WordPress action hooks to one or more automation endpoints. Each row defines:

| Column | Description |
|---|---|
| **Hook name** | The WordPress action hook to listen on (e.g. `save_post`, `acf/save_post`) |
| **Endpoints** | Automation endpoint(s) to trigger — selected from live endpoint list |
| **Priority** | WordPress hook priority (default: `10`) |
| **Accepted args** | Number of hook arguments forwarded to the payload (default: `99`) |

Rows can be added dynamically. Definitions are stored under `syncengine[hooks][custom]`.

---

## Admin Connector

Navigate to **Tools → SyncEngine** to access the connector UI.

### Tabs

The admin page is split into three tabs:

- **Connector** — connection settings, current API status, endpoint list, per-endpoint actions
- **Trigger Maps & Debug** — module-provided trigger map sections plus recent dispatch log tools
- **Custom Hooks Config** — custom WordPress hook-to-endpoint mappings with dynamic row addition

### Endpoint Status Behavior

Endpoint status is intentionally **not preloaded** for all rows. Instead:

- Use **Load status** in a specific endpoint row to fetch that endpoint's status via AJAX
- The request targets `admin-ajax.php` using the `syncengine_get_endpoint_status` action and a nonce check
- The status cell is updated inline without reloading the page
- The trace cell is populated with counters derived from the API response arrays: `running`, `scheduled`, and `queued`

This keeps the endpoint table responsive and avoids unnecessary status calls.

---

## WordPress Core Triggers

The plugin automatically detects and triggers SyncEngine automations for the following WordPress Core events.

### Supported Events

| Event | WordPress hook | SyncEngine blueprint class |
|---|---|---|
| New post | `wp_after_insert_post` | `SyncEngine/WordpressRestV2:NewPost` |
| Updated post | `wp_after_insert_post` | `SyncEngine/WordpressRestV2:UpdatedPost` |
| Deleted post | `before_delete_post` | `SyncEngine/WordpressRestV2:DeletedPost` |
| New term | `created_term` | `SyncEngine/WordpressRestV2:NewTerm` |
| Updated term | `edited_term` | `SyncEngine/WordpressRestV2:UpdatedTerm` |
| Deleted term | `delete_term` | `SyncEngine/WordpressRestV2:DeletedTerm` |
| New user | `user_register` | `SyncEngine/WordpressRestV2:NewUser` |
| Updated user | `profile_update` | `SyncEngine/WordpressRestV2:UpdatedUser` |
| Deleted user | `deleted_user` | `SyncEngine/WordpressRestV2:DeletedUser` |

Post revisions and autosaves are skipped. Payloads include an `id`, an `event` string, a `data` object (with meta), and a `request` object with raw hook arguments.

### Discovery

The plugin queries the SyncEngine API for all automations, then cross-references:

1. The automation's trigger blueprint class against the table above
2. The automation's connected WordPress connection against this site's URL

Only automations that match both criteria are registered. The result is cached in a WordPress transient for 5 minutes.

The cache is automatically cleared when:
- Settings are saved in the WordPress admin
- A WordPress connection is successfully tested in SyncEngine (via the "Connect" button)
- An automation using a WordPress trigger blueprint is saved in SyncEngine

---

## Custom Hook Mappings

Custom hook definitions stored in settings are registered with `add_action()` at plugin load. Each hook receives all its arguments; the plugin normalizes them and sends a payload to the configured endpoints.

Payload shape:

```json
{
  "event": "wp_custom_hook",
  "id": 123,
  "data": {
    "hook": "save_post",
    "args": [ ... ]
  },
  "request": {
    "hook": "save_post",
    "args": [ ... ],
    "arg_count": 3
  }
}
```

Hook definitions can also be added or filtered in code:

```php
add_filter( 'syncengine_wp_custom_hook_definitions', function( $definitions, $settings ) {
    $definitions[] = [
        'hook'          => 'my_custom_action',
        'trigger'       => 'hook:my_custom_action',
        'endpoints'     => [ 'my-endpoint-slug' ],
        'priority'      => 10,
        'accepted_args' => 2,
    ];
    return $definitions;
}, 10, 2 );
```

---

## REST API Endpoints

The plugin registers the following endpoints under the `syncengine/v1` namespace:

| Method | Route | Auth | Description |
|---|---|---|---|
| `GET` | `/wp-json/syncengine/v1/status` | None | Returns plugin status (`active`) |
| `POST` | `/wp-json/syncengine/v1/refresh` | None | Clears the trigger endpoint map cache. Throttled (see below). |

The `/refresh` endpoint is called automatically by SyncEngine when a related connection or automation is saved. It is intentionally unauthenticated — the only effect is clearing internal transient cache, which causes a fresh lookup the next time a WordPress event fires. Installing the plugin is optional; if not present, SyncEngine falls back to the existing cache timeout.

#### Refresh throttling

Requests are bucketed by whether SyncEngine can identify itself as a known trusted connection:

| Request type | Throttle window | Transient key |
|---|---|---|
| Anonymous / unrecognised | 5 seconds | `syncengine_refresh_throttle` |
| Trusted (known connection ref) | 1 second | `syncengine_refresh_throttle_trusted` |

SyncEngine sends its connection ref in the `X-SyncEngine-Connection` request header. The plugin verifies the value against the set of known connection refs it captured the last time the trigger endpoint map was built. Both windows are filterable:

```php
// Change the anonymous throttle window (seconds)
add_filter( 'syncengine_refresh_throttle_ttl', fn() => 10 );

// Change the trusted throttle window (seconds)
add_filter( 'syncengine_refresh_trusted_throttle_ttl', fn() => 2 );
```

### Enhanced REST API Query Params

The following query parameters are added to all post type, taxonomy, user, and attachment REST API endpoints:

| Parameter | Description |
|---|---|
| `meta_query` | Array-format meta query builder |
| `meta_key` | Filter by meta key |
| `meta_value` | Filter by meta value |
| `meta_compare` | Comparison operator for meta value |

---

## Developer Filters

### Trigger Layer (`AbstractPlatformService`)

These filters run before endpoints are dispatched. All hooks exist in four variants: global, per-source, per-trigger, and per-source+trigger. Tags are normalized (lowercase, `[a-z0-9_]`).

**Payload filters** — modify the payload before dispatch:
```php
add_filter( 'syncengine_trigger_payload', function( $payload, $meta ) { ... }, 10, 2 );
add_filter( 'syncengine_trigger_payload_{source}', ... );
add_filter( 'syncengine_trigger_payload_{trigger}', ... );
add_filter( 'syncengine_trigger_payload_{source}_{trigger}', ... );
```

**Gate filters** — return `false` to cancel dispatch entirely:
```php
add_filter( 'syncengine_trigger_should_dispatch', function( $should, $meta, $payload ) { ... }, 10, 3 );
add_filter( 'syncengine_trigger_should_dispatch_{source}', ... );
add_filter( 'syncengine_trigger_should_dispatch_{trigger}', ... );
add_filter( 'syncengine_trigger_should_dispatch_{source}_{trigger}', ... );
```

**Post-dispatch actions** — react after all endpoints have been triggered:
```php
add_action( 'syncengine_trigger_dispatched', function( $results, $meta, $payload ) { ... }, 10, 3 );
add_action( 'syncengine_trigger_dispatched_{source}', ... );
add_action( 'syncengine_trigger_dispatched_{trigger}', ... );
add_action( 'syncengine_trigger_dispatched_{source}_{trigger}', ... );
```

The `$meta` array contains `source`, `trigger`, and `context` keys.

### Dispatch Layer (`EndpointDispatcherService`)

These filters run per HTTP dispatch. All hooks accept an optional `_{trigger}` suffix.

| Filter/Action | Description |
|---|---|
| `syncengine_dispatch_payload` | Modify payload for all endpoints |
| `syncengine_dispatch_endpoints` | Modify the list of endpoints to call |
| `syncengine_dispatch_payload_for_endpoint` | Modify payload for a single endpoint |
| `syncengine_dispatch_result` | Modify/inspect the result for a single endpoint |
| `syncengine_dispatch_completed` | Fires after all endpoints are dispatched |

### Module-specific

| Filter | Description |
|---|---|
| `syncengine_wp_custom_hook_definitions` | Add, remove, or modify custom hook definitions |

---

## Source Identifiers

The `source` value in `$meta` identifies which module fired the trigger:

| Source | Module |
|---|---|
| `wordpress_core` | WordPress Core (posts, terms, users, custom hooks) |
| `woocommerce` | WooCommerce (orders, customers, products) |

---

## WooCommerce

When WooCommerce is active, the plugin registers additional triggers for all major WooCommerce object types.

### Supported Events

| Object | Event | SyncEngine blueprint class |
|---|---|---|
| Customer | New | `SyncEngine/WooCommerceRestV3:NewCustomer` |
| Customer | Updated | `SyncEngine/WooCommerceRestV3:UpdatedCustomer` |
| Customer | Deleted | `SyncEngine/WooCommerceRestV3:DeletedCustomer` |
| Order | New | `SyncEngine/WooCommerceRestV3:NewOrder` |
| Order | Updated | `SyncEngine/WooCommerceRestV3:UpdatedOrder` |
| Order | Deleted | `SyncEngine/WooCommerceRestV3:DeletedOrder` |
| Product | New | `SyncEngine/WooCommerceRestV3:NewProduct` |
| Product | Updated | `SyncEngine/WooCommerceRestV3:UpdatedProduct` |
| Product | Deleted | `SyncEngine/WooCommerceRestV3:DeletedProduct` |
| Product variation | New | `SyncEngine/WooCommerceRestV3:NewProductVariation` |
| Product variation | Updated | `SyncEngine/WooCommerceRestV3:UpdatedProductVariation` |
| Product variation | Deleted | `SyncEngine/WooCommerceRestV3:DeletedProductVariation` |
| Coupon | New | `SyncEngine/WooCommerceRestV3:NewCoupon` |
| Coupon | Updated | `SyncEngine/WooCommerceRestV3:UpdatedCoupon` |
| Coupon | Deleted | `SyncEngine/WooCommerceRestV3:DeletedCoupon` |

Payloads match the WooCommerce REST API response shape (`GET /wp-json/wc/v3/{resource}/{id}` with `context=edit`).
