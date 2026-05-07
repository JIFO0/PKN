# PKN Backend — Comprehensive Active Documentation

> **Audience:** Super Admins, Science Community Admins, maintainers, and future developers.
> **Current code version documented:** `Alpha 0.956` / `SC_PLUGIN_VERSION = 0.956`.
> **Documentation refresh date:** 2026-05-07.
> **Scope:** Public discovery, shortcode pages, admin surfaces, database model, imports/exports, update system, statistics, forum, media, permissions, and internal plugin flow.

---

## 1) Platform Purpose

PKN Backend is a WordPress plugin for managing and presenting academic science communities. It has grown beyond a simple directory into a public catalog plus an operational admin system.

The plugin supports:

- Public discovery through search, results, list, and detail pages.
- Community profile maintenance by assigned community admins.
- Global governance by superadmins.
- Bulk data import/export and import/update history.
- Community lifecycle states such as active, limited, suspended, inactive, and archived.
- Statistics for views, social clicks, search terms, and tag usage.
- Internal forum communication for superadmins and community admins.
- Join/application forms, contact requests, media/logo upload, and categorized community images.
- WordPress-admin pages and frontend shortcode-admin pages.
- PL/EN language support and language toggle shortcode.
- GitHub-backed update checking and manual plugin update flow.

The platform should be treated as a **public register + operational governance system**. Public data quality, import hygiene, role correctness, and update history are all part of the core product.

---

## 2) High-Level Architecture

### 2.1 Entry point

`PKN-backend.php` is the main plugin file. It defines plugin constants, loads modules, registers activation/deactivation hooks, creates/repairs required pages, registers shortcodes, registers admin menus, handles admin-post/AJAX actions, creates database tables, exports CSV files, and integrates the updater.

Key constants:

| Constant | Meaning |
|---|---|
| `SC_PLUGIN_PATH` | Filesystem path to the plugin directory. |
| `SC_PLUGIN_URL` | Public URL to plugin assets. |
| `SC_PLUGIN_VERSION` | Current version string used by the plugin. |
| `SC_VERSION` | Secondary version constant with the same numeric value. |
| `SC_DEBUG_MODE` | Debug mode flag; currently enabled in code. |

### 2.2 Included modules

| File | Responsibility |
|---|---|
| `includes/functions.php` | Public/community data functions: search, retrieval, tag normalization, CRUD helpers, faculty/status helpers, shortcode-page lookup. |
| `includes/lang.php` | Language initialization, selected language, translation loading, `sc_t()` lookup, language toggle shortcode. |
| `includes/admin-functions.php` | Permission checks, save logic, role creation, upload limits, logo handling, import parsing, import logs, update history, community images, contact requests. |
| `includes/auth.php` | Superadmin checks, community-admin role checks, assigned community lookup, edit-request verification, role assignment/removal, admin access checks, login helpers. |
| `includes/error-logger.php` | Error logging support. |
| `includes/statistics.php` | Statistics table creation and event/dashboard/statistics queries. |
| `includes/forum.php` | Internal forum tables, access rules, thread/message logic, AJAX handlers. |
| `includes/updater.php` | GitHub/WordPress update integration, manifest fetch, plugin information popup, manual update handler. |

### 2.3 Template layer

Templates are included by shortcodes or WordPress-admin render functions. The most important templates are:

| Template | Main use |
|---|---|
| `templates/search-form.php` | Public search/filter form. |
| `templates/search-results.php` | Public search result cards and application/contact surfaces. |
| `templates/community-list.php` | Public browsable list of communities. |
| `templates/community-detail.php` | Public community detail page. |
| `templates/admin-panel.php` | Frontend admin hub. |
| `templates/add-community.php` | Community creation form. |
| `templates/edit-community.php` | Community edit form with links, status, tags, images, contact/request sections. |
| `templates/admin-communities-list.php` | WordPress-admin community list with bulk actions and Facebook pull. |
| `templates/admin-import.php` | Import/export, plugin update card, update history, import logs. |
| `templates/manage-users.php` | User-to-community administration. |
| `templates/contact-requests.php` | Contact/request moderation. |
| `templates/dashboard.php` | Activity/data-quality dashboard. |
| `templates/community-statistics.php` | Statistics reporting UI. |
| `templates/forum.php` | Internal forum UI. |
| `templates/admin-tags-faculties.php` | Taxonomy management. |
| `templates/debug-info.php` | Debug/support information. |

### 2.4 Assets

| Asset area | Files |
|---|---|
| Public styling | `assets/css/style.css`, `search.css`, `results.css`, `community-list.css`, `community-detail.css` |
| Admin styling | `assets/css/admin.css`, `admin-panel.css` |
| Shared/layout styling | `assets/css/globals.css` |
| Forum styling | `assets/css/forum.css` |
| JavaScript | `assets/js/script.js`, `admin-script.js`, `forum.js`, `layout-fixes.js` |
| Images | `assets/images/underline.svg` |

