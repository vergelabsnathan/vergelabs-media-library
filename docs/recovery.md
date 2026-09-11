# Getting back in when a site is white-screened

The failure this is about: a plugin hits a PHP fatal error on every request, so
the front end and `wp-admin` both die. You cannot deactivate the plugin from the
plugins screen, because you cannot reach the plugins screen. The traditional
answer is FTP, rename the plugin folder, log back in.

Two of the routes below are ours. The rest are not, and are listed because when
a site is down the useful thing is the full set of options, not only the ones
this plugin can take credit for.

## 1. This plugin watches itself

`core/watchdog.php` registers a shutdown handler at load. On every request that
ends in a fatal error it asks one question: was the file that died inside this
plugin's own directory? If not, it does nothing at all — deactivating a plugin
because a *different* plugin crashed would be both wrong and maddening.

If it was ours, there are three rungs:

| Rung | Trigger | What happens |
|------|---------|--------------|
| 1 | First fatal | Recorded. Once is not a pattern — it can be a timeout, a corrupt upload, a host restarting mid-request. |
| 2 | Second fatal within an hour | **Safe mode.** The feature files stop loading. The site comes back. |
| 3 | A fatal while already in safe mode | Full self-deactivation. The remaining shell is broken too. |

Strikes expire after an hour, so two unrelated crashes months apart never add up
to a plugin that switched itself off for no reason anyone can see.

Safe mode is preferred to deactivation on purpose: the plugin stays active, so
something is still running that can put a notice on the screen explaining what
happened, show the actual error and file, and offer a button to switch the
features back on. A plugin that silently deactivates itself leaves the user to
guess.

Turn the whole mechanism off with `define( 'VERGEML_NO_WATCHDOG', true );` in
`wp-config.php`. Force safe mode on with `define( 'VERGEML_SAFE_MODE', true );`.

The Pro add-on has the same mechanism under `VGMLPRO_` names.

### What it does not cover

- A fatal so early that the handler is not yet registered — a parse error in the
  main plugin file, for instance. PHP never reaches `register_shutdown_function`.
- Memory exhaustion severe enough that even the reserved 256KB buffer is not
  enough to write an option row.
- An infinite loop or a timeout. Those are not fatal errors and `error_get_last`
  reports nothing.

For those, the routes below still apply.

## 2. WordPress recovery mode (core, since 5.2)

Core catches fatals itself and emails the address in **Settings → General** a
link that logs you in with the offending plugin paused. It is genuinely good and
costs nothing to rely on. Two things break it, and both are worth checking
*before* you need it:

- The admin email must be an address you can actually read. Many installs still
  have `admin@` on a domain with no mailbox.
- The site must be able to send mail at all. A lot of hosts cannot without SMTP.

Send yourself a test from **Users → Profile** if you are unsure.

## 3. WP-CLI, if the host offers it

Most managed hosts do, and it does not care that the site is white-screened:

```
wp plugin deactivate vergelabs-media-library
wp plugin deactivate --all          # when you do not yet know which one
wp plugin list --status=active
```

SiteGround, Kinsta, WP Engine, Cloudways and Pressable all expose this over SSH.

## 4. The database, via phpMyAdmin

No file access needed, only the host's database tool. The `active_plugins` row
in `wp_options` is a serialised array; renaming the option disables every plugin
at once:

```sql
UPDATE wp_options SET option_name = 'active_plugins_off'
 WHERE option_name = 'active_plugins';
```

Rename it back afterwards. Serialised arrays are length-prefixed, so do not edit
the value by hand unless you are willing to count characters.

## 5. Renaming the folder — the FTP trick

Still the last resort, and still reliable. Rename
`wp-content/plugins/vergelabs-media-library` to anything else; WordPress finds
the plugin file missing and deactivates it on the next admin page load.

## If it was a release

A version that should not have gone out is withdrawn from the update channel,
not from the sites: `docs/runbooks/rollback.md` — what to change on Vercel,
how to see that a site stopped being offered it, the six-hour cache, and what
to do for a site that already took it (the same rename as section 5).

## If it was us

The safe-mode notice shows the file and line. `core/system-report.php` collects
the environment in one click, and it contains no licence key, email or
credential — check it yourself before sending. Issues:
<https://github.com/vergelabsnathan/vergelabs-media-library/issues>
