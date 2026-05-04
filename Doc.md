# PKN Platform — Comprehensive Documentation (English)

> **Audience:** Super Admins, Science Community Admins, and Public Users  
> **Scope:** Entire plugin surface (public pages, administration, data flow, forum, statistics, imports, and operations)

---

## 1) Platform Purpose

PKN is a WordPress-based platform for managing and presenting science communities (student clubs / organizations). It supports:

- Public discovery (search, filters, detail pages, list pages)
- Community-level administration (creating/updating entries)
- Central governance (verification, moderation, user assignment, import/export, statistics)
- Internal communication layers (forum and contact workflows)

The system is implemented as a plugin and relies on plugin-managed pages and shortcodes.

---

## 2) Built-in Pages and Shortcodes

On activation (and recovery checks), the plugin ensures the required pages exist:

- **PKN Search** (`/sc-search`) → `[science_communities_search]`
- **PKN Results** (`/results`) → `[science_communities_results]`
- **PKN Detail** (`/detail`) → `[science_community_detail]`
- **PKN Admin** (`/sc-admin`) → `[science_communities_admin]`
- **PKN Communities List** (`/sc-list`) → `[science_communities_list]`
- **Community Statistics** (`/community-statistics`) → `[science_communities_statistics]`
- **PKN Forum** (`/sc-forum`) → `[science_communities_forum]`

This means production recovery is easier: if pages are deleted or shortcode mapping is damaged, admin-side checks can regenerate consistency.

---

## 3) User Roles and Responsibility Model

## 3.1 Super Admin (global governance)

Super Admin operates across all communities and is responsible for:

- Reviewing the full catalog of communities
- Validating quality/completeness of records
- Managing lifecycle/statuses (active/inactive/archived/suspended depending on configured statuses)
- Managing manager-user assignments
- Running imports and system-wide corrections
- Monitoring forum and analytics signal
- Maintaining data consistency and language quality

Super Admin must treat the platform as a **public register + operational system**.

## 3.2 Community Admin (single-community operations)

A regular admin for a specific science community should:

- Create or edit that community’s profile content
- Keep descriptions up to date
- Maintain official links (website/social channels)
- Upload and manage logo/media fields available in current UI
- Respond to governance feedback from Super Admin

Community Admin should focus on **content accuracy and timeliness**.

## 3.3 Public User (outside user)

Public users can:

- Browse all listed communities
- Search by text / apply filters
- Open detail pages
- Access forum and public interaction surfaces exposed by the site
- Use links to external channels (social/web)

Public users are consumers of information; their experience depends heavily on admin data quality.

---

## 4) Functional Areas (Complete Walkthrough)

## 4.1 Public Discovery Flow

1. User enters Search/List area.  
2. Applies text queries and optional filters.  
3. Opens Results/List cards.  
4. Enters community detail page.  
5. Follows official links (website/social).  
6. Optionally continues to forum/contact context.

### Key assets/templates involved
- `templates/search-form.php`
- `templates/search-results.php`
- `templates/community-list.php`
- `templates/community-detail.php`

## 4.2 Community Detail Surface

A complete profile can include:

- Community name
- Short and full description
- Logo/media fields (as implemented)
- Website and social links
- Status and metadata

This page is the platform’s **public truth surface**, so stale data here impacts credibility.

## 4.3 Admin Center (`/sc-admin`)

Admin area typically provides action-based subviews, including:

- Dashboard / overview
- Add community
- Edit community
- Communities list / management
- User-manager mapping
- Import/export utilities
- Contact request handling

Key templates include:

- `templates/admin-panel.php`
- `templates/add-community.php`
- `templates/edit-community.php`
- `templates/admin-communities-list.php`
- `templates/manage-users.php`
- `templates/admin-import.php`
- `templates/contact-requests.php`
- `templates/dashboard.php`

## 4.4 Statistics and Tracking

Platform-level analytics surfaces include:

- Community views
- Social click tracking
- Search behavior signals
- Tag popularity / usage patterns

Primary touchpoints:

- `includes/statistics.php`
- `templates/community-statistics.php`

Use statistics for curation, not vanity:

- Identify communities with low discoverability
- Spot missing or weak descriptions
- Detect high-interest tags to improve taxonomy

## 4.5 Forum Layer

Forum functionality is isolated as dedicated feature code and template.

Primary touchpoints:

- `includes/forum.php`
- `templates/forum.php`
- `assets/css/forum.css`
- `assets/js/forum.js`

Operationally, forum should be treated as semi-public institutional communication and moderated accordingly.

## 4.6 Import/Export and Data Maintenance

The platform includes import tooling and supports bulk-oriented maintenance workflows.

Goals:

- Initial migration from legacy datasets
- Batch updates for large edits
- Recovery from partial data quality issues

Operational safeguards recommended:

