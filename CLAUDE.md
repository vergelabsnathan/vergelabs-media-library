# VergeLabs Media Library

GPLv2 fork of Enhanced Media Library 2.9.4, taken over after upstream went unmaintained.
Free plugin on wordpress.org; a paid Pro add-on (`C:\dev\vergelabs-media-library-pro`) sells
AI image descriptions on top of it. Currently **3.0.0**, not yet submitted.

Everything below is a constraint that has already drawn blood. Anything conditional lives in
`docs/` and is read only when the task touches it.

## Hard constraints

- **PHP 7.4 is the floor.** Users on shared hosting are the whole audience. No arrow-fn-only
  syntax, no `match`, no named arguments, no constructor promotion, no nullsafe operator.
- **No build step.** Plain ES5-compatible JS and hand-written CSS, shipped as-is. Nothing here
  compiles, bundles or transpiles. FileBird ships ~765KB of React; the entire point of this
  plugin is that it does not.
- **Attach, never replace.** Extend WordPress's media views by hooking them; never override
  `wp.media.view.AttachmentsBrowser` wholesale (Premio's Folders does this twice, and it is why
  it breaks whenever core moves). Same rule for any core view.
- **GPLv2, and the lineage is public.** Upstream credit stays in the readme. Never remove
  attribution, and never re-add upstream's phone-home.

## Where the version number lives

Three places, and they must agree or the plugin ships as one version while announcing another:

1. `vergelabs-media-library.php` header — `Version:`
2. the same file — `define( 'VERGEML_VERSION', ... )`
3. `readme.txt` — `Stable tag:` (this is the one wordpress.org reads)

## Naming

- Free plugin prefixes everything `vergeml_` / `VERGEML_`; Pro uses `vgmlpro_`.
- Text domain is `vergelabs-media-library`, and it must be the literal string in every
  `__()` call — a constant there breaks the translation scanner.
- Options: `vergeml_taxonomies`, `vergeml_lib_options`, `vergeml_tax_options`, `vergeml_mimes`,
  `vergeml_version`.

## Options migrations

Saved options outlive defaults. Changing a default does nothing for existing installs — every
one of them already has the old value written to the database. Migrations go in
`vergeml_set_options()` guarded by `version_compare( get_option( 'vergeml_version', '' ), 'X.Y.Z', '<' )`,
and they must not touch taxonomies the user owns (`eml_media` = 0 means it is the site's, not ours).

## Safe mode is load-bearing

`core/watchdog.php` catches fatals via `register_shutdown_function` and escalates: log → safe
mode → deactivate. Two things follow, both learned the hard way:

- **New feature files load inside the safe-mode guard** in `vergelabs-media-library.php`, so a
  crash in a new feature can actually be switched off. `core/rest-tree.php` is the example.
- **Read the flag with `get_option()`, not `wp_load_alloptions()`.** Alloptions visibility depends
  on the autoload column, so safe mode reported itself on while never applying.
- The rungs need a **one-minute floor** between them: one page view fires several PHP requests,
  so three strikes can happen in a second and skip straight to deactivation.

## Testing

Two environments, and they answer different questions. `docs/testing.md` has the full detail.

- **Playground** (`npx @wp-playground/cli`) for functional work — boots in seconds.
  - Browse **`127.0.0.1`, never `localhost`**: WordPress builds URLs from `siteurl`, and the
    other name fails every nonce with "the link you followed has expired".
  - On Windows, prefix with `MSYS_NO_PATHCONV=1` or Git Bash rewrites `/wordpress/...` into
    `C:/Program Files/Git/wordpress/...`. Mount is two args: `--mount-dir "C:\path" /vfs/path`.
  - A blueprint's `installPlugin` collides with `--mount-dir` ("Device or resource busy") — use
    `activatePlugin` against the mount instead.
- **The VPS** (`185.229.224.239`, Kamatera, hourly) for anything about scale or numbers. See
  the `kamatera-wp-test-box` memory for the four traps that make REST look broken there.

**Playground cannot measure performance.** PHP-wasm spends ~2.4s booting WordPress on every
request, so core's endpoints time the same as ours and wall-clock says nothing. Measure with
`tests/perf/bench.mjs`, which reports handler time separately from boot and — the figure that
actually transfers — **query count**, which is a property of the algorithm and is identical in
both environments. A disagreement there is a bug, not a hardware difference.

## Reference points

The two competitors, read from source, in `docs/competitors.md`. Short version: FileBird uses
custom tables (`fbv`, `fbv_attachment_folder`) and React; Premio Folders uses a real taxonomy
plus jQuery/jsTree. We use taxonomies like Premio and stay build-free like neither.

## Working method

This repo runs the R-PIV loop — research, plan, implement, validate — with the plan written in
one session and executed in a **fresh** one. See `.claude/skills/`. Two rules that are not
negotiable because they are the whole point:

- **Never implement in the planning session.** The plan file is the handoff; a session that
  planned is too full to build well.
- **Never skip the interview.** Reducing the number of assumptions the agent makes is the job.
  A ticket that seems clear is what an assumption feels like from the inside.

Validation commands are in `.claude/skills/validate/SKILL.md`. Run the whole gate, iterate until
green, and optimise for correctness rather than time.