---

## 3) Activation, Schema Upgrade, and Required Pages

### 3.1 Activation flow

On activation, the plugin runs:

1. `sc_create_tables()` to create/update custom tables.
2. `sc_insert_default_faculties()` to seed known faculties.
3. `sc_ensure_required_pages()` to create or repair plugin pages.
4. `flush_rewrite_rules()`.

On deactivation, it flushes rewrite rules.

### 3.2 Schema upgrade flow

`sc_maybe_upgrade_schema()` runs on `admin_init`. It compares the stored `sc_schema_version` with the current schema marker. If the marker differs, it calls `sc_create_tables()` and updates the option. This allows schema additions after activation.

Current schema marker in code:

```text
2026-05-06-import-history-links
```

### 3.3 Required pages and shortcodes

The plugin creates or repairs these pages by shortcode:

| Page title | Slug | Shortcode |
|---|---|---|
| PKN Search | `/sc-search` | `[science_communities_search]` |
| PKN Results | `/results` | `[science_communities_results]` |
| PKN Detail | `/detail` | `[science_community_detail]` |
| PKN Admin | `/sc-admin` | `[science_communities_admin]` |
| PKN Communities List | `/sc-list` | `[science_communities_list]` |
| Community Statistics | `/community-statistics` | `[science_communities_statistics]` |
| PKN Forum | `/sc-forum` | `[science_communities_forum]` |

Additional registered shortcodes:

| Shortcode | Purpose |
|---|---|
| `[science_communities_add]` | Add-community form. |
| `[science_communities_debug]` | Debug info surface. |
| `[sc_lang_header_toggle]` | Language toggle for header/layout use. |

### 3.4 Menu hiding for guests

Logged-out visitors should not see protected navigation items. The plugin filters menu/page listings and hides forum/statistics/admin-related entries for guests.

Protected slugs include:

- `sc-forum`
- `community-statistics`
- `sc-admin`

---

## 4) Roles and Permission Model

### 4.1 Superadmin

A superadmin is a WordPress user with the `superadmin` role. Superadmins can:

- Access all admin surfaces.
- Create new communities.
- Edit any community.
- Archive/unarchive communities.
- Run imports and exports.
- Manage user/community assignments.
- Use bulk actions.
- Access global statistics and forum.
- Run plugin update flow.

### 4.2 Community admin

Community admins are represented by WordPress roles using this naming pattern:

```text
{community_id}-admin
```

Example:

```text
ABCDE-admin
```

These roles are created when communities are created or when assignment helpers require them. Community admins can edit assigned communities only and can access admin/forum surfaces when `sc_user_can_edit_any_community()` returns true.

### 4.3 Explicit relationship table

The database also includes `wp_science_community_user_roles`:

| Column | Meaning |
|---|---|
| `user_id` | WordPress user ID. |
| `community_id` | 5-character community ID. |
| `role` | Relationship role value. |

This table supports more explicit assignment tracking in addition to WordPress role names.

### 4.4 Admin access checks

Important permission helpers:

| Function | Purpose |
|---|---|
| `sc_is_superadmin()` | Checks global superadmin access. |
| `sc_is_community_admin($community_id)` | Checks whether user administers a specific community. |
| `sc_get_user_admin_communities()` | Returns communities connected with current user. |
| `sc_user_can_edit_community($community_id)` | Main edit permission gate. |
| `sc_user_can_edit_any_community()` | Gate for admin panel/forum access. |
| `sc_can_access_admin_panel()` | Frontend admin access helper. |
| `sc_verify_community_edit_request()` | Nonce + permission validation for edit requests. |

---

## 5) Database Tables

The plugin manages multiple custom tables. The table prefix below is shown as `wp_`, but actual installs use the site’s `$wpdb->prefix`.

### 5.1 `wp_science_communities`

Core community table.

| Column | Type / role |
|---|---|
| `id` | Internal auto-increment ID. |
| `community_id` | Public/internal 5-character ID, unique. |
| `name` | Community name, required. |
| `shortdescription` | Short summary. |
| `description` | Long rich description. |
| `webpage` | Website URL. |
| `facebook` | Facebook URL. |
| `instagram` | Instagram URL. |
| `tiktok` | TikTok URL. |
| `discord` | Discord URL. |
| `other_links` | Newline-normalized list of extra links. |
| `logo` | Logo URL. |
| `contact_email` | Application/contact email. |
| `open_for_applications` | Boolean-like flag. |
| `faculty_id` | Linked faculty. |
| `status` | `active`, `limited`, `suspended`, or `inactive`. |
| `is_archived` | Archive flag controlled by superadmin. |
| `last_verified_at` | Verification timestamp. |
| `created_at` | Creation timestamp. |
| `updated_at` | Auto-updated timestamp. |

