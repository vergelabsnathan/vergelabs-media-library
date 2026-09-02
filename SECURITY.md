# Security policy

## Reporting a vulnerability

Email **security@vergelabs.nl**. Please do not open a public issue, and please
do not post it in the WordPress.org support forum — both are readable by the
people the bug would be used against.

Include what you can: the version, what you did, what happened, and where you
found it. A proof of concept is welcome and never required.

**What to expect**

| | |
|---|---|
| Acknowledgement | within 24 hours, by a person |
| First assessment | within 3 working days |
| Fix for a confirmed issue | as fast as it can be tested — hours for anything remotely exploitable |
| Credit | your name in the changelog and the release notes, unless you would rather not |

We do not run a paid bounty. We do not require an NDA, and we will not ask you
to stay quiet after a fix has shipped: once users are protected, publish
whatever you like.

## Scope

In scope:

- The plugin, at any version wordpress.org or GitHub is serving.
- The AI service it talks to at `https://ai.vergelabs.nl/v1`.
- The account and licensing site at `https://vergelabsmedia.com`.

Out of scope, so nobody wastes an afternoon:

- Reports produced only by an automated scanner, with no working attack.
- Missing headers or version disclosure with no demonstrated impact.
- Anything that needs an administrator to already be compromised — an
  administrator can install any plugin they like, which ends every such chain.
- Denial of service by volume, and social engineering of our staff or users.

## What the plugin does with your data

Said plainly, because a security policy that hides the data flow is not one:

- **Nothing is sent anywhere without a licence key and a run you started.**
  With no key, the AI screens make no request at all — not on page load, not
  on upload, not in the background.
- When a run is going, each image is sent **downsized, never the original**,
  with its file name, its MIME type, the site address and the licence key. The
  service describes it and discards it; what it keeps is a usage count.
- The licence key is **sealed at rest** with a key derived from the site's own
  auth salts, so a copied database or a stray SQL dump does not hand out
  working licences.
- Describing is **refused on a staging or development copy** unless
  `VERGEML_AI_ALLOW_NONPROD` says otherwise.

The full disclosure, including what "page context" adds when it is switched
on, is in `readme.txt` under *External services*.

## How we try to stay ahead of it

- Every release is linted against PHP 7.4 and 8.3 and run through WordPress's
  own Plugin Check.
- A nightly watch picks up new WordPress, PHP, plugin and theme releases,
  greps each one for every hook and field this plugin relies on, and upgrades
  a staging site before any of it reaches yours.
- The service side has an end-to-end suite that runs on every change and
  nightly against the live deployment.
