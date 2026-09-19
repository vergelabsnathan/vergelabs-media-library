# Ready to service clients — the whole suite, not the plugin

Written 2026-09-19 at the end of S21. The plugin is in good shape; the suite
around it has drifted behind it. Ordered by what hurts a paying client first,
not by what is easiest.

**Where the plugin stands:** 4.0.0 is cut, tagged `v4.0.0`, archived at
`dist/vergelabs-media-library-4.0.0.zip` (145 entries, sha256 `bf0d63b70056`),
and passes Plugin Check with no errors and two non-blocking warnings. Suites
green, both SCORE lines unmoved, `folders.spec` and `modes.spec` green.

## Phase 1 — the suite is selling something it does not serve

**1.1 Publish 4.0.0 to the update channel.** Releases come from the
`PLUGIN_RELEASES` env var in Vercel, read by `service/lib/updates.ts:149`. It
still says free 3.16.1, pro 1.0.2 — confirmed live in `/api/health` on
2026-09-19. Until it moves, every existing site is told it is current and every
new client installs four months behind. Needs the zip hosted where the signed
download route can serve it, then the env var, then a redeploy.

**1.2 Walk 3.16.1 → 4.0.0 as an upgrade**, not a fresh install, on a site with
existing folders and a confirmed tree. The librarian schema went 1 → 4 and
filing decisions move; this is the walk suites do not cover.

**1.3 Check Pro 1.0.2 against 4.0.0.** Pro was built against the old free
plugin, and 4.0.0 removed the Rules tab and reshaped routes. If Pro breaks,
every Pro customer breaks on update day. **Unknown, and the scariest item
here.**

## Phase 2 — the money path

**2.1 Walk the purchase as a buyer, end to end, on 4.0.0:** buy → licence
issued → connect → describe → credits decrement → invoice. The standing lesson
is that every component returned 200 and the customer still got nothing.

**2.2 Confirm `CREDITS_MIN` is not still 69 in Vercel production.**
`service/lib/credits.ts:53` defaults to 500; the override existed for one
supervised €1 walk.

**2.3 The VAT position.** From Tenerife the supply is non-EU: destination VAT
from the first euro, and Stripe will not run the right OSS scheme. This is not
engineering and cannot be fixed in a session — it needs an accountant, and it
gates real invoicing.

## Phase 3 — what is being promised operationally

**3.1 An availability answer.** Filing and describing stop when the service
does. Decide it before an agency asks during an outage.

**3.2 The licence key in the support ticket.** `vergeml_help_send()` posts the
plaintext key with the system report, while the key is sealed at rest against
the site's auth salt precisely so a leak cannot hand out working licences. The
site token already sent, or the key's last four characters, identifies the
customer as well.

**3.3 A response time that can be kept solo.**

## Phase 4 — the client-library risks

**4.1 The CSV export formula injection.** A folder named `=HYPERLINK(...)` is
written unquoted and executes in Excel, Numbers and Sheets. Recorded and
deliberately unfixed; it is the only open item that hurts somebody who is not
us. Fix before exports touch a client.

**4.2 Re-run the compatibility matrix against 4.0.0.** The 18 cells and the
plugin-conflict set were last run on 3.16.1, before the media-list rebuild and
the Enhanced Media Library dequeue — the two changes most likely to break
someone else's plugin.

**4.3 Operator-grade or client-grade.** Filing is 60 % exactly right, 13 % in a
correct ancestor, 10 % wrong, 18 % declined. Either the maintainer drives every
fill, or the screen has to stop a client filling 20,000 pictures without
reading the count. A product decision, not a bug.

## Deferred deliberately, after the above

- A product's picture placed by its product **on upload**. Today the rule runs
  only in the fill and the dry count, never in `core/auto-file.php`, so a new
  product's pictures wait for the next Fill. Building it means a new automatic
  write path on a real library — plan it before writing it.
- The Uncategorized guard: withhold the categories button when Woo's default is
  the only category with anything in it.
- The maintainer's pass over `docs/superpowers/mocks/2026-09-19-folders-sheet.html`.
- Filing headroom, in order: the leaves that take nothing at all
  (`bags & luggage > backpacks` scored 0 right and 11 unplaced of 11 — a folder
  that takes nothing is bug-shaped, not a model limit), then the audience gate
  at 44 of 581.