### 5.2 Taxonomy tables

| Table | Purpose |
|---|---|
| `wp_science_faculties` | Faculty names. |
| `wp_science_tags` | Tag names. |
| `wp_science_community_tags` | Many-to-many relationship between community IDs and tag IDs. |

Default faculties inserted by activation include UG faculties plus cross-faculty/inter-university/unknown values.

### 5.3 Governance and audit tables

| Table | Purpose |
|---|---|
| `wp_science_community_user_roles` | Explicit user/community relationships. |
| `wp_science_communities_audit` | Generic change audit trail. |
| `wp_science_communities_error_log` | Error logging table. |
| `wp_science_communities_update_history` | Import/update history visible in admin import page. |
| `wp_science_contact_requests` | Requests from community admins to superadmins. |

### 5.4 Interaction and media tables

| Table | Purpose |
|---|---|
| `wp_science_community_applications` | Public join/application submissions. |
| `wp_science_community_uploads` | File upload tracking and daily quota support. |
| `wp_science_community_images` | Categorized images, such as event/team/gallery. |
| `wp_science_community_statistics` | View/social/search statistics. |
| `wp_science_forum_threads` | Forum thread metadata. |
| `wp_science_forum_messages` | Forum messages and optional image URLs. |

---

## 6) Public Discovery Flow

### 6.1 Search form

The search form lets users enter a text query and select filters. Relevant filter dimensions include:

- Tags.
- Faculties.
- Open-for-applications only.

The form forwards data to the results page.

### 6.2 Search query internals

`sc_search_communities()` accepts:

```php
sc_search_communities($search_term = '', $tags = array(), $fuzzy = true, $faculties = array(), $open_for_applications = false)
```

Search behavior:

1. If a search term is provided and fuzzy mode is enabled for terms of at least 3 characters, the plugin loads communities and performs PHP-side fuzzy matching using Levenshtein distance plus substring checks.
2. Otherwise, it uses SQL `LIKE` against `name`, `shortdescription`, and `description`.
3. Tags are normalized and can be IDs or names.
4. Multiple selected tags are treated as an “must have all selected tags” filter using a grouped subquery.
5. Faculty IDs are applied as filters.
6. Open-for-applications can restrict results to communities that accept applications.
7. Results are returned ordered for display by templates.

### 6.3 Detail page

The detail page resolves a community ID from either shortcode attributes or the `id` URL parameter.

Expected URL style:

```text
/detail?id=ABCDE
```

A strong public detail profile should include:

- Name.
- Short description.
- Long description.
- Logo.
- Website and social links.
- Other links.
- Faculty.
- Tags.
- Operational status.
- Contact/application path.
- Community images if provided.

### 6.4 Statistics events in public flow

Public actions can feed statistics:

| Event | Function |
|---|---|
| Community view | `sc_track_community_view($community_id)` |
| Social link click | `sc_track_social_click($community_id, $platform)` |
| Search term that found communities | `sc_track_search_term_for_results($search_term, $communities)` |

---

## 7) Community Lifecycle and Status Model

### 7.1 Status values

The code supports these status values in the main table:

| Status | Meaning |
|---|---|
| `active` | Community is active. |
| `limited` | Community has limited/partial activity. |
| `suspended` | Community is suspended or not operating normally. |
| `inactive` | Community is inactive. |

### 7.2 Archive flag

`is_archived` is separate from `status`. This means a record can have a status and also be archived. Archive changes are reserved for superadmins in `sc_save_community()`.

### 7.3 Import status mapping

Import accepts both numeric and textual statuses:

| Import value | Stored status | Archive flag |
|---|---|---|
| Empty | `active` | `0` |
| Negative number, e.g. `-1` | `suspended` | `1` |
| `0` | `suspended` | `0` |
| `0.1` through `0.9` | `limited` | `0` |
| `1` or greater | `active` | `0` |
| `active` | `active` | `0` |
| `limited` | `limited` | `0` |
| `suspended` | `suspended` | `0` |
| `inactive` | `inactive` | `0` |
| Unknown text | `active` | `0` |

### 7.4 Export status mapping

Community CSV export writes status values as:

| Stored condition | Export value |
|---|---|
| Archived | `-1` |
| Active | `1` |
| Limited | `0.5` |
| Suspended or other non-active | `0` |

---

## 8) Admin Surfaces

### 8.1 Frontend admin panel (`/sc-admin`)

