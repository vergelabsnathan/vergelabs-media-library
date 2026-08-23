# Getting to the 60,000

Enhanced Media Library: 60,000 active installs, last updated 15 July 2024, rated 86/100.
Those people are the business. There are two ways to reach them and one is worth roughly a
hundred times the other.

## The fork in the road

**Route A — adopt the slug.** WordPress.org transfers abandoned plugins. If `enhanced-media-library`
becomes ours, **all 60,000 installs update to our code on their next update check.** No
marketing, no SEO, no reviews, no waiting to be discovered. You inherit the users rather than
acquiring them.

**Route B — a new listing.** Submit `vergelabs-media-library` and start from zero against
FileBird's 200,000 and Folders' 90,000, both with years of reviews. Every install has to be
earned one search at a time.

Route A is the whole game. Route B is the fallback, and it takes weeks in the queue anyway, so
it should be running in the background regardless.

**They are not mutually exclusive.** Submit our own plugin *and* request adoption. If adoption
lands, the two get merged or the new one is withdrawn.

## Why the adoption case is stronger than it looks

The plugins team is cautious about transfers, and "I'd like to take this over" alone rarely
moves them. What moves them, in rough order:

1. **A security issue in an unmaintained plugin.** We found one: `wpuxss_eml_apply_settings_to_network`
   checks a nonce but no capability, and writes options into every site on a network. Not cleanly
   exploitable — the nonce only prints on a super-admin screen — but it is a real missing
   capability check in a plugin nobody is fixing. That is the single strongest lever, because the
   team's alternative is to *close* the plugin, which serves those 60,000 users worse than
   transferring it.
2. **The plugin is actually broken on current WordPress.** The WP 7.0 toolbar bug is visible,
   reproducible, and reported by users in the forum.
3. **A competent, complete, already-working patch**, GPLv2, attributed to the original author.
4. **A commitment to keep it free.** Say it plainly and mean it. A transfer to someone who
   immediately paywalls it is exactly what they screen for.
5. **Evidence of a good-faith attempt to reach the author first.** They ask for this. Do it and
   say when you did it.

Our case has all five. That is unusually strong.

## Sequence

**Now, in parallel — none of these need wordpress.org approval:**

1. **Email the author.** Draft ready in `outreach/adoption-email.md`. Give it 7–14 days.
2. **Answer the forum thread** about the WP 7 layout bug. This is the highest-intent audience
   that exists: people who have the problem, right now, and are looking for the fix. One honest
   post, one link, no marketing. Draft in `outreach/forum-reply.md`.
   **Get this wrong and it kills Route A** — self-promotion in .org forums gets accounts flagged,
   and a flagged account does not get handed a plugin. Be useful or say nothing.
3. **Publish a GitHub release plus a Playground link.** Someone can try the fixed plugin in ten
   seconds without installing anything. For a media plugin, where the whole pitch is visual, that
   demo is worth more than any description.
4. **Ship the zip from vergelabsmedia.com.** Nothing about selling Pro or distributing the free
   plugin requires the .org directory.

**Then, after the author's window closes:**

5. **Email plugins@wordpress.org** requesting adoption, saying you contacted the author, when,
   and that there was no reply. Lead with the security issue.

**In the background, from today:**

6. **Submit `vergelabs-media-library`** so the review clock starts. Needs your .org username in
   `readme.txt` — that is still the only thing blocking it.

## About not being approved yet

Two separate queues, and they are often confused:

- **Account review.** A new WordPress.org account can sit before it works. Unrelated to the
  plugin.
- **Plugin review.** Typically a few weeks, sometimes longer, and it only begins once a
  submission exists. If nothing has been submitted, nothing is queued.

Neither is a judgement on the plugin. Plugin Check is clean, the upgrade path is tested, and it
runs beside the eighteen most common plugins.

**The important part: none of this blocks the work.** The forum thread, the author email, the
Playground demo and the direct zip all function today. Approval widens the funnel; it does not
open it.

## What to say, in one line each

- **To the author:** here are your bugs, here are the patches, they are yours either way.
- **To the plugins team:** this plugin is broken on current WordPress and has an unfixed
  capability check, the author has not responded, and I have a tested fix I will keep free.
- **To users in the forum:** here is why the toolbar breaks and here is a build where it does not.
- **To everyone else, later:** it is the plugin you already use, maintained, and now with folders.

## The thing that makes the tree matter here

Adoption gets us 60,000 users. It does not get us paid — the free plugin stays free, and it
should. The tree is what turns an inherited install base into something with a reason to pay:
those users are the exact people currently deciding whether to install FileBird alongside it.

Which is why the order is: **adoption first, tree second, Pro third.** Adoption is time-critical
because an abandoned plugin can be closed by the team at any point, and a closed plugin cannot
be adopted.
