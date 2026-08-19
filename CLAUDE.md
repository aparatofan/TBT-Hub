# TBT Hub — Claude Code guidance

Keep this file concise. It is loaded at the start of every Claude Code session.

## Start here

- This is a small repository: inspect `git status`, then read only the file(s) relevant to the task.
- Do not load `README.md` automatically unless the task concerns shared-design-system rules or consumer behavior.
- Avoid changing unrelated menu, capability, registry, or design-system behavior while solving a local task.

## Project role

- TBT Hub is the central WordPress admin menu/index for TBT plugins.
- It is also the canonical owner of the shared TBT design system.
- Main PHP file: `tbt-hub.php`.
- Canonical shared styles: `assets/css/tbt-tokens.css` and `assets/css/tbt-components.css`.
- There is no build step or application framework.

## Shared design-system invariants

- Hub owns the WordPress style handles `tbt-tokens` and `tbt-components`.
- Register those handles; do not globally enqueue them from Hub.
- Registration happens early (`wp_enqueue_scripts` priority 5) so consumers can find the canonical handles before deciding whether to use a fallback.
- `tbt-components` depends on `tbt-tokens`.
- Consumer plugins may vendor fallback copies, but those copies must remain byte-identical to the Hub originals and register under the same handle only when Hub has not already registered it.
- Never create a second shared handle pointing at a divergent token vocabulary.
- Add a shared token/component only for a recurring suite-wide need, not to solve one isolated screen.
- A change here can affect multiple TBT plugins. Treat token/component edits as cross-plugin changes and call out the blast radius.

## Cross-repository boundary

- TBT Notes and TBT Matching Games consume Hub's shared design system and carry fallbacks.
- TBT Swipe deliberately uses its own private style vocabulary/handle; do not migrate it into Hub incidentally.
- When changing canonical shared CSS, verify consumer fallback copies are synchronized when those repositories are available; otherwise explicitly flag the required sync.

## PHP invariants

- Preserve menu priorities that let the Hub parent exist before consumer plugins register submenus.
- Keep the plugin registry filter-based (`tbt_hub_items`) so the Overview reflects active consumers rather than a hard-coded list.
- The owner-capability block is intentionally mirrored with TBT Register and guarded so either plugin can provide it. Do not edit that block in only one repository unless the task explicitly accepts divergence.
- Follow existing WordPress escaping/capability practices on admin output.

## Validation

For PHP changes:

```bash
php -l tbt-hub.php
```

For shared CSS changes, inspect the diff carefully and check downstream fallback parity where possible. If browser/WordPress behavior cannot be exercised locally, state what needs a live check.

## Git and deployment

- Do not commit directly to `main`; use a focused feature branch unless explicitly instructed otherwise.
- Inspect the final diff before finishing.
- Every push to `main` triggers the Hub FTPS deployment workflow.
- Markdown files are excluded from the FTP upload, but the workflow itself still starts for a Markdown-only push because the trigger has no Markdown `paths-ignore` rule.
- Never change FTP credentials, secrets, or server paths unless the task is specifically about deployment.

## Context discipline

- Prefer a narrow read of `tbt-hub.php` or the relevant CSS file over broad repository exploration.
- Do not paste full stylesheets, long logs, or large README sections into the conversation when a summary is enough.
- At completion, report the changed surface, validation, downstream sync needs, and any manual verification briefly.
- Use a fresh Claude Code session for an unrelated new task.