The shortcode admin panel requires a logged-in user with edit access. It routes by the `action` query parameter.

Common routes:

| URL pattern | Purpose |
|---|---|
| `/sc-admin` | Admin overview/dashboard surface. |
| `/sc-admin?action=add` | Add community, superadmin only. |
| `/sc-admin?action=edit&id=ABCDE` | Edit community. |

### 8.2 WordPress admin menu

The plugin registers a PKN Communities admin menu with subpages such as:

- Communities.
- Import/Export.
- Tags & Faculties.
- Activity Dashboard.
- Community Statistics.
- User Management.
- Contact Requests.
- Social Sync Settings.

### 8.3 Bulk community actions

The WordPress-admin communities list supports selecting multiple communities and applying:

| Bulk action | Effect |
|---|---|
| Delete | Deletes selected communities. |
| Archive | Sets `is_archived = 1`. |
| Unarchive | Sets `is_archived = 0`. |
| Status | Sets selected status. |
| Faculty | Changes faculty. |
| Add tags | Merges selected tags into each community. |
| Remove tags | Removes selected tags from each community. |

### 8.4 Facebook pull / social sync

The admin UI includes Facebook pull controls. The updater/social settings page stores a Facebook app access token in `sc_facebook_app_token`. AJAX action `sc_pull_facebook_data` fetches Facebook data when configured.

---

## 9) Community Add/Edit Data Model

Community save logic sanitizes and persists:

| Field | Sanitization / handling |
|---|---|
| `name` | `sanitize_text_field`; required. |
| `shortdescription` | `sanitize_textarea_field`. |
| `description` | `wp_kses_post` for rich allowed HTML. |
| `webpage`, `facebook`, `instagram`, `tiktok`, `discord`, `logo` | `esc_url_raw`. |
| `other_links` | Normalized to unique newline-separated URLs. |
| `contact_email` | `sanitize_email`. |
| `open_for_applications` | Boolean-like integer. |
| `faculty_id` | Integer or null. |
| `status` | Sanitized text/status key. |
| `is_archived` | Boolean-like integer; superadmin only. |
| `tags` | Normalized and saved through relationship table. |
| `event_images`, `team_images`, `gallery_images` | Saved into `science_community_images` by category. |

New communities are superadmin-only. When a new community is created, the plugin generates a random 5-letter `community_id` if needed and registers a dedicated community admin role.

---

## 10) Imports — Supported Table Formats and Internal Flow

### 10.1 Important clarification

The admin page labels the upload as “Excel File” and allows `.csv`, `.xlsx`, and `.xls` in the file picker. Internally, the current importer uses PHP `fgetcsv()`. Therefore:

- **Recommended:** UTF-8 CSV.
- **Best practical delimiter:** pipe (`|`) because descriptions and links often contain commas.
- **Supported detected delimiters:** comma (`,`), semicolon (`;`), tab, and pipe (`|`).
- **BOM support:** UTF-8 BOM is stripped/handled.
- **Excel files:** If an `.xlsx` or `.xls` is uploaded but is not actually readable as CSV text, the current importer should not be expected to parse workbook sheets correctly. Convert to CSV first for reliable imports.

### 10.2 Supported import headers

The first row must contain headers. Headers are normalized by:

1. Removing BOM.
2. Lowercasing.
3. Trimming whitespace.
4. Replacing spaces with underscores.
5. Applying aliases.

Canonical supported columns:

| Column | Required | Description |
|---|---:|---|
| `community_id` | Recommended | Five-character community ID. If present and existing, updates that community. If exactly `0`, row is skipped. Legacy rows without ID may match by name. |
| `name` | Yes | Community name. Rows without a name are skipped. |
| `shortdescription` | No | Short 1–2 sentence description. |
| `description` | No | Long description; allowed HTML is sanitized. |
| `faculty` | No | Faculty name. Existing faculty is reused; missing faculty is created. |
| `webpage` | No | Website URL. |
| `facebook` | No | Facebook URL. |
| `instagram` | No | Instagram URL. |
| `tiktok` | No | TikTok URL. |
| `discord` | No | Discord URL. |
| `other_links` | No | Extra links. Comma/newline separated values are normalized into unique URLs. |
| `contact_email` | No | Contact/application email. |
| `logo` | No | Logo image URL. |
| `tags` | No | Tag list. Separators: `|`, `,`, `;`, `/`. |
| `status` | No | Numeric or textual status; see status mapping table. |

### 10.3 Header aliases

| Alias | Canonical column |
|---|---|
| `nazwa` | `name` |
| `nazwa_kola` | `name` |
| `community_name` | `name` |
| `short_description` | `shortdescription` |
| `opis_krotki` | `shortdescription` |
| `opis` | `description` |
| `faculty_name` | `faculty` |
| `wydzial` | `faculty` |
| `www` | `webpage` |
| `strona` | `webpage` |
| `strona_www` | `webpage` |
| `inne` | `other_links` |
| `other` | `other_links` |
| `mail` | `contact_email` |
| `email` | `contact_email` |

