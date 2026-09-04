# How this plugin touches WordPress core

## The rule

**Wrap or add. Never replace.**

The original rule was "do not override core media UI". That cannot be satisfied.
WordPress core's media JavaScript exposes no hook API at all — `wp-includes/js/media-views.js`
contains zero `wp.hooks`, zero `applyFilters`, and one `doAction`. There is no supported way
to add a filter dropdown to the media grid. Patching core views is the only route, so the
question is not *whether* to patch but *how*.

Three ways to patch, in descending order of safety:

| Pattern | Verdict |
|---|---|
| `media.view.X = media.view.Y.extend({...})` — register a new view | Safe. Adds, changes nothing. |
| `core.X.method.apply(this, arguments)` then adjust — wrap | Safe. Core keeps running; we react to it. |
| `_.extend(media.view.X.prototype, {...})` without calling through — replace | **Liability.** Core's implementation is discarded and we silently fall behind every time WordPress changes it. |

## Why this is not theoretical

Two live bugs, both caused by replacement:

1. **The WordPress 7.0 layout break.** `AttachmentsBrowser.createToolbar` was replaced with a
   fork of core's version. WordPress 7.0 changed the toolbar to a CSS grid that places elements
   by id; our fork knew nothing about it and the toolbar stacked into a 300px block with every
   label above the wrong control.

2. **A live accessibility regression.** Core's `createToolbar` sets a `filters-heading` item — a
   screen-reader `<h2>` labelling the filter group. Our replacement never recreates it, so the
   heading is simply missing. Nobody noticed because replacement fails silently: core adds
   something, and our copy just doesn't have it.

A wrap would have inherited both changes for free.

## Current state

Measured against WordPress 7.0.4:

- **7 replacements** left — the liability (was 8; `createToolbar` is converted)
- **5 wraps** — fine
- **9 added methods** — fine
- **3 new views** — fine

### The frame runs in its own mode

Worth knowing before touching any of this: on `upload.php` the frame is
`mode-eml-grid`, not core's `mode-grid`. The mode is set in JavaScript, so grepping the
PHP for `eml-grid` finds nothing and it is easy to conclude the mode is PRO-only. It is not.

Consequences:

- core's grid branch in `createToolbar` does not run on its own, so core's **Bulk select**
  and **Delete selected** buttons were missing entirely. Fixed: `createToolbar` borrows
  `grid` for the duration of core's call and hands it back.
- the view switcher has to be supplied by us, because core only adds it in its own grid mode

**Do not declare `grid` permanently.** It was tried. It pulls in core's grid stylesheet,
which hides the individual filter labels behind the screen-reader heading — core's design,
not this plugin's — collapsing the filter row into stacked columns and taking the toolbar
from 66px to 96px. Borrowing the mode gets core's buttons without the restyle.

Priorities matter here too. Core files Bulk select at `-70`; our filters run from `-75`
upward in fractional steps so they sort before it and the button lands after the filter run
instead of between two dropdowns.

### Conversions

| Target | Why it was replaced | Route to a wrap |
|---|---|---|
| ~~`AttachmentsBrowser.createToolbar`~~ | **converted.** Calls core, then adds our filters and re-files what core placed differently. Recovered `filters-heading`; verified the toolbar renders at identical coordinates with one extra child, the heading. | done |
| `AttachmentFilters.change` | reset-button state across several filters | Call core, then update the reset button |
| `AttachmentFilters.select` | match our extra props | Call core, then correct the selection |
| `AttachmentsBrowser.updateContent` | no-results messaging in our grid mode | Call core, then adjust; or gate to `eml-grid` only |
| `AttachmentsBrowser.createUploader` | same | as above |
| `AttachmentsBrowser.createAttachments` | same | as above |
| `AttachmentCompat.render` | taxonomy field classes and term counts | Call core, then post-process the DOM it produced |
| `controller.Library.uploading` | autoSelect behaviour | Call core, then adjust selection |

`createToolbar` is the one to do first: it is the largest, it caused the WP 7 break, and it is
the one dropping `filters-heading`.

## Known issues

- **Duplicate id `eml-save-changes-message`.** `emlAttachmentDetailsEditMessage` hard-codes
  that id and is instantiated twice, once for the save-success message and once for
  save-failure. Both stylesheets target it as `#eml-save-changes-message`, and an id
  selector only matches the first occurrence, so the failure message is likely unstyled.
  Same class of bug as the author filter's duplicate id. The fix is to move the styling to
  a class and give the two instances distinct ids; it touches the view plus both
  stylesheets, so it wants its own commit. Found by the duplicate-id guard below, which is
  exactly what that guard is for.

## Guard

Converting is not enough on its own — a wrap can still drift if core renames a toolbar key.
The test ground should assert, per WordPress version in the matrix:

- every key core's `createToolbar` sets is still present after ours runs
  (catches a repeat of `filters-heading`)
- the toolbar occupies exactly two grid rows with each label sharing an x with its control
- no duplicate element ids in the toolbar

Those three checks would have caught all of: the WP 7.0 layout break, the duplicate author
filter id, and the missing heading.

## Guided sorting (2026-09-04)

A full-screen, four-step way to arrive at a folder structure: `core/guide.php` keeps one session per site in the option `vergeml_guide_session` (goal, summary, proposals, draft tree with versions, turns, a 25-turn cap) and exposes `/guide/session|summary|propose|turn|apply|progress` under the plugin's REST namespace. The screens are `js/vergeml-guide.js` in `wp.element`, no build step. The assistant lives on the service (`/v1/guide`, `lib/guide.ts`, Opus with thinking disabled) and only ever edits the draft; `apply` hands the final tree to `vergeml_talk_apply()` and the same resumable re-filing every other path uses. Counts on proposals are estimated in the plugin from a 2,000-record sample of the catalogue, never from a model.
