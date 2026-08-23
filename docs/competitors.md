# The two competitors, read from source

Free and Pro tiers of both, unpacked and surveyed rather than taken from marketing pages.
Versions: FileBird 6.5.8, Premio Folders 3.1.9 / Folders Pro 3.0.5.

## Architecture

|  | FileBird | Premio Folders | Us |
|---|---|---|---|
| Storage | custom tables `fbv`, `fbv_attachment_folder` | `media_folder` taxonomy (real terms) | existing plugin taxonomies (real terms) |
| Front end | React, ~765KB built bundle | jQuery + jsTree, ~140KB | plain JS, 164KB, **no build step** |
| Free PHP | 12,646 lines | 19,268 lines | 8,343 lines |
| Pro PHP | +13,340 lines | +24,192 lines | — |
| Core views | hooks, 8× MutationObserver | **`AttachmentsBrowser.extend` ×2** | wrap, never replace |
| Portable | no — uninstall loses the tree | yes — terms survive | yes — terms survive |
| Multi-folder | no | no | **yes** |

Two things follow. **Premio validated the substrate** — native terms, portable, REST-visible —
which is why we are on taxonomies rather than custom tables. And **Premio's view handling is the
anti-pattern**: replacing `AttachmentsBrowser` outright is why it breaks when core moves, and it
is the specific mistake `js/vergeml-media-views.js` warns against in its header comment.

FileBird's custom tables are the real lock-in. A user who uninstalls FileBird loses their entire
folder structure, because it never existed as anything WordPress understands. That is the
sharpest line we have, and it is true rather than rhetorical: *uninstall us and keep your
structure — they are ordinary WordPress categories.*

## What each one charges for

This is the useful part. Both Pro tiers agree on three things and then diverge completely.

**Both sell:** folders for posts, pages and custom post types (not just media); WPML/Polylang
multilingual; licensing and auto-updates.

**FileBird Pro sells galleries.** `PageBuilders/GalleryRenderer.php`, an Elementor widget, a Divi
module, and a Gutenberg `filebird-gallery` block. The pitch is *turn a folder into a gallery* —
the folder stops being filing and becomes a content primitive.

**Folders Pro sells media utilities**, none of which are about folders at all:

- `dynamic-folders.php` — automatic folders by year, month and author. Filing with no filing.
- `media.replace.php` — replace a file while keeping its URL and every existing embed.
- `media.clean.php` — scan for unused media and bulk-delete it.
- `svg.class.php` + a bundled SVG sanitiser — safe SVG uploads.
- `size.class.php` — file sizes in the library.

## What this means for our Pro

Our Pro currently sells AI descriptions, which neither competitor offers at all. That is a
genuinely uncontested position — but the survey shows the two of them independently concluded
that **folders alone do not sustain a paid tier**. Both bolted on adjacent value: one toward
publishing, one toward media hygiene.

Worth noting, not acting on yet:

- **Dynamic folders** (auto-file by date/author) is cheap for us — it is a saved query over
  terms we already have — and it is the one Folders Pro feature that would feel native here.
- **Replace-a-file-keeping-the-URL** is the most-requested media feature in WordPress generally
  and is entirely independent of folders.
- **Galleries** would mean owning page-builder integrations for Elementor, Divi and Gutenberg.
  That is a large, permanent maintenance surface on other people's release schedules. Against
  the no-build-step rule, and against a one-person team.

## The review nag

Both ship one. Premio's `class-review-box.php` is 717 lines and calendar-driven. The T4 plan's
success-moment box is deliberately the same mechanic without the nagging: it fires once, after
something actually worked, and dismisses forever.
