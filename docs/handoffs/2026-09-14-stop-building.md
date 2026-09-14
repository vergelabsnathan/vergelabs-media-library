# Handover — 2026-09-14: the build stops here (Opus 5)

The plugin has been shippable since 3.16.1 on 2026-09-12. Every open item in
`plans/four-yesses.md` is a 2,000-sites item; the plan's own rule is 20 sites
first. No build session opens until there are users, a real report, a real
failure, or wordpress.org comes back.

## What went out today

- Free disclosure channels, all live: GitHub private advisories on
  (`gh api`), Dependabot on, `security.txt` on both hosts (`9db9950`,
  `a30981f`), Patchstack VDP program `dc92736f-…` in manual review, its FAQ
  entry in `readme.txt` and the link in `SECURITY.md` (`2d3587b`), zip rebuilt
  (`348c841`).
- `docs/security-brief.md` gains "What breaks in this class of plugin" — 62
  WPScan findings across six media-folder plugins (`6485a0e`).
- Repo description, homepage and topics set; `readme.txt` opening leads with
  the product, EML is one line (`8187a14`).

## Open, for when something happens

- Patchstack: program name typo ("VerlabsMedia") to fix in its settings;
  re-sync after a wordpress.org listing.
- wordpress.org: account approval, their queue.
- Tokens pasted into transcripts, to rotate if wanted: Better Stack API
  (S2d), WPScan API (today). Neither is in a file.
- The Phase 4 S2d handoff's "Next" card is stale (Phase 5 S1 was done on
  09-13); ignore it. `~/.claude/harness/model-profiles.md` does not exist.

## Next

Users. Not a card.