### 10.4 Recommended import table format

Pipe-delimited UTF-8 CSV:

```csv
community_id|name|shortdescription|description|faculty|webpage|facebook|instagram|tiktok|discord|other_links|contact_email|logo|tags|status
ABCDE|Example Science Club|Short description|Long description with optional HTML|Wydział Chemii|https://example.edu|https://facebook.com/example|https://instagram.com/example|https://tiktok.com/@example|https://discord.gg/example|https://example.edu/docs, https://example.edu/events|club@example.edu|https://example.edu/logo.png|chemistry; research; outreach|1
```

### 10.5 Export-compatible format

The built-in community export writes these columns with pipe (`|`) delimiter:

```text
community_id|name|shortdescription|description|faculty|webpage|facebook|instagram|discord|inne|mail|logo|tags|status
```

Notes:

- Export currently includes `discord` but not `tiktok`.
- Export uses `inne` and `mail`, which are accepted aliases on re-import.
- Export status is numeric (`-1`, `0`, `0.5`, `1`).
- Tags are exported as comma-separated names, which re-import supports.

### 10.6 Import processing flow

The importer performs this sequence:

1. Logs import start to `uploads/sc-community-import.log` and PHP error log.
2. Removes broken tag records containing semicolons from earlier bad imports.
3. Verifies file exists and opens it as text.
4. Handles UTF-8 BOM.
5. Detects delimiter by checking comma, semicolon, tab, and pipe frequency.
6. Reads first row as headers and normalizes headers.
7. Skips completely empty rows.
8. Builds associative row data from headers.
9. Rejects `community_id = 0` rows.
10. Rejects rows without `name`.
11. Resolves or creates faculty.
12. Parses status.
13. Builds sanitized community data.
14. Updates existing community by `community_id` if present.
15. If no ID match exists, tries to match existing community by `name` for legacy imports.
16. Creates a new community if no existing record is found.
17. Registers community admin role for newly created communities.
18. Parses and saves tags.
19. Logs summary counts.
20. Records update history with actor name, filename, created/updated/skipped counts, and notes.

### 10.7 Import skip rules

Rows are skipped when:

- Every cell is empty.
- `community_id` is exactly `0`.
- `name` is empty.
- A save/update operation fails permission or database validation.

### 10.8 Import logging and history

Import logging writes to:

```text
wp-content/uploads/sc-community-import.log
```

The import admin page shows the last 20 log lines and the last 25 update-history records.

Update history records are stored in:

```text
wp_science_communities_update_history
```

The history page can be cleared, but clearing itself creates a `history_cleared` history record when the clear handler runs.

---

## 11) Exports

### 11.1 Community export

Trigger:

```text
admin.php?page=pkn-import&sc_export=1
```

Protected by nonce and superadmin permission.

Output:

```text
pkn-communities-YYYY-MM-DD.csv
```

Delimiter:

```text
|
```

Columns:

```text
community_id, name, shortdescription, description, faculty, webpage, facebook, instagram, discord, inne, mail, logo, tags, status
```

### 11.2 Accounts export

Trigger parameter:

```text
sc_export_accounts=1
```

Output:

```text
pkn-accounts-YYYY-MM-DD.csv
```

Columns:

```text
username|email|community_id|password
```

The password column is intentionally blank; this export is for mapping/admin onboarding, not credential disclosure.

---

## 12) Statistics and Activity Dashboard

### 12.1 Event table

`includes/statistics.php` creates `wp_science_community_statistics` with event data.

Tracked event types include:

| Event type | Meaning |
|---|---|
| `view` | Community detail view. |
| `social_click` | Social/platform link click. |
| `search_term` | Search term associated with returned communities. |

### 12.2 Dashboard data

Dashboard data can include:

- Recent community updates.
- Most viewed communities.
- Communities without logos.
- Communities missing descriptions.
- Tag usage statistics.

### 12.3 Statistics data

Statistics reporting includes:

- Views per community.
- Social clicks grouped by platform.
- Search terms grouped by community.
- Tag popularity.
- Optional scoping to selected community IDs for community-admin views.

Operational use:

- Find communities with poor discoverability.
- Identify popular tags and top search terms.
- Detect communities that need better descriptions/logos.
- Monitor whether public engagement justifies taxonomy or content cleanup.

---

## 13) Forum System

### 13.1 Purpose

The forum is an internal communication layer for superadmins and science-community admins. It is not a public anonymous forum.

### 13.2 Access

