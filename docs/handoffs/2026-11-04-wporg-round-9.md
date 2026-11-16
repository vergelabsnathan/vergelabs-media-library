# Session handoff — 2026-11-04 — wordpress.org round 9: post-type counts need a capability check

## Goal
Fix the round 9 finding across the whole plugin, prove it, build `dist/vergelabs-media-library-4.1.0.zip`, and hand Nathan the zip path, upload comment and reply. Enter through `vergelabs-loop` (Build → Prove → Review → Ship) and follow the `wporg-submission` skill's "Each review round".

The finding (review ID `R vergelabs-media-library/vergelabsdev/26Sep26/T7 28Oct26/4.3`), on `core/rest-tree.php:169`: "Users with upload_files can request counts for arbitrary post types without a capability check for the requested post type."

## State
- Repo: `Media Plugin\wt\release-4.1.0`
- Branch: `release-4.1.0`
- Last code commit: `243b973`; the last commit before this handoff is `00d4aa6` — docs(wporg): round 8 upload sent 2026-10-26
- Uncommitted: clean, apart from this handoff's commit. No code changed yet.
- Dev server: not checked (the shell was blocked)
- Last file edited: this handoff

## Decisions made this session
- Treat it as a real gap, not a false positive: `vergeml_folder_counts()` and `vergeml_folder_unfiled_count()` count every status except trash and auto-draft, so an upload_files user can read draft and private totals for any registered type, including another plugin's.
- Proposed fix, not yet built:
  1. One helper decides which post type a caller may have counts for. It returns `''` unless the type exists, `is_object_in_taxonomy()` holds for the taxonomy, and `current_user_can( get_post_type_object( $type )->cap->edit_posts )`.
  2. Every counts path goes through the helper.
  3. `/tree`'s inline permission_callback also refuses a named post type the user cannot edit, because the reviewer reads the callback.

## The class, from the read-only search of `core/`
| Site | What it does |
|---|---|
| `rest-tree.php:169`, `:281-286` | the quoted `/tree` route: only `post_type_exists()` |
| `rest-tree.php:201`, `:729`, `:822` → `vergeml_assign_count` `:529-533` | `/assign` returns counts for a caller-chosen type, with no existence or capability check |
| `rest-folders.php:42`, `:383-389` | the folder route passes `post_type` straight into `vergeml_rest_tree()`, which skips the permission_callback, so the check must also live in the handler path |

No `$_GET`/`$_POST` reads of `post_type` matched in `core/`. Not yet searched: PHP outside `core/`, and other parameters that choose an object type under a different name. Do both before calling the class closed.

The browser sends `cfg.postType` (`js/vergeml-tree.js:132-140`, `:162`). Confirm a post-screen user who may edit that type still gets counts.

## Open questions / blockers
- The session guard blocked Bash here because `spec-1-1-the-channel-serves-4-0-0.md` "became done in this session". That story was not part of this work; if it recurs in a fresh session, look at the guard's state.
- Should an Author see totals that include other people's private posts? `edit_posts` lets them through while the counts include every status. Core's list screen is the comparison to check. Probe it, then fix it or record it in the ledger before the zip.

## Files to read first (priority order)
1. `docs/wordpress-org-submission.md:51` — round 8 proof, the bar to match
2. `~/.claude/skills/wporg-submission/SKILL.md` — round procedure and gates
3. `core/rest-tree.php`, `core/rest-folders.php:375-396`, `core/post-folders.php:117-194`
4. `tests/security/route-caps.php` and `tests/release/hands-off.mjs` — where the new test and guard row go

## Next concrete step
Add a failing case to `tests/security/route-caps.php`: an upload_files user without `edit_posts` for a named type asks `/tree`, `/assign` and the folder route for that type's counts. Run `node tools/verify.mjs route-caps` and see it fail on `243b973` code, then build the helper.

## After the fix
- Add a hands-off row for the class and prove it red with a planted mutation.
- Run every gate on the archive of one commit: PHPCS, Plugin Check, clean walk, hands-off, hygiene, runtime checks, suites.
- Run the fresh-eyes reviewer with the round 9 class named. Round 9 was one finding after eight rounds, so look hard for this class's siblings: read routes where a parameter widens what is counted or listed.
- Only then build the zip, read it back and record commit, size and sha256.
- The upload and the reply are Nathan's.

## What NOT to redo
- The round 8 work: inline permission_callbacks and the `vergeml_` prefix, including `vergeml_folder`.
- Do not fix only the quoted line: the `/assign` and folder paths have the same gap.
