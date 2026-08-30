# How this plugin talks

Written 30-08-2026, after reading the dashboard as somebody who had never seen
it. The sentence that started it:

> **Unfiled files** — A proposal is waiting. Look it over branch by branch,
> apply it in one go, and put it all back with one click if you regret it.

Every word of that is true and none of it helps. It assumes you know what the
Librarian is, what a *branch* is, what was *proposed* and by whom — and it
never says that this is about 25 files sitting loose in your library.

## The reader

Somebody who installed a media plugin this morning. They know what a photo is,
what a folder is, and what their website is for. They do not know what an
index is, what a taxonomy is, what "described" means here, or that there is a
model involved at all until we say so.

They are not stupid and they are not in a hurry to learn our vocabulary. They
want to know what this is, what it will do, and whether it is safe.

## The rule

**Lead with the number. Say what happens. Say what it costs and what is
reversible.**

    25 of your files are sitting in one big pile with no folder. We have
    already worked out a set of folders for them. You will see every folder we
    want to make and exactly which pictures go in each one — nothing moves
    until you say so, and one click puts it all back if you change your mind.

Number first, then the offer, then the safety. No metaphors.

## We and you

Say **we** for the thing the software does, and **you** for the person. "We
have not looked at 12 of your pictures yet" beats "12 images are unindexed" —
the first has an actor and a consequence, the second is a database state.

## Words we do not use on screen

| Not this | This |
|---|---|
| described, indexed, unindexed | we have looked at it / we have not looked at it yet |
| stale | written before your settings changed |
| taxonomy | folder, or category |
| attachment | file, or picture |
| the model, the AI service | we |
| unattached, no references | not used on any page or post |
| proposal, branch, tree | the folders we want to make |
| apply, run, execute | do it, sort them, write them |
| quarantine | set aside |
| scope | which pictures |

The internal words are fine in code and in comments — the whole repo runs on
them. They are not fine on a screen.

## No invented names

A feature is called what it does. "The Librarian" was a name we made up, so
every sentence about it had to explain it first, and the nav item told you
nothing. It is **Sort into folders**.

| Was | Is |
|---|---|
| Librarian | Sort into folders |
| File what is still loose | Suggest a folder for each file |
| Say what you want | Type what you want done |
| Set aside | Files you have taken out of the library |
| Folders and taxonomies | Folders and categories |
| Library behaviour | Library settings |
| Look for a home for them | Suggest folders |

The test is whether somebody who has never opened the plugin can tell what a
menu item does from its label alone. "Librarian" fails it; "Sort into folders"
passes. Straight and plain beats clever every time — clever costs a sentence of
explanation on every screen it appears on.

The function names, hooks and file names keep the old words. Those are code,
and renaming them is churn with no reader.

## Say why, once, where it matters

Alt text is the case that proves it. "12 images have no alt text" means
nothing to somebody who has never heard of alt text. This does:

> 12 of your pictures have no alt text. That is the line a blind visitor's
> screen reader reads out loud instead of showing the picture, and Google reads
> it as well.

One clause of explanation, at the point of use, not a link to documentation.

## Never make somebody guess what a button spends

Anything that costs credits says how many before it is pressed, in the same
sentence as the offer. Anything irreversible says so. Anything reversible says
that too, because "you can undo this" is the sentence that gets a nervous
person to press the button at all.

## Length

As long as it needs, and not a word past it. A paragraph that answers "what is
this and should I press it" earns its lines; three paragraphs that restate the
label do not. If it cannot be read in one go, it belongs behind the `?`
(see `core/help.php`).

## Where this has been applied

- `core/journey.php` — done, 30-08-2026
- `core/help.php`, `core/librarian.php`, `core/health.php`,
  `core/import-ui.php`, `core/ai.php`, `core/auto-file.php`,
  `core/nl-commands.php`, `core/quarantine.php` — in progress
- `core/options-pages.php` — 83 prose strings, most of them inherited from
  Enhanced Media Library and written in its voice. Last, and the biggest.