- Validate mandatory fields before import
- Reject malformed rows with visible logs
- Run post-import deduplication checks
- Keep dated backups before large operations

---

## 5) End-to-End Role Playbooks

## 5.1 Super Admin Playbook (Daily/Weekly)

### Daily
- Check admin dashboard for anomalies
- Review new/edited communities
- Resolve pending contact/admin requests
- Verify broken social/web links from random sample

### Weekly
- Review communities with missing descriptions/logos
- Audit status correctness (active vs archived)
- Validate user-to-community assignment integrity
- Run light analytics review (top searches, zero-result patterns)

### Monthly
- Execute data quality report
- Plan taxonomy cleanup (tags/faculties)
- Evaluate import/export readiness and backup integrity
- Publish governance notes to internal team

## 5.2 Community Admin Playbook

- Keep title/description concise and factual
- Update achievements/events in long description
- Ensure social links are official and active
- Replace outdated logo/assets quickly
- Coordinate status changes with Super Admin when required

## 5.3 Public User Experience Principles

Public users should always find:

- Clear description of what the community does
- Valid contact or social path
- Up-to-date status
- Simple navigation back to results/list

---

## 6) Information Architecture and Data Quality Rules

Recommended quality baseline per community record:

- **Required:** name, short description, full description, at least one contact path
- **Strongly recommended:** logo, faculty mapping, tags, status reason when non-active
- **Governance:** each non-active state should be reviewable and explainable internally

Naming and content standards:

- Use institutional tone
- Avoid temporary announcements in evergreen fields
- Keep language versions synchronized (if bilingual mode is enabled)

---

## 7) Security, Permissions, and Operational Safety

- Treat all admin forms as permission-protected actions
- Keep nonce and form action checks enabled in production
- Restrict edit scope for community-level admins to assigned entities
- Log sensitive state changes (status edits, bulk updates, imports)
- Never run bulk operations without rollback plan

---

## 8) Frontend/UI Areas and Styling Notes

Main CSS surfaces:

- Public: `assets/css/style.css`, `assets/css/search.css`, `assets/css/results.css`, `assets/css/community-list.css`, `assets/css/community-detail.css`
- Admin: `assets/css/admin.css`, `assets/css/admin-panel.css`
- Shared: `assets/css/globals.css`

JS surfaces:

- Public interactions: `assets/js/script.js`
- Admin interactions: `assets/js/admin-script.js`
- Layout fixes: `assets/js/layout-fixes.js`
- Forum interactions: `assets/js/forum.js`

Design governance objective: visual consistency with the university ecosystem while preserving plugin independence.

---

## 9) Troubleshooting Matrix

## 9.1 Add/Edit save issues

If forms redirect incorrectly or save nothing:

- Confirm `action` field values in forms match registered handlers
- Verify nonce creation/validation pair
- Check user capability checks (role/capability mismatch)
- Inspect PHP error logs around `admin-post.php`

## 9.2 Media/logo upload issues

If upload stalls or progress area freezes:

- Validate server upload limits (`upload_max_filesize`, `post_max_size`)
- Confirm AJAX/media endpoint responses (HTTP status + payload)
- Check MIME/type/extension validation rules
- Test on target hosting stack (XAMPP behavior can differ)

## 9.3 CSV import data quality issues

If empty or malformed communities are created:

- Enforce column mapping checks
- Reject rows without mandatory fields
- Add transaction-like rollback strategy for large batch imports
- Produce import report with success/failure row details

---

## 10) Recommended Governance KPIs

Track these core KPIs monthly:

- % communities with complete profile fields
- % communities with valid logo
- % communities updated in last 6 months
- Search-to-detail click-through rate
- Top zero-result searches
- Active vs archived trend

These KPIs align administration effort with public discoverability and data trustworthiness.

---

## 11) Change Management and Release Checklist

Before each release:

1. Backup database + files.
2. Validate shortcode pages exist and resolve correctly.
3. Run smoke tests for search, detail, admin add/edit, forum, stats.
4. Test at least one community lifecycle change.
5. Confirm no PHP warnings/notices in critical flows.
6. Validate UI on desktop and mobile breakpoints.

After release:

- Monitor error logs for 24–48h
- Verify analytics events still record correctly
- Collect admin feedback and create patch list

---

## 12) Quick Navigation by Role

### Super Admin Quick Links
- `/sc-admin`
- `/community-statistics`
- `/sc-forum`

### Community Admin Quick Links
- `/sc-admin?action=add`
- `/sc-admin?action=edit&community_id=...`

### Public User Quick Links
- `/sc-search`
- `/results`
- `/sc-list`
- `/detail?id=...`

---

## 13) Final Notes

This documentation is designed as a **single-source operational guide** for onboarding, daily administration, and platform governance.  
For long-term maintainability, keep this file updated whenever:

- New admin actions are added
- Role permissions change
- Import/statistics/forum behavior changes
- Public navigation flow is redesigned

