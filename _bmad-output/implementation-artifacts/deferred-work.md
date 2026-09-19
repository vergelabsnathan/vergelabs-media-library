- source_spec: `spec-1-1-the-channel-serves-4-0-0.md`
  summary: service/public/index-v2-backup.html still links /releases/vergelabs-media-library.zip, which is gone (404).
  evidence: nothing links to the backup page; it is Nathan's archived site export, so deleting or editing it is his call.
- source_spec: `spec-1-1-the-channel-serves-4-0-0.md`
  summary: tools/promote.mjs could HEAD every catalogue source under /releases/ and refuse to say promoted on anything but 200, closing the gap between a release commit and the daily release-check; the same script (or a sibling) should produce the <slug>-<version>-<sha256[:12]>.zip name, which today is typed by hand.
  evidence: this story's read-back was by hand; the cron is the only automated check and runs once a day; promote.mjs already parses /api/health.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: a one-command box walk (reset → 3.16.1 → fixture → freeze → 4.0.0 → suite) so the fixture is re-armed for the next schema bump without the story notes.
  evidence: today the fixture and freeze steps are run by hand through the ssh wrapper; only the suite is a verify.mjs entry.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: the 4.0.1 changelog names the PHP 8.4+ deprecation fix (core/ai.php vergeml_ai_rest_status), beside FR10 and FR12.
  evidence: readme copy is Nathan's; the fix is in the tree since 6dc3223.