Forum access requires:

- Logged-in user.
- Ability to edit at least one community or superadmin access.

### 13.3 Tables

| Table | Purpose |
|---|---|
| `wp_science_forum_threads` | Thread title, creator, general flag, closed flag, activity timestamps. |
| `wp_science_forum_messages` | Thread messages, author ID, text, optional image URL, timestamps. |

### 13.4 General Chat

The plugin ensures a General Chat thread exists through `sc_forum_ensure_general_thread()`.

### 13.5 Forum AJAX actions

The plugin registers AJAX handlers for:

- Get threads.
- Get messages.
- Create thread.
- Post message.
- Edit message.
- Delete message.
- Close thread.
- Report message.
- Upload image.
- Delete thread.

Moderation expectations:

- Superadmins should treat reports seriously.
- Closed threads should be used for resolved or locked discussions.
- Image upload should be monitored for quota and inappropriate content.

---

## 14) Media, Logo, and Image Galleries

### 14.1 Upload tracking

Uploads are tracked in `wp_science_community_uploads` with:

- Filename.
- File path.
- File size.
- Uploader ID.
- Upload timestamp.

### 14.2 Upload permission/quota

`sc_can_user_upload($user_id)` checks upload allowance. Superadmins are treated differently from regular community admins, and the edit UI can show daily upload usage.

### 14.3 Logo handling

Logo URLs are stored directly on the community record. Upload AJAX/action support is connected through admin scripts and `sc_ajax_upload_logo()` / `sc_handle_logo_upload()`.

### 14.4 Categorized images

Community images are stored in `wp_science_community_images` with:

| Field | Purpose |
|---|---|
| `community_id` | Owning community. |
| `category` | Image category, e.g. `event`, `team`, `gallery`. |
| `image_url` | Stored image URL. |
| `sort_order` | Display ordering. |

Edit flow supports arrays such as:

- `event_images`
- `team_images`
- `gallery_images`

---

## 15) Contact Requests and Applications

### 15.1 Community applications

Public users can submit applications/join forms tied to a community. Stored data includes:

- Community ID.
- Applicant name.
- Applicant email.
- Applicant info.
- Additional contact field.
- Optional WordPress user ID.
- Read flag.
- Created timestamp.

### 15.2 Contact requests

Community admins can create contact requests to superadmins. Stored data includes:

- Community ID.
- Requester ID.
- Message.
- Status, default `open`.
- Created timestamp.

### 15.3 Operational guidelines

- Superadmins should review requests regularly.
- Community removal requests should be verified before destructive action.
- Application emails/contact paths must be kept current.

---

## 16) Language Support

Language support lives in:

- `includes/lang.php`
- `lang/pl.php`
- `lang/en.php`

Core language helpers:

| Function | Purpose |
|---|---|
| `sc_init_language()` | Initializes language state. |
| `sc_get_lang()` | Returns active language. |
| `sc_load_translations()` | Loads translation arrays. |
| `sc_t($key)` | Returns translated text for key. |
| `sc_render_lang_toggle()` | Renders language toggle. |
| `sc_render_lang_header_toggle_shortcode()` | Shortcode wrapper. |

Shortcode:

```text
[sc_lang_header_toggle]
```

Documentation/content governance:

- Keep PL/EN text aligned.
- When adding a UI label, add both translation keys.
- Forum/statistics/admin areas should expose language controls where practical.

---

## 17) Updater and Build Information

### 17.1 Version source

The plugin header currently declares:

```text
Version: Alpha 0.956
```

Constants declare:

```text
SC_PLUGIN_VERSION = 0.956
SC_VERSION = 0.956
```

### 17.2 GitHub updater

Updater constants point to:

| Constant | Value / meaning |
|---|---|
| `SC_UPDATER_GITHUB_OWNER` | `JIFO0` |
| `SC_UPDATER_GITHUB_REPO` | `PKN` |
| `SC_UPDATER_SLUG` | `pkn-backend` |
| `SC_UPDATER_FALLBACK_ZIP_NAME` | `PKN.zip` |
| `SC_UPDATER_RELEASE_API_URL` | Latest GitHub release API URL. |

### 17.3 Update flow

The updater:

1. Hooks into WordPress plugin update transients.
2. Fetches the GitHub latest release/build manifest.
3. Normalizes candidate version strings.
4. Compares candidate version with installed version.
5. Adds update data to the WordPress plugin update UI when newer.
6. Provides plugin info popup data.
7. Handles install-directory normalization after upgrade.
8. Exposes a manual update button in admin UI.

### 17.4 Local build metadata

`builds/latest.json` contains packaged build metadata such as:

- Name.
- Slug.
- Version.
- Build timestamp.
- Package URL.
- Details URL.
- WordPress/PHP requirements.
- Changelog text.

---

## 18) Security and Operational Safety

### 18.1 Required practices

- Keep nonce validation on all state-changing forms.
- Keep permission checks before import/export/update/bulk actions.
- Do not allow community admins to edit archive state unless explicitly intended.
- Keep URL/email fields sanitized.
- Treat rich descriptions as sanitized HTML only.
- Check uploaded file type/size and daily quota behavior.
- Do not run imports without backups.
- Do not run plugin updates without confirming the build ZIP is correct.

### 18.2 Current sensitive handlers

Important state-changing handlers include:

- `sc_handle_add_community`
- `sc_handle_edit_community`
- `sc_handle_excel_import`
- `sc_handle_communities_export`
- `sc_handle_accounts_export`
- `sc_handle_bulk_delete`
- `sc_handle_assign_user_to_community`
- `sc_handle_update_admin_profile`
- `sc_handle_request_community_removal`
- `sc_handle_submit_general_request`
- `sc_handle_submit_contact_request`
- `sc_handle_submit_join_application`
- `sc_handle_manual_plugin_update`
- Forum create/edit/delete/close/report/upload AJAX actions

### 18.3 Debug mode warning

`SC_DEBUG_MODE` is currently set to `true` in code. Production deployments should review whether this should be disabled or guarded.

---

## 19) Operational Playbooks

### 19.1 Superadmin daily checklist

- Check admin dashboard for missing logos/descriptions and recent changes.
- Review open contact requests.
- Review new applications if the site receives them.
- Spot-check public search and detail pages.
- Check import/update history after any maintenance.
- Review forum reports or urgent messages.

### 19.2 Superadmin weekly checklist

- Audit inactive/suspended/limited statuses.
- Review archived communities and reasons.
- Check broken/obsolete website and social links.
- Verify user-community assignments.
- Export communities as backup before large edits.
- Review top searches and low/no-engagement communities.

### 19.3 Superadmin monthly checklist

- Run a full data quality review.
- Clean taxonomy duplication in tags/faculties.
- Review GitHub update status and changelog/build validity.
- Test import with a small sample file.
- Test restore/rollback process for backups.
- Update `overview v2.txt` and this document when functionality changes.

### 19.4 Community admin checklist

- Keep name and descriptions current.
- Keep logo and images updated.
- Verify official links and contact email.
- Update open-for-applications status.
- Ask superadmins for archive/removal/status governance decisions.
- Use forum/contact requests for platform issues.

### 19.5 Public-user quality principles

A public visitor should always see:

- What the community does.
- Whether it is currently active/open/limited/suspended.
- Which faculty/tags it belongs to.
- How to contact it or follow official channels.
- Enough detail to decide whether to join or learn more.

---

## 20) Troubleshooting Matrix

### 20.1 Add/edit saves redirect to blank `admin-post.php`

Check:

- Form `action` points to `admin-post.php`.
- Hidden `action` value matches registered handler.
- Nonce field name and nonce action match validation.
- Current user has required role/capability.
- PHP fatal errors in server logs.
- Output buffering/BOM/whitespace warnings.

### 20.2 Import creates wrong or empty records

Check:

- File is real UTF-8 CSV, not binary `.xlsx`.
- First row has supported headers.
- Delimiter is one of comma, semicolon, tab, pipe.
- Required `name` is present.
- `community_id` is not `0`.
- Descriptions containing delimiters are quoted correctly.
- Import log in `uploads/sc-community-import.log`.
- Update history counts for skipped rows.

### 20.3 Tags look broken after import

Check:

- Tags are split with `|`, comma, semicolon, or slash.
- Tag names are not accidentally entire semicolon strings from an older bad import.
- `sc_cleanup_broken_semicolon_tags()` runs at import start and removes tag records containing semicolons.

### 20.4 Logo/upload freezes

Check:

- Browser network tab for AJAX response.
- WordPress AJAX URL and nonce localization.
- PHP upload limits: `upload_max_filesize`, `post_max_size`, `max_file_uploads`.
- Web server temp directory permissions.
- File MIME/type constraints.
- Upload quota from `sc_can_user_upload()`.
- Hosting differences between XAMPP and production.

### 20.5 Forum messages fail

Check:

- User is logged in.
- User has superadmin or community-admin access.
- AJAX nonce/config is present.
- Thread is not closed.
- Message rate limit from forum posting helper.
- PHP/JS console errors.

### 20.6 Statistics missing

Check:

- Statistics table exists.
- Event tracking functions are called by templates/scripts.
- AJAX/social click handlers are wired.
- Filters by community IDs are not excluding current user data.

### 20.7 Plugin update unavailable

Check:

- Network access from WordPress host to GitHub.
- Latest release/build manifest exists.
- Package URL points to downloadable ZIP.
- Version comparison recognizes the new version as newer.
- WordPress filesystem credentials/permissions allow plugin upgrade.

---

## 21) Data Quality Rules

### 21.1 Minimum viable community record

Required for publication quality:

- Name.
- Short description.
- Long description.
- Faculty.
- At least one contact or official link.
- Correct status.

Strongly recommended:

- Logo.
- Tags.
- Contact email.
- Open-for-applications flag.
- Social links.
- Gallery/team/event images.

### 21.2 Content standards

- Use institutional tone.
- Avoid temporary event announcements in evergreen descriptions.
- Put time-sensitive recruitment messages in appropriate channels, not permanent profile fields.
- Avoid duplicate tags with only capitalization differences.
- Keep PL/EN UI and descriptions synchronized when bilingual content is used.

### 21.3 Import standards

- Prefer export-compatible pipe-delimited CSV.
- Keep a backup export before every import.
- Test with 2–5 rows before a large import.
- Review skipped count and log after import.
- Never import unknown spreadsheet exports without opening/checking headers first.

---

## 22) Release and Update Checklist

Before release:

1. Update version in plugin header and constants consistently.
2. Run PHP syntax checks on changed PHP files.
3. Smoke-test public pages: search, results, list, detail.
4. Smoke-test admin add/edit.
5. Smoke-test import with a small CSV.
6. Smoke-test export.
7. Smoke-test forum access and messaging.
8. Smoke-test statistics page.
9. Verify update manifest/build ZIP.
10. Backup database and files.

After release:

1. Confirm plugin version in admin UI.
2. Check required pages still resolve.
3. Monitor PHP error log and import log.
4. Verify admin permissions.
5. Ask one superadmin and one community admin to confirm critical flows.

---

## 23) Developer Maintenance Notes

### 23.1 When adding a community field

Update all relevant places:

- Database schema in `sc_create_tables()`.
- Schema version marker if needed.
- Add/edit templates.
- `sc_save_community()` sanitization.
- Import header support if the field should import.
- Export output if it should round-trip.
- Detail/list/search templates if public.
- Translation files if label text is shown.
- This documentation.

### 23.2 When changing import behavior

Update:

- `sc_normalize_import_header()` aliases.
- `sc_import_from_excel()` row mapping.
- `templates/admin-import.php` format table.
- Export columns if round-trip compatibility changes.
- Import examples in this document.
- `overview v2.txt` import summary.

### 23.3 When changing permissions

Update:

- `includes/auth.php`.
- `includes/admin-functions.php` edit checks.
- WordPress admin menu capability checks.
- Forum access checks.
- User-management template behavior.
- This document’s role model.

### 23.4 When changing UI routes or shortcodes

Update:

- `sc_register_shortcodes()`.
- `sc_ensure_required_pages()`.
- Menu/page hiding logic if protected.
- Public navigation links.
- This document’s route table.

---

## 24) Quick Reference

### Public URLs

| URL | Purpose |
|---|---|
| `/sc-search` | Search form. |
| `/results` | Search results. |
| `/sc-list` | Community list. |
| `/detail?id=ABCDE` | Detail page for community `ABCDE`. |

### Protected frontend URLs

| URL | Purpose |
|---|---|
| `/sc-admin` | Frontend admin panel. |
| `/community-statistics` | Statistics. |
| `/sc-forum` | Internal forum. |

### WordPress admin areas

| Area | Purpose |
|---|---|
| PKN Communities | Main admin list. |
| Import/Export | CSV import/export, update history, plugin update card. |
| Tags & Faculties | Taxonomy maintenance. |
| Activity Dashboard | Data quality and activity overview. |
| Community Statistics | Reporting. |
| User Management | Assign admins to communities. |
| Contact Requests | Review admin/user requests. |
| Social Sync Settings | Facebook token/settings. |

### Most important import example

```csv
community_id|name|shortdescription|description|faculty|webpage|facebook|instagram|discord|inne|mail|logo|tags|status
ABCDE|Example Club|Short text|Long text|Wydział Biologii|https://example.edu|https://facebook.com/example|https://instagram.com/example|https://discord.gg/example|https://example.edu/more|club@example.edu|https://example.edu/logo.png|biology, ecology|1
```

---

## 25) Final Notes

This document is intended to be the **single comprehensive technical and operational guide** for the active PKN Backend plugin. Keep it synchronized with the codebase, especially after changes to:

- Database tables.
- Shortcodes and required pages.
- Import/export columns.
- Status lifecycle behavior.
- Roles and permissions.
- Statistics events.
- Forum behavior.
- Update/build pipeline.
- Public/admin templates.
