# Guided Sorting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A full-screen, four-step guided sorting flow in wp-admin, with an Opus-backed assistant that proposes and revises a draft folder tree in conversation, and files the library by evidence only after the user confirms.

**Architecture:** The plugin owns the session (an option), the four screens (`wp.element`, no build step), the summary and count estimation (SQL over the catalogue), and the apply (existing `vergeml_talk_apply()` + resumable re-filing). The service owns one new route, `/v1/guide`, in two modes (`propose`, `turn`), with the prompts, the model (`claude-opus-5` via OpenRouter, thinking disabled) and Zod validation. Nothing in either side reads all catalogue records into a model call.

**Tech Stack:** PHP 7.4+ (WordPress plugin, REST API, `wp.element` = React 18 shipped by WordPress), Next.js 16 route handlers, TypeScript, Zod, Vitest, Playwright (existing `box-ui.yml` sweep), the box probes in `box-fix.yml`.

**Spec:** `plugin/docs/superpowers/specs/2026-09-04-guided-sorting-design.md`

## Global Constraints

- Plugin has no build step: the screen is written with `wp.element.createElement` (aliased `h`), never JSX. Scripts are enqueued with dependencies `wp-element`, `wp-api-fetch`, `wp-i18n`.
- Every model call goes through OpenRouter as the rest of the service does (`anthropic()` client, `provider()`/`providerPreferences()` routing). Never propose `ANTHROPIC_API_KEY` for anything.
- Guide model: `claude-opus-5` (override `ANTHROPIC_GUIDE_MODEL`), `max_tokens: 6000`, `thinking: { type: 'disabled' }`.
- Cap: 25 assistant turns per session, enforced in the plugin; the service refuses more than 60 turns of history.
- Folder shape everywhere: `{ name, parent, matches, classes[], kinds[], audience }` (the existing `FolderShape`). A name never contains a slash.
- The assistant never files anything. Only `POST /guide/apply` does, via `vergeml_talk_apply()`.
- All plugin strings through `__()` / `esc_html__()` with text domain `vergelabs-media-library`; all output escaped; REST routes under `VERGEML_REST_NS` with `manage_categories`.
- Box deploys are gated by `php -l` (deploy.mjs). Commit after each task; push to deploy to the box.
- Copy rules from the spec: every screen ends in one confirm button whose label says what happens next.

---

### Task 1: Service — schemas, prompts and the two model calls (`lib/guide.ts`)

**Files:**
- Create: `service/lib/guide.ts`
- Create: `service/lib/guide.test.ts`
- Read for patterns: `service/lib/anthropic.ts` (`anthropic()`, `wireModel()`, `provider()`, `providerPreferences()`, `looseJson()`, `FolderShape`)

**Interfaces:**
- Consumes: `FolderShape`, `looseJson`, `anthropic`, `wireModel`, `provider`, `providerPreferences` from `./anthropic`.
- Produces:
  - `export const GuideFolder = FolderShape.extend({ matches: z.string().default(''), count: z.number().int().nonnegative().optional() })`
  - `export const GuideTree = z.object({ folders: z.array(GuideFolder), tags: z.array(z.object({ name: z.string(), values: z.array(z.string()).default([]) })).default([]) })`
  - `export type GuideTreeT = z.infer<typeof GuideTree>`
  - `export const TurnAnswer = z.object({ message: z.string().min(1), choices: z.array(z.string()).max(4).default([]), draft: GuideTree.optional() })`
  - `export type GuideContext = { summary: unknown; goal: string; current: { name: string; parent: string; count: number }[]; draft?: GuideTreeT; turns?: { role: 'user' | 'assistant'; text: string }[] }`
  - `export async function proposeTrees(ctx: GuideContext, ask?: Asker): Promise<{ name: string; tree: GuideTreeT }[] | null>`
  - `export async function guideTurn(ctx: GuideContext, input: { text?: string; choice?: string; edit?: string }, ask?: Asker): Promise<z.infer<typeof TurnAnswer> | null>`
  - `export type Asker = (system: string, user: string) => Promise<string>` — the model call, injectable for tests; default `askModel`.

- [ ] **Step 1: Write the failing tests**

```ts
// service/lib/guide.test.ts
import { describe, it, expect } from 'vitest';
import { TurnAnswer, GuideTree, guideTurn, proposeTrees, guideRules } from './guide';

const ctx = {
  summary: { total: 641, groups: [{ size: 120, classes: ['landscape', 'mountain'] }] },
  goal: 'tech blog',
  current: [{ name: 'Apparel', parent: '', count: 30 }],
};

describe('guide schemas', () => {
  it('accepts a turn answer with a draft and choices', () => {
    const r = TurnAnswer.safeParse({
      message: 'Size as folders, brand as a tag?',
      choices: ['Size as folders', 'Brand as folders'],
      draft: { folders: [{ name: 'Monitors', parent: '', matches: 'monitors', classes: ['monitor'], kinds: ['photo'], audience: '' }], tags: [{ name: 'Brand', values: ['Dell', 'LG'] }] },
    });
    expect(r.success).toBe(true);
  });
  it('refuses a folder name with a slash', () => {
    const r = GuideTree.safeParse({ folders: [{ name: 'Apparel / Men', parent: '', matches: '', classes: [], kinds: [], audience: '' }] });
    expect(r.success).toBe(false);
  });
  it('refuses more than four choices', () => {
    const r = TurnAnswer.safeParse({ message: 'x', choices: ['a', 'b', 'c', 'd', 'e'] });
    expect(r.success).toBe(false);
  });
});

describe('guideTurn', () => {
  it('returns the parsed answer from the model', async () => {
    const ask = async () => JSON.stringify({ message: 'Done.', choices: [], draft: { folders: [], tags: [] } });
    const a = await guideTurn(ctx, { text: 'hi' }, ask);
    expect(a?.message).toBe('Done.');
  });
  it('retries once with the validation error, then gives up', async () => {
    const seen: string[] = [];
    const ask = async (_s: string, u: string) => { seen.push(u); return 'not json at all'; };
    const a = await guideTurn(ctx, { text: 'hi' }, ask);
    expect(a).toBeNull();
    expect(seen).toHaveLength(2);
    expect(seen[1]).toContain('did not parse');
  });
  it('puts a hand edit into the user turn as words', async () => {
    let user = '';
    const ask = async (_s: string, u: string) => { user = u; return JSON.stringify({ message: 'Noted.', choices: [] }); };
    await guideTurn(ctx, { edit: 'renamed Monitors to Displays' }, ask);
    expect(user).toContain('renamed Monitors to Displays');
  });
});

describe('proposeTrees', () => {
  it('returns two named trees', async () => {
    const ask = async () => JSON.stringify({ proposals: [
      { name: 'By what the pictures are', tree: { folders: [], tags: [] } },
      { name: 'By how you publish', tree: { folders: [], tags: [] } },
    ] });
    const p = await proposeTrees(ctx, ask);
    expect(p?.map((x) => x.name)).toEqual(['By what the pictures are', 'By how you publish']);
  });
});

describe('guideRules', () => {
  it('states the one-axis rule and the ask-rather-than-guess rule', () => {
    expect(guideRules).toMatch(/nests one way/);
    expect(guideRules).toMatch(/ask/i);
  });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd service && pnpm vitest run lib/guide.test.ts`
Expected: FAIL — cannot resolve `./guide`.

- [ ] **Step 3: Write `lib/guide.ts`**

```ts
// service/lib/guide.ts
import { z } from 'zod';
import { anthropic, wireModel, provider, providerPreferences, looseJson, FolderShape } from './anthropic';

/*
 *  The guide: a conversation that converges on a folder tree.
 *
 *  Two calls. `propose` draws two starting trees from the library's summary
 *  and the site's goal. `turn` takes the whole session -- summary, goal, the
 *  draft, the last twenty turns -- plus one new input, and answers with a
 *  message and either an updated draft or a question with concrete choices.
 *  It never files anything; the plugin does that, after the user confirms.
 */

export const GuideFolder = FolderShape.extend({
  matches: z.string().default(''),
  count: z.number().int().nonnegative().optional(),
}).refine((f) => !f.name.includes('/'), { message: 'a folder name is a label, not a path' });

export const GuideTree = z.object({
  folders: z.array(GuideFolder),
  tags: z.array(z.object({ name: z.string().min(1), values: z.array(z.string()).default([]) })).default([]),
});
export type GuideTreeT = z.infer<typeof GuideTree>;

export const TurnAnswer = z.object({
  message: z.string().min(1),
  choices: z.array(z.string().min(1)).max(4).default([]),
  draft: GuideTree.optional(),
});
export type TurnAnswerT = z.infer<typeof TurnAnswer>;

const Proposals = z.object({
  proposals: z.array(z.object({ name: z.string().min(1), tree: GuideTree })).min(1).max(2),
});

export type GuideContext = {
  summary: unknown;
  goal: string;
  current: { name: string; parent: string; count: number }[];
  draft?: GuideTreeT | undefined;
  turns?: { role: 'user' | 'assistant'; text: string }[] | undefined;
};

export type Asker = (system: string, user: string) => Promise<string>;

export function guideModel(): string {
  return process.env['ANTHROPIC_GUIDE_MODEL'] ?? 'claude-opus-5';
}

/** The rules the assistant applies and explains. Tested by name. */
export const guideRules =
  'You help the owner of a WordPress media library arrive at a folder structure that fits their site. '
  + 'You reason from the evidence you are given (the library summary: groups, counts, what share of pictures name a brand, a size, an audience) and from what the owner says. Rules:\n'
  + '1. A folder tree nests one way. When the owner wants two or three axes (by size, colour and brand), choose ONE axis as folders on the evidence -- the one named most often and grouping most evenly -- propose the others as tags, show the consequence, and ask which they meant.\n'
  + '2. A folder needs pictures behind it. Use the group sizes and the evidence shares; do not propose a folder the catalogue cannot fill, and when asked for one, say the number ("only 8% name a colour; that folder would be mostly empty").\n'
  + '3. Split by audience (men, women, kids) only where the audience share supports it; say the number.\n'
  + '4. Kinds are gates: logos, screenshots, diagrams, documents get folders of their own kind or none.\n'
  + '5. Keep the names the owner gave. Never rename silently. Never put a slash in a name; nesting is expressed only through "parent".\n'
  + '6. When something is unclear, ask ONE question with two to four concrete choices rather than guess. When a request contradicts the evidence, say the evidence and ask; do not refuse.\n'
  + '7. Answer the question behind the words. "I run a tech blog" means folders follow topics and publishing, not dates.\n'
  + 'You never act on the library. You only change the draft. The owner files it when they confirm.';

const shape =
  'A folder is {"name": string, "parent": string, "matches": string, "classes": string[], "kinds": string[], "audience": string, "count": number}. '
  + '"name" is a label, sentence case, never a path. "parent" is the exact name of another folder in the same list or "". '
  + '"matches" is a short visual phrase of what belongs there. "classes" are the object classes a picture would carry ("footwear", "monitor"), two to five, most important first. '
  + '"kinds" from photo, illustration, screenshot, document, diagram, logo. "audience" is "men", "women", "kids" or "". "count" is your estimate from the evidence. '
  + 'A tree is {"folders": Folder[], "tags": [{"name": string, "values": string[]}]}. Reply with JSON and nothing else: no prose before or after, no code fence.';

async function askModel(system: string, user: string): Promise<string> {
  const routing = provider() === 'openrouter' ? { provider: providerPreferences() } : {};
  const response = (await anthropic().messages.create({
    ...routing,
    model: wireModel(guideModel()),
    max_tokens: 6000,
    // No extended thinking: measured 4 Sept 2026, the planning model spent
    // every output token thinking and returned no text.
    thinking: { type: 'disabled' },
    system,
    messages: [{ role: 'user', content: user }],
  })) as { content: { type: string; text?: string }[] };
  return (response.content ?? []).filter((c) => c.type === 'text').map((c) => c.text ?? '').join('');
}

function contextBlock(ctx: GuideContext): string {
  const current = ctx.current.length === 0
    ? 'There are no folders yet.'
    : 'Folders that exist now:\n' + ctx.current.map((c) => `- ${c.parent !== '' ? c.parent + ' / ' : ''}${c.name} (${c.count})`).join('\n');
  return `Library summary (JSON):\n${JSON.stringify(ctx.summary)}\n\n${current}\n\nThe owner's goal: ${ctx.goal.trim() === '' ? '(not stated)' : ctx.goal.trim()}`;
}

/** Ask, validate, and on a bad answer ask once more with the error in hand. */
async function askValidated<T>(schema: z.ZodType<T>, system: string, user: string, ask: Asker): Promise<T | null> {
  const first = await ask(system, user);
  const p1 = schema.safeParse(looseJson(first));
  if (p1.success) return p1.data;
  const why = p1.error.issues[0]?.message ?? 'invalid';
  const second = await ask(system, `${user}\n\nYour previous answer did not parse (${why}). Answer again, JSON only, in the shape described.`);
  const p2 = schema.safeParse(looseJson(second));
  if (p2.success) return p2.data;
  console.error('[ai/guide] answer refused twice', { why, head: second.slice(0, 200) });
  return null;
}

export async function proposeTrees(ctx: GuideContext, ask: Asker = askModel): Promise<{ name: string; tree: GuideTreeT }[] | null> {
  const system = `${guideRules}\n\n${shape}\nReply exactly {"proposals": [{"name": "By what the pictures are", "tree": Tree}, {"name": "By how you publish", "tree": Tree}]}. `
    + 'The first follows the library\'s own groups. The second follows how the site publishes, from the goal; if no goal is stated, read it from the summary and say so in the folders\' "matches".';
  const user = `${contextBlock(ctx)}\n\nPropose the two trees. Every folder gets a "count" estimated from the evidence.`;
  const out = await askValidated(Proposals, system, user, ask);
  return out === null ? null : out.proposals;
}

export async function guideTurn(ctx: GuideContext, input: { text?: string; choice?: string; edit?: string }, ask: Asker = askModel): Promise<TurnAnswerT | null> {
  const said = input.edit !== undefined
    ? `The owner edited the draft by hand: ${input.edit}.`
    : input.choice !== undefined
      ? `The owner chose: "${input.choice}".`
      : `The owner says: "${(input.text ?? '').trim()}"`;
  const history = (ctx.turns ?? []).slice(-20).map((t) => `${t.role === 'user' ? 'Owner' : 'You'}: ${t.text}`).join('\n');
  const system = `${guideRules}\n\n${shape}\nReply exactly {"message": string, "choices": string[], "draft": Tree | omitted}. `
    + '"message" is short and speaks to the owner. Include "choices" (two to four) only when you are asking a question. Include "draft" only when you changed the tree, and then send the WHOLE tree, not a diff.';
  const user = `${contextBlock(ctx)}\n\nThe current draft (JSON):\n${JSON.stringify(ctx.draft ?? { folders: [], tags: [] })}\n\nThe conversation so far:\n${history === '' ? '(none)' : history}\n\n${said}`;
  return askValidated(TurnAnswer, system, user, ask);
}
```

Note: `provider`, `providerPreferences`, `anthropic`, `wireModel`, `looseJson` and `FolderShape` must be exported from `lib/anthropic.ts`. Check with `grep -nE "^export (function|const) (provider|providerPreferences|anthropic|wireModel|looseJson|FolderShape)" service/lib/anthropic.ts`; add `export` to any that is missing (they are module-level today).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd service && pnpm vitest run lib/guide.test.ts && npx tsc --noEmit`
Expected: 8 passed; tsc silent.

- [ ] **Step 5: Commit**

```bash
cd service && git add lib/guide.ts lib/guide.test.ts lib/anthropic.ts && git commit -m "feat(guide): schemas, rules and the two model calls behind the guided sorting flow"
```

---

### Task 2: Service — the route `/api/ai/guide` (`/v1/guide` by rewrite)

**Files:**
- Create: `service/app/api/ai/guide/route.ts`
- Create: `service/app/api/ai/guide/route.test.ts`
- Read for patterns: `service/app/api/ai/folders/route.ts` (the checks in order: licensing, AI enabled, body, key, site, licence, entitlement, activation)

**Interfaces:**
- Consumes: `proposeTrees`, `guideTurn`, `GuideTree` from `@/lib/guide`; `store`, `licensingEnabled` from `@/lib/stripe`; `entitlement`, `isValidKeyShape`, `normaliseSite` from `@/lib/licence`.
- Produces: `POST /api/ai/guide` with body `{ key|license_key, site, mode: 'propose'|'turn', summary, goal, current, draft?, turns?, input? }` → `200 { proposals }` or `200 { message, choices, draft? }`; errors `400 invalid_body|invalid_mode|too_many_turns`, `401 bad_key`, `403 not_found|not_entitled|site_not_activated`, `502 could_not_answer`, `503 licensing_disabled|ai_disabled`.

- [ ] **Step 1: Write the failing test**

```ts
// service/app/api/ai/guide/route.test.ts
import { describe, it, expect, vi } from 'vitest';

vi.mock('@/lib/stripe', () => ({ licensingEnabled: () => true, store: () => ({
  findLicenceByKey: async () => ({ id: 1, status: 'active', currentPeriodEnd: null, plan: 'agency' }),
  listActivations: async () => [{ site: 'https://example.com' }],
}) }));
vi.mock('@/lib/guide', () => ({
  proposeTrees: async () => [{ name: 'A', tree: { folders: [], tags: [] } }, { name: 'B', tree: { folders: [], tags: [] } }],
  guideTurn: async () => ({ message: 'ok', choices: [] }),
  GuideTree: { safeParse: () => ({ success: true, data: { folders: [], tags: [] } }) },
}));
process.env['AI_RELAY_ENABLED'] = 'true';

import { POST } from './route';

const body = (extra: Record<string, unknown>) => new Request('http://x/api/ai/guide', {
  method: 'POST', headers: { 'content-type': 'application/json' },
  body: JSON.stringify({ license_key: 'VGML-ABCDEFGHJKLMNPQRSTUVWXYZ2345', site: 'https://example.com', summary: {}, goal: '', current: [], ...extra }),
});

describe('POST /api/ai/guide', () => {
  it('refuses an unknown mode', async () => {
    const r = await POST(body({ mode: 'dance' }));
    expect(r.status).toBe(400);
  });
  it('proposes', async () => {
    const r = await POST(body({ mode: 'propose' }));
    expect(r.status).toBe(200);
    expect((await r.json()).proposals).toHaveLength(2);
  });
  it('answers a turn', async () => {
    const r = await POST(body({ mode: 'turn', input: { text: 'hi' }, turns: [] }));
    expect((await r.json()).message).toBe('ok');
  });
  it('refuses more than sixty turns of history', async () => {
    const turns = Array.from({ length: 61 }, () => ({ role: 'user', text: 'x' }));
    const r = await POST(body({ mode: 'turn', input: { text: 'hi' }, turns }));
    expect(r.status).toBe(400);
  });
});
```

If `isValidKeyShape` rejects the sample key, copy a key from `lib/licence.test.ts` that it accepts.

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd service && pnpm vitest run app/api/ai/guide/route.test.ts`
Expected: FAIL — cannot resolve `./route`.

- [ ] **Step 3: Write the route**

```ts
// service/app/api/ai/guide/route.ts
import { NextResponse } from 'next/server';
import { proposeTrees, guideTurn, GuideTree } from '@/lib/guide';
import { store, licensingEnabled } from '@/lib/stripe';
import { entitlement, isValidKeyShape, normaliseSite } from '@/lib/licence';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';
export const maxDuration = 120;

/*
 *  POST /api/ai/guide  { key, site, mode: 'propose' | 'turn', summary, goal, current, draft?, turns?, input? }
 *
 *  The conversation behind the guided sorting screen. No credit is charged:
 *  the session is included in the plan and capped by the plugin at 25
 *  assistant turns; sixty turns of history is refused here as a backstop.
 */

const MAX_TURNS = 60;

function aiEnabled(): boolean {
  return process.env['AI_RELAY_ENABLED'] === 'true';
}

export async function POST(req: Request): Promise<NextResponse> {
  if (!licensingEnabled()) return NextResponse.json({ error: 'licensing_disabled' }, { status: 503 });
  if (!aiEnabled()) return NextResponse.json({ error: 'ai_disabled' }, { status: 503 });

  let body: unknown;
  try { body = await req.json(); } catch { return NextResponse.json({ error: 'invalid_body' }, { status: 400 }); }
  if (typeof body !== 'object' || body === null) return NextResponse.json({ error: 'invalid_body' }, { status: 400 });
  const raw = body as Record<string, unknown>;

  const key = raw['key'] ?? raw['license_key'];
  const site = normaliseSite(raw['site']);
  if (typeof key !== 'string' || !isValidKeyShape(key)) return NextResponse.json({ error: 'bad_key' }, { status: 401 });
  if (site === null) return NextResponse.json({ error: 'invalid_site' }, { status: 400 });

  const mode = raw['mode'];
  if (mode !== 'propose' && mode !== 'turn') return NextResponse.json({ error: 'invalid_mode' }, { status: 400 });

  const s = store();
  const licence = await s.findLicenceByKey(key);
  if (licence === null) return NextResponse.json({ error: 'not_found' }, { status: 403 });
  const ent = entitlement({ status: licence.status, currentPeriodEnd: licence.currentPeriodEnd, plan: licence.plan }, new Date());
  if (!ent.entitled) return NextResponse.json({ error: 'not_entitled' }, { status: 403 });
  const activations = await s.listActivations(licence.id);
  if (!activations.some((a) => a.site === site)) return NextResponse.json({ error: 'site_not_activated' }, { status: 403 });

  const goal = typeof raw['goal'] === 'string' ? raw['goal'].slice(0, 1000) : '';
  const current = Array.isArray(raw['current'])
    ? (raw['current'] as unknown[]).filter((c): c is Record<string, unknown> => typeof c === 'object' && c !== null)
        .map((c) => ({ name: String(c['name'] ?? '').slice(0, 120), parent: String(c['parent'] ?? '').slice(0, 120), count: Number(c['count'] ?? 0) || 0 }))
        .filter((c) => c.name !== '')
    : [];
  const summary = typeof raw['summary'] === 'object' && raw['summary'] !== null ? raw['summary'] : {};

  if (mode === 'propose') {
    const proposals = await proposeTrees({ summary, goal, current });
    if (proposals === null) return NextResponse.json({ error: 'could_not_answer' }, { status: 502 });
    return NextResponse.json({ proposals });
  }

  const turnsRaw = Array.isArray(raw['turns']) ? (raw['turns'] as unknown[]) : [];
  if (turnsRaw.length > MAX_TURNS) return NextResponse.json({ error: 'too_many_turns' }, { status: 400 });
  const turns = turnsRaw
    .filter((t): t is Record<string, unknown> => typeof t === 'object' && t !== null)
    .map((t) => ({ role: t['role'] === 'assistant' ? ('assistant' as const) : ('user' as const), text: String(t['text'] ?? '').slice(0, 2000) }));
  const draftParsed = GuideTree.safeParse(raw['draft']);
  const draft = draftParsed.success ? draftParsed.data : undefined;
  const inputRaw = typeof raw['input'] === 'object' && raw['input'] !== null ? (raw['input'] as Record<string, unknown>) : {};
  const input = {
    text: typeof inputRaw['text'] === 'string' ? inputRaw['text'].slice(0, 2000) : undefined,
    choice: typeof inputRaw['choice'] === 'string' ? inputRaw['choice'].slice(0, 200) : undefined,
    edit: typeof inputRaw['edit'] === 'string' ? inputRaw['edit'].slice(0, 500) : undefined,
  };
  if (input.text === undefined && input.choice === undefined && input.edit === undefined) {
    return NextResponse.json({ error: 'empty_input' }, { status: 400 });
  }

  const answer = await guideTurn({ summary, goal, current, draft, turns }, input);
  if (answer === null) return NextResponse.json({ error: 'could_not_answer' }, { status: 502 });
  return NextResponse.json(answer);
}
```

With `exactOptionalPropertyTypes` on, pass `draft` and the `input` fields as `| undefined` types (the `GuideContext` type already allows `undefined`).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd service && pnpm vitest run app/api/ai/guide/route.test.ts && npx tsc --noEmit`
Expected: 4 passed; tsc silent.

- [ ] **Step 5: Commit, push, deploy the on-box service**

```bash
cd service && git add app/api/ai/guide && git commit -m "feat(guide): the /v1/guide route, propose and turn" && git push origin HEAD
gh workflow run vps.yml --repo vergelabsnathan/vergelabsmedia
```

The `/v1/*` → `/api/*` rewrite in `next.config.mjs` already covers `/v1/guide`.

---

### Task 3: Plugin — session, summary, estimation and REST routes (`core/guide.php`)

**Files:**
- Create: `plugin/core/guide.php`
- Modify: `plugin/vergelabs-media-library.php` — add `include_once( 'core/guide.php' );` after `core/folder-talk.php`
- Create: `plugin/tools/box-guide-walk.php`, `plugin/tools/box-guide-walk.sh`; add job `guide-walk` to `.github/workflows/box-fix.yml` (same shape as the `plan` job)

**Interfaces:**
- Consumes: `vergeml_talk_groups()`, `vergeml_talk_samples()`, `vergeml_talk_current()`, `vergeml_talk_apply( $folders )`, `vergeml_talk_report( $state )`, `vergeml_talk_refile_schedule()`, `vergeml_librarian_taxonomy()`, `vergeml_ai_settings()`, `vergeml_ai_unseal()`, `vergeml_ai_service_url()`, `vergeml_filing_classes_of_object()`, `vergeml_filing_class_match()` (string parts only — see estimate), constants `VERGEML_REST_NS`, `VERGEML_TALK_STATE`.
- Produces:
  - `const VERGEML_GUIDE_OPTION = 'vergeml_guide_session'`, `const VERGEML_GUIDE_TURN_CAP = 25`
  - `vergeml_guide_session()` → array (fresh if none); `vergeml_guide_save( $session )`
  - `vergeml_guide_summary()` → array `{ total, described_at, folders, groups: [{size, classes[], kinds[], thumbs[]}], evidence: {brand, size, audience, kinds: {photo:…}} }`
  - `vergeml_guide_estimate( $folders )` → the same folders with `count` filled
  - `vergeml_guide_call( $mode, $payload )` → array|WP_Error (the service call)
  - REST: `GET|POST /guide/session`, `POST /guide/summary`, `POST /guide/propose`, `POST /guide/turn`, `POST /guide/apply`, `GET /guide/progress`
  - Page: `admin.php?page=media-guide` → `vergeml_guide_page()` prints `<div id="vgml-guide" class="vgml-guide"></div>` inside the shell and enqueues the screen (Task 4 provides the JS)

- [ ] **Step 1: Write the box walk (the test), so it fails first**

```php
<?php
// plugin/tools/box-guide-walk.php — a scripted session through the REST callbacks. Read-mostly:
// it applies only when VGML_APPLY=1. Run as an administrator (--user=1).
$apply = '1' === (string) getenv( 'VGML_APPLY' );
$req = function ( $method, $params = array() ) { $r = new WP_REST_Request( $method ); foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); } return $r; };
$show = function ( $label, $res ) { $d = $res instanceof WP_REST_Response ? $res->get_data() : $res; printf( "%s: %s\n", $label, is_wp_error( $d ) ? 'ERROR ' . $d->get_error_message() : mb_substr( wp_json_encode( $d ), 0, 400 ) ); return $d; };

delete_option( VERGEML_GUIDE_OPTION );
$s = $show( 'session', vergeml_guide_rest_session( $req( 'GET' ) ) );
if ( 'library' !== ( $s['state'] ?? '' ) ) { echo "FAIL: fresh session is not at 'library'\n"; return; }

$sum = $show( 'summary', vergeml_guide_rest_summary( $req( 'POST' ) ) );
if ( empty( $sum['total'] ) || empty( $sum['groups'] ) ) { echo "FAIL: summary has no total or groups\n"; return; }

$p = $show( 'propose', vergeml_guide_rest_propose( $req( 'POST', array( 'goal' => 'a fashion and lifestyle shop' ) ) ) );
if ( empty( $p['proposals'][0]['tree']['folders'] ) ) { echo "FAIL: no proposal\n"; return; }
$first = $p['proposals'][0]['tree'];
printf( "first proposal: %d folders, counts: %s\n", count( $first['folders'] ), implode( ', ', array_map( function ( $f ) { return $f['name'] . '=' . ( $f['count'] ?? '?' ); }, array_slice( $first['folders'], 0, 6 ) ) ) );

$show( 'start from first', vergeml_guide_rest_session( $req( 'POST', array( 'session' => array_merge( vergeml_guide_session(), array( 'state' => 'shaping', 'draft' => array_merge( $first, array( 'version' => 1 ) ) ) ) ) ) ) );
$t1 = $show( 'turn 1', vergeml_guide_rest_turn( $req( 'POST', array( 'text' => 'I want shoes split by size, colour and brand.' ) ) ) );
if ( empty( $t1['message'] ) ) { echo "FAIL: no message\n"; return; }
$t2 = $show( 'turn 2', vergeml_guide_rest_turn( $req( 'POST', array( 'choice' => $t1['choices'][0] ?? 'Size as folders' ) ) ) );
$sess = vergeml_guide_session();
printf( "assistant turns used: %d of %d; draft version %d with %d folders\n", $sess['assistant_turns'], VERGEML_GUIDE_TURN_CAP, $sess['draft']['version'] ?? 0, count( $sess['draft']['folders'] ?? array() ) );

if ( ! $apply ) { echo "dry run: not applying\n"; return; }
$a = $show( 'apply', vergeml_guide_rest_apply( $req( 'POST' ) ) );
for ( $i = 0; $i < 120; $i++ ) { wp_cache_delete( VERGEML_TALK_STATE, 'options' ); $st = get_option( VERGEML_TALK_STATE ); if ( ! is_array( $st ) || empty( $st['active'] ) ) { break; } vergeml_talk_refile_run( time() + 20 ); }
$show( 'progress', vergeml_guide_rest_progress( $req( 'GET' ) ) );
```

```bash
# plugin/tools/box-guide-walk.sh
set -e
cd /var/www/wp
VGML_APPLY="${VGML_APPLY:-0}" wp eval-file /tmp/vgml-guide-walk.php --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-guide-walk.php
```

Wire the job in `.github/workflows/box-fix.yml`: add `- guide-walk` to the `job` options and a step identical to the `plan` step but with `tools/box-guide-walk.php` → `/tmp/vgml-guide-walk.php` and `tools/box-guide-walk.sh`, passing `VGML_APPLY='${{ inputs.apply }}'`.

- [ ] **Step 2: Run it to verify it fails**

Run: push, then `gh workflow run box-fix.yml -f job=guide-walk -f apply=0`, read the log.
Expected: fatal `Call to undefined function vergeml_guide_rest_session()`.

- [ ] **Step 3: Write `core/guide.php`**

```php
<?php
/**
 *  Guided sorting: the session, the summary, the estimates, the routes.
 *
 *  Four screens converge on a folder tree in conversation with an assistant
 *  that only ever edits the draft. The library is touched once, on the last
 *  click, through the same apply and re-filing every other path uses.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_GUIDE_OPTION   = 'vergeml_guide_session';
const VERGEML_GUIDE_TURN_CAP = 25;
const VERGEML_GUIDE_SAMPLE   = 2000;

/* --------------------------------------------------------------- the page */

add_action( 'admin_menu', 'vergeml_guide_menu', 23 );

function vergeml_guide_menu() {
    add_submenu_page( VERGEML_MENU, __( 'Sort with a guide', 'vergelabs-media-library' ), __( 'Sort with a guide', 'vergelabs-media-library' ), 'manage_categories', 'media-guide', 'vergeml_guide_page' );
}

function vergeml_guide_page() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
    echo '<div class="wrap vgml-guide-wrap"><div id="vgml-guide" class="vgml-guide" data-described="' . esc_attr( (string) vergeml_guide_described_count() ) . '"></div></div>';
}

add_action( 'admin_enqueue_scripts', 'vergeml_guide_assets' );

function vergeml_guide_assets( $hook ) {
    if ( false === strpos( (string) $hook, 'media-guide' ) ) {
        return;
    }
    $base = plugin_dir_url( dirname( __FILE__ ) );
    $ver  = defined( 'VERGEML_VERSION' ) ? VERGEML_VERSION : '1';
    wp_enqueue_style( 'vergeml-guide', $base . 'css/vergeml-guide.css', array(), $ver );
    wp_style_add_data( 'vergeml-guide', 'rtl', 'replace' );
    wp_enqueue_script( 'vergeml-guide', $base . 'js/vergeml-guide.js', array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ), $ver, true );
    wp_localize_script( 'vergeml-guide', 'vgmlGuide', array(
        'ns'        => VERGEML_REST_NS,
        'cap'       => VERGEML_GUIDE_TURN_CAP,
        'foldersUrl' => admin_url( 'admin.php?page=media-taxonomies' ),
        'aiUrl'     => admin_url( 'admin.php?page=media-ai' ),
    ) );
}

/** How many pictures are described; the guide only opens on a described library. */
function vergeml_guide_described_count() {
    global $wpdb;
    if ( ! isset( $wpdb->vergeml_ai_index ) ) {
        return 0;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL" );
}

/* ------------------------------------------------------------ the session */

function vergeml_guide_fresh() {
    return array(
        'version'         => 1,
        'state'           => 'library',
        'started_at'      => time(),
        'updated_at'      => time(),
        'goal'            => '',
        'summary'         => null,
        'proposals'       => array(),
        'draft'           => array( 'version' => 0, 'folders' => array(), 'tags' => array() ),
        'history'         => array(),
        'turns'           => array(),
        'assistant_turns' => 0,
        'apply'           => null,
    );
}

function vergeml_guide_session() {
    $s = get_option( VERGEML_GUIDE_OPTION );
    return is_array( $s ) && isset( $s['state'] ) ? $s : vergeml_guide_fresh();
}

function vergeml_guide_save( $session ) {
    $session['updated_at'] = time();
    // Bounded: the last ten drafts, the last sixty turns.
    $session['history'] = array_slice( (array) ( $session['history'] ?? array() ), -10 );
    $session['turns']   = array_slice( (array) ( $session['turns'] ?? array() ), -60 );
    update_option( VERGEML_GUIDE_OPTION, $session, false );
    return $session;
}

/** A tree from the client, made safe. Names never carry a slash. */
function vergeml_guide_clean_tree( $tree ) {
    $out = array( 'version' => isset( $tree['version'] ) ? (int) $tree['version'] : 0, 'folders' => array(), 'tags' => array() );
    foreach ( (array) ( $tree['folders'] ?? array() ) as $f ) {
        if ( ! is_array( $f ) || empty( $f['name'] ) ) {
            continue;
        }
        $out['folders'][] = array(
            'name'     => str_replace( '/', '-', sanitize_text_field( (string) $f['name'] ) ),
            'parent'   => sanitize_text_field( (string) ( $f['parent'] ?? '' ) ),
            'matches'  => sanitize_text_field( (string) ( $f['matches'] ?? '' ) ),
            'classes'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $f['classes'] ?? array() ) ) ) ),
            'kinds'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( $f['kinds'] ?? array() ) ) ) ),
            'audience' => sanitize_text_field( (string) ( $f['audience'] ?? '' ) ),
            'count'    => isset( $f['count'] ) ? (int) $f['count'] : 0,
        );
    }
    foreach ( (array) ( $tree['tags'] ?? array() ) as $t ) {
        if ( is_array( $t ) && ! empty( $t['name'] ) ) {
            $out['tags'][] = array( 'name' => sanitize_text_field( (string) $t['name'] ), 'values' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $t['values'] ?? array() ) ) ) ) );
        }
    }
    return $out;
}

/* ------------------------------------------------------------ the summary */

function vergeml_guide_summary() {
    global $wpdb;
    $t = $wpdb->vergeml_ai_index;
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL" );
    $last  = (string) $wpdb->get_var( "SELECT MAX(described_at) FROM {$t} WHERE error = ''" );
    $kinds = $wpdb->get_results( "SELECT kind, COUNT(*) AS n FROM {$t} WHERE error = '' AND embedding IS NOT NULL GROUP BY kind", ARRAY_A );
    $n_brand    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND filing LIKE '%\"brand\":\"_%'" );
    $n_audience = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND filing LIKE '%\"audience\":\"_%'" );
    $n_size     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND ( filing REGEXP '[0-9]+(\\.[0-9]+)? ?(inch|\"|cm|mm|\\\")' OR caption REGEXP '[0-9]+(\\.[0-9]+)? ?(inch|\")' )" );
    // phpcs:enable

    // The groups the chat already computes, each described by its members' most common classes and kinds.
    $groups = array();
    foreach ( (array) vergeml_talk_groups() as $g ) {
        $groups[] = array( 'size' => (int) $g['size'], 'captions' => array_slice( (array) $g['captions'], 0, 3 ) );
    }
    // Class words per group are cheaper to read from the whole library once: the top classes overall, for the assistant.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, filing FROM {$t} WHERE error = '' AND filing IS NOT NULL ORDER BY described_at DESC LIMIT %d", VERGEML_GUIDE_SAMPLE ), ARRAY_A );
    $classes = array();
    foreach ( (array) $rows as $r ) {
        $f = json_decode( (string) $r['filing'], true );
        foreach ( vergeml_filing_classes_of_object( is_array( $f ) ? ( $f['object'] ?? '' ) : '' ) as $i => $c ) {
            if ( 1 === $i ) { // the class part, not the specific phrase
                $classes[ $c ] = ( $classes[ $c ] ?? 0 ) + 1;
            }
        }
    }
    arsort( $classes );
    $scale = count( $rows ) > 0 ? $total / count( $rows ) : 1;
    $top = array();
    foreach ( array_slice( $classes, 0, 24, true ) as $c => $n ) {
        $top[] = array( 'class' => $c, 'count' => (int) round( $n * $scale ) );
    }

    $folders = array();
    foreach ( (array) vergeml_talk_current() as $c ) {
        $folders[] = array( 'name' => (string) $c['name'], 'parent' => (string) ( $c['parent'] ?? '' ), 'count' => (int) ( $c['count'] ?? 0 ) );
    }
    $by_kind = array();
    foreach ( (array) $kinds as $k ) {
        $by_kind[ '' === (string) $k['kind'] ? 'photo' : (string) $k['kind'] ] = (int) $k['n'];
    }
    return array(
        'total'        => $total,
        'described_at' => $last,
        'folders'      => $folders,
        'groups'       => $groups,
        'classes'      => $top,
        'evidence'     => array(
            'brand'    => $total ? round( $n_brand / $total, 2 ) : 0,
            'size'     => $total ? round( $n_size / $total, 2 ) : 0,
            'audience' => $total ? round( $n_audience / $total, 2 ) : 0,
            'kinds'    => $by_kind,
        ),
        'samples'      => vergeml_talk_samples(),
    );
}

/* ----------------------------------------------------------- the estimate */

/**
 *  Real counts for a proposed tree, without embeddings: the share of a
 *  sample of catalogue records whose class words match the folder's classes
 *  (exact, plural, substring), scaled to the library. Deterministic and fast.
 */
function vergeml_guide_estimate( $folders ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT filing, kind FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY described_at DESC LIMIT %d", VERGEML_GUIDE_SAMPLE ), ARRAY_A );
    $total = vergeml_guide_described_count();
    $scale = count( $rows ) > 0 ? $total / count( $rows ) : 1;
    $facts = array();
    foreach ( (array) $rows as $r ) {
        $f = json_decode( (string) $r['filing'], true );
        $facts[] = array( 'classes' => vergeml_filing_classes_of_object( is_array( $f ) ? ( $f['object'] ?? '' ) : '' ), 'kind' => '' === (string) $r['kind'] ? 'photo' : (string) $r['kind'] );
    }
    foreach ( $folders as &$folder ) {
        $classes = array_map( 'mb_strtolower', (array) ( $folder['classes'] ?? array() ) );
        if ( ! $classes ) {
            $classes = array( mb_strtolower( (string) $folder['name'] ) );
        }
        $kinds = (array) ( $folder['kinds'] ?? array() );
        $n = 0;
        foreach ( $facts as $fact ) {
            if ( $kinds && ! in_array( $fact['kind'], $kinds, true ) ) {
                continue;
            }
            foreach ( $fact['classes'] as $pc ) {
                foreach ( $classes as $fc ) {
                    if ( $pc === $fc || rtrim( $pc, 's' ) === rtrim( $fc, 's' ) || false !== mb_strpos( ' ' . $pc . ' ', ' ' . $fc . ' ' ) || false !== mb_strpos( ' ' . $fc . ' ', ' ' . $pc . ' ' ) ) {
                        $n++;
                        continue 3;
                    }
                }
            }
        }
        $folder['count'] = (int) round( $n * $scale );
    }
    unset( $folder );
    return $folders;
}

/* ---------------------------------------------------------- the service */

function vergeml_guide_call( $mode, $payload ) {
    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence tab first.', 'vergelabs-media-library' ) );
    }
    $response = wp_remote_post( vergeml_ai_service_url() . '/guide', array(
        'timeout'   => 110,
        'headers'   => array( 'Content-Type' => 'application/json' ),
        'sslverify' => true,
        'body'      => wp_json_encode( array_merge( array( 'license_key' => $licence, 'site' => home_url(), 'mode' => $mode ), $payload ) ),
    ) );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'unreachable', __( 'Could not reach the service. Your draft is safe; try again in a moment.', 'vergelabs-media-library' ) );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || ! is_array( $data ) ) {
        $why = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : 'HTTP ' . $code;
        return new WP_Error( 'service_' . $code, 'could_not_answer' === $why ? __( 'I did not follow that. Say it another way.', 'vergelabs-media-library' ) : $why );
    }
    return $data;
}

/* --------------------------------------------------------------- routes */

add_action( 'rest_api_init', 'vergeml_guide_routes' );

function vergeml_guide_routes() {
    $may = function () { return current_user_can( 'manage_categories' ); };
    register_rest_route( VERGEML_REST_NS, '/guide/session', array(
        array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_guide_rest_session', 'permission_callback' => $may ),
        array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_guide_rest_session', 'permission_callback' => $may, 'args' => array( 'session' => array( 'type' => 'object', 'required' => true ) ) ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/summary', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_guide_rest_summary', 'permission_callback' => $may ) );
    register_rest_route( VERGEML_REST_NS, '/guide/propose', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_guide_rest_propose', 'permission_callback' => $may, 'args' => array( 'goal' => array( 'type' => 'string', 'required' => false ) ) ) );
    register_rest_route( VERGEML_REST_NS, '/guide/turn', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_guide_rest_turn', 'permission_callback' => $may, 'args' => array(
        'text' => array( 'type' => 'string', 'required' => false ), 'choice' => array( 'type' => 'string', 'required' => false ), 'edit' => array( 'type' => 'string', 'required' => false ),
        'draft' => array( 'type' => 'object', 'required' => false ),
    ) ) );
    register_rest_route( VERGEML_REST_NS, '/guide/apply', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_guide_rest_apply', 'permission_callback' => $may ) );
    register_rest_route( VERGEML_REST_NS, '/guide/progress', array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_guide_rest_progress', 'permission_callback' => $may ) );
}

function vergeml_guide_rest_session( WP_REST_Request $request ) {
    if ( 'POST' === $request->get_method() ) {
        $in  = (array) $request->get_param( 'session' );
        $cur = vergeml_guide_session();
        $allowed = array( 'library', 'proposal', 'shaping', 'review', 'applying', 'done' );
        $cur['state'] = in_array( (string) ( $in['state'] ?? '' ), $allowed, true ) ? (string) $in['state'] : $cur['state'];
        $cur['goal']  = sanitize_textarea_field( (string) ( $in['goal'] ?? $cur['goal'] ) );
        if ( isset( $in['draft'] ) ) {
            $draft = vergeml_guide_clean_tree( $in['draft'] );
            if ( wp_json_encode( $draft ) !== wp_json_encode( $cur['draft'] ) ) {
                $cur['history'][] = array( 'version' => (int) $cur['draft']['version'], 'draft' => $cur['draft'] );
                $draft['version'] = (int) $cur['draft']['version'] + 1;
                $cur['draft']     = $draft;
            }
        }
        // assistant_turns and apply stay the server's.
        return rest_ensure_response( vergeml_guide_save( $cur ) );
    }
    return rest_ensure_response( vergeml_guide_session() );
}

function vergeml_guide_rest_summary( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    $described = vergeml_guide_described_count();
    if ( ! is_array( $s['summary'] ) || (int) ( $s['summary']['total'] ?? -1 ) !== $described ) {
        $s['summary'] = vergeml_guide_summary();
        $s = vergeml_guide_save( $s );
    }
    return rest_ensure_response( $s['summary'] );
}

function vergeml_guide_rest_propose( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( null !== $request->get_param( 'goal' ) ) {
        $s['goal'] = sanitize_textarea_field( (string) $request->get_param( 'goal' ) );
    }
    if ( ! is_array( $s['summary'] ) ) {
        $s['summary'] = vergeml_guide_summary();
    }
    $data = vergeml_guide_call( 'propose', array( 'summary' => $s['summary'], 'goal' => $s['goal'], 'current' => $s['summary']['folders'] ) );
    if ( is_wp_error( $data ) ) {
        return $data;
    }
    $proposals = array();
    foreach ( (array) ( $data['proposals'] ?? array() ) as $p ) {
        $tree = vergeml_guide_clean_tree( (array) ( $p['tree'] ?? array() ) );
        $tree['folders'] = vergeml_guide_estimate( $tree['folders'] );
        $proposals[] = array( 'name' => sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 'tree' => $tree );
    }
    $s['proposals'] = $proposals;
    $s['state']     = 'proposal';
    vergeml_guide_save( $s );
    return rest_ensure_response( array( 'proposals' => $proposals ) );
}

function vergeml_guide_rest_turn( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( (int) $s['assistant_turns'] >= VERGEML_GUIDE_TURN_CAP ) {
        return new WP_Error( 'cap', sprintf( __( 'This session has used its %d turns. You can still shape the tree by hand and file it.', 'vergelabs-media-library' ), VERGEML_GUIDE_TURN_CAP ), array( 'status' => 429 ) );
    }
    if ( null !== $request->get_param( 'draft' ) ) {
        $s['draft'] = array_merge( vergeml_guide_clean_tree( $request->get_param( 'draft' ) ), array( 'version' => (int) $s['draft']['version'] ) );
    }
    $input = array();
    foreach ( array( 'text', 'choice', 'edit' ) as $k ) {
        if ( null !== $request->get_param( $k ) && '' !== trim( (string) $request->get_param( $k ) ) ) {
            $input[ $k ] = sanitize_textarea_field( (string) $request->get_param( $k ) );
        }
    }
    if ( ! $input ) {
        return new WP_Error( 'empty', __( 'Say something first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $said = $input['text'] ?? ( isset( $input['choice'] ) ? $input['choice'] : sprintf( __( 'I %s', 'vergelabs-media-library' ), $input['edit'] ) );
    $s['turns'][] = array( 'role' => 'user', 'text' => $said, 'at' => time() );

    $data = vergeml_guide_call( 'turn', array(
        'summary' => $s['summary'], 'goal' => $s['goal'], 'current' => (array) ( $s['summary']['folders'] ?? array() ),
        'draft'   => $s['draft'], 'turns' => array_slice( $s['turns'], -20 ), 'input' => $input,
    ) );
    if ( is_wp_error( $data ) ) {
        vergeml_guide_save( $s );
        return $data;
    }
    $answer = array( 'message' => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ), 'choices' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $data['choices'] ?? array() ) ) ) ) );
    if ( isset( $data['draft'] ) && is_array( $data['draft'] ) ) {
        $draft = vergeml_guide_clean_tree( $data['draft'] );
        $draft['folders'] = vergeml_guide_estimate( $draft['folders'] );
        $s['history'][]   = array( 'version' => (int) $s['draft']['version'], 'draft' => $s['draft'] );
        $draft['version'] = (int) $s['draft']['version'] + 1;
        $s['draft']       = $draft;
        $answer['draft']  = $draft;
    }
    $s['turns'][] = array( 'role' => 'assistant', 'text' => $answer['message'], 'choices' => $answer['choices'], 'at' => time() );
    $s['assistant_turns'] = (int) $s['assistant_turns'] + 1;
    $s['state'] = 'shaping';
    vergeml_guide_save( $s );
    $answer['assistant_turns'] = $s['assistant_turns'];
    return rest_ensure_response( $answer );
}

function vergeml_guide_rest_apply( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    $folders = array();
    foreach ( (array) $s['draft']['folders'] as $f ) {
        $folders[] = array( 'name' => $f['name'], 'parent' => $f['parent'], 'matches' => $f['matches'], 'classes' => $f['classes'], 'kinds' => $f['kinds'], 'audience' => $f['audience'] );
    }
    if ( ! $folders ) {
        return new WP_Error( 'empty', __( 'The draft has no folders.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $r = vergeml_talk_apply( $folders );
    if ( is_wp_error( $r ) ) {
        return $r;
    }
    $s['state'] = 'applying';
    $s['apply'] = array( 'started_at' => time() );
    vergeml_guide_save( $s );
    return rest_ensure_response( vergeml_talk_progress() );
}

function vergeml_guide_rest_progress( WP_REST_Request $request ) {
    $report = vergeml_talk_progress();
    $s = vergeml_guide_session();
    if ( 'applying' === $s['state'] && empty( $report['running'] ) ) {
        $s['state'] = 'done';
        vergeml_guide_save( $s );
    }
    $report['state'] = $s['state'];
    return rest_ensure_response( $report );
}
```

Tags in the draft are recorded and shown; creating tag taxonomies is out of scope for this cycle (the spec's "tags reuse the plugin's extra taxonomies" is a later task), so `apply` files folders only.

- [ ] **Step 4: Include the file, push, run the walk**

Add `        include_once( 'core/guide.php' );` after the `core/folder-talk.php` include in `vergelabs-media-library.php`.

Run: commit, push, wait for `box.yml` on the pushed sha, then `gh workflow run box-fix.yml -f job=guide-walk -f apply=0`.
Expected: `session:` at state library; `summary:` with total 641 and groups; `propose:` two proposals with counts; `turn 1:` a message (likely asking size vs colour vs brand with choices); `turn 2:` a message; `assistant turns used: 2 of 25`.

- [ ] **Step 5: Commit**

```bash
cd plugin && git add core/guide.php vergelabs-media-library.php tools/box-guide-walk.php tools/box-guide-walk.sh .github/workflows/box-fix.yml && git commit -m "feat(guide): session, summary, estimates and routes for guided sorting" && git push origin HEAD
```

---

### Task 4: Plugin — the screen (`js/vergeml-guide.js`, `css/vergeml-guide.css`)

**Files:**
- Create: `plugin/js/vergeml-guide.js`
- Create: `plugin/css/vergeml-guide.css` (then `node tools/rtl.mjs` for the RTL sheet)
- Test: the Playwright sweep `tests/ui/sweep.spec.mjs` gains the guide screen (Task 5); this task is verified on the box by screenshot (`tools/box-ui` pattern) and by hand.

**Interfaces:**
- Consumes: the six REST routes from Task 3 via `wp.apiFetch` (`path: '/' + vgmlGuide.ns + '/guide/…'`), `vgmlGuide` localized object `{ ns, cap, foldersUrl, aiUrl }`, `#vgml-guide[data-described]`.
- Produces: one global-free IIFE that mounts `Guide` into `#vgml-guide`.

- [ ] **Step 1: Write the screen**

```js
/* global wp, vgmlGuide */
( function () {
	'use strict';
	var el = wp.element.createElement, useState = wp.element.useState, useEffect = wp.element.useEffect, useRef = wp.element.useRef, Fragment = wp.element.Fragment;
	var __ = wp.i18n.__, sprintf = wp.i18n.sprintf;
	var api = function ( method, route, data ) {
		return wp.apiFetch( { path: '/' + vgmlGuide.ns + '/guide/' + route, method: method, data: data } );
	};
	var STEPS = [ __( 'What you have', 'vergelabs-media-library' ), __( 'A first proposal', 'vergelabs-media-library' ), __( 'Shape it together', 'vergelabs-media-library' ), __( 'Apply', 'vergelabs-media-library' ) ];
	var stepOf = { library: 0, proposal: 1, shaping: 2, review: 2, applying: 3, done: 3 };

	/* ----------------------------------------------------------- pieces */

	function TopBar( p ) {
		return el( 'header', { className: 'vgml-guide-top' },
			el( 'div', { className: 'vgml-guide-steps' }, STEPS.map( function ( s, i ) {
				return el( 'span', { key: i, className: 'vgml-guide-step' + ( i === p.step ? ' is-now' : i < p.step ? ' is-done' : '' ) }, ( i + 1 ) + ' · ' + s );
			} ) ),
			el( 'div', { className: 'vgml-guide-meta' }, p.meta || '' ),
			el( 'a', { className: 'vgml-guide-leave', href: vgmlGuide.foldersUrl }, __( 'Leave', 'vergelabs-media-library' ) )
		);
	}

	function Confirm( p ) {
		return el( 'div', { className: 'vgml-guide-confirm' },
			p.secondary ? el( 'button', { type: 'button', className: 'button', onClick: p.onSecondary, disabled: !! p.busy }, p.secondary ) : null,
			el( 'button', { type: 'button', className: 'button button-primary button-hero', onClick: p.onConfirm, disabled: !! p.busy }, p.busy ? __( 'One moment…', 'vergelabs-media-library' ) : p.label )
		);
	}

	function Tree( p ) {
		// p.folders (flat, with parent names), p.editable, p.changed (set of names), p.onEdit(action, folder, value)
		var byParent = {};
		p.folders.forEach( function ( f ) { ( byParent[ f.parent || '' ] = byParent[ f.parent || '' ] || [] ).push( f ); } );
		var walk = function ( parent, depth ) {
			return ( byParent[ parent ] || [] ).map( function ( f ) {
				return el( 'li', { key: parent + '/' + f.name, className: 'vgml-tree-row' + ( p.changed && p.changed[ f.name ] ? ' is-changed' : '' ) },
					el( 'div', { className: 'vgml-tree-line', style: { paddingInlineStart: ( depth * 18 ) + 'px' } },
						p.editable
							? el( 'input', { className: 'vgml-tree-name', value: f.name, onChange: function ( e ) { p.onEdit( 'rename', f, e.target.value ); } } )
							: el( 'span', { className: 'vgml-tree-name' }, f.name ),
						el( 'span', { className: 'vgml-tree-count' }, typeof f.count === 'number' ? f.count.toLocaleString() : '' ),
						f.matches ? el( 'span', { className: 'vgml-tree-why', title: f.matches }, f.matches ) : null,
						p.editable ? el( 'span', { className: 'vgml-tree-actions' },
							el( 'button', { type: 'button', className: 'button-link', onClick: function () { p.onEdit( 'add', f ); } }, __( 'add inside', 'vergelabs-media-library' ) ),
							el( 'button', { type: 'button', className: 'button-link', onClick: function () { p.onEdit( 'remove', f ); } }, __( 'remove', 'vergelabs-media-library' ) )
						) : null
					),
					el( 'ul', null, walk( f.name, depth + 1 ) )
				);
			} );
		};
		return el( 'ul', { className: 'vgml-tree' }, walk( '', 0 ),
			p.editable ? el( 'li', null, el( 'button', { type: 'button', className: 'button-link vgml-tree-addtop', onClick: function () { p.onEdit( 'add', null ); } }, __( '+ add a top-level folder', 'vergelabs-media-library' ) ) ) : null );
	}

	function Tags( p ) {
		if ( ! p.tags || ! p.tags.length ) { return null; }
		return el( 'div', { className: 'vgml-guide-tags' },
			el( 'span', { className: 'vgml-guide-label' }, __( 'Tags, to filter by', 'vergelabs-media-library' ) ),
			p.tags.map( function ( t ) { return el( 'span', { key: t.name, className: 'vgml-guide-tag' }, t.name + ( t.values && t.values.length ? ' · ' + t.values.slice( 0, 4 ).join( ', ' ) : '' ) ); } )
		);
	}

	/* ---------------------------------------------------------- screens */

	function Library( p ) {
		var s = p.summary;
		if ( ! s ) { return el( 'p', { className: 'vgml-guide-lede' }, __( 'Reading the library…', 'vergelabs-media-library' ) ); }
		var kinds = s.evidence && s.evidence.kinds ? s.evidence.kinds : {};
		return el( Fragment, null,
			el( 'h1', null, __( 'This library, as the AI sees it', 'vergelabs-media-library' ) ),
			el( 'p', { className: 'vgml-guide-lede' }, sprintf( __( '%1$s pictures described. %2$s folders exist today.', 'vergelabs-media-library' ), s.total.toLocaleString(), s.folders.length ) ),
			el( 'div', { className: 'vgml-guide-tiles' }, s.groups.map( function ( g, i ) {
				return el( 'div', { key: i, className: 'vgml-guide-tile' }, el( 'div', { className: 'n' }, g.size.toLocaleString() ), el( 'div', { className: 's' }, ( g.captions || [] ).join( ' · ' ) ) );
			} ) ),
			el( 'div', { className: 'vgml-guide-evidence' },
				el( 'span', null, sprintf( __( '%s%% name a brand', 'vergelabs-media-library' ), Math.round( ( s.evidence.brand || 0 ) * 100 ) ) ),
				el( 'span', null, sprintf( __( '%s%% name a size', 'vergelabs-media-library' ), Math.round( ( s.evidence.size || 0 ) * 100 ) ) ),
				el( 'span', null, sprintf( __( '%s%% show who they are for', 'vergelabs-media-library' ), Math.round( ( s.evidence.audience || 0 ) * 100 ) ) ),
				Object.keys( kinds ).map( function ( k ) { return el( 'span', { key: k }, kinds[ k ].toLocaleString() + ' ' + k ); } )
			),
			el( 'label', { className: 'vgml-guide-goal' }, __( 'Tell it your goal first (optional)', 'vergelabs-media-library' ),
				el( 'textarea', { rows: 2, value: p.goal, placeholder: __( 'e.g. a tech blog — folders should follow topics, not dates', 'vergelabs-media-library' ), onChange: function ( e ) { p.setGoal( e.target.value ); } } ) ),
			el( Confirm, { label: __( 'This is my library, show me a proposal →', 'vergelabs-media-library' ), onConfirm: p.onConfirm, busy: p.busy } )
		);
	}

	function Proposal( p ) {
		if ( ! p.proposals || ! p.proposals.length ) { return el( 'p', { className: 'vgml-guide-lede' }, __( 'Drawing two proposals…', 'vergelabs-media-library' ) ); }
		return el( Fragment, null,
			el( 'h1', null, __( 'Two ways this library could be organised', 'vergelabs-media-library' ) ),
			el( 'div', { className: 'vgml-guide-two' }, p.proposals.map( function ( pr, i ) {
				return el( 'section', { key: i, className: 'vgml-guide-proposal' }, el( 'h2', null, pr.name ), el( Tree, { folders: pr.tree.folders } ), el( Tags, { tags: pr.tree.tags } ),
					el( 'button', { type: 'button', className: 'button button-primary', onClick: function () { p.onPick( pr.tree ); } }, i === 0 ? __( 'Start from the first', 'vergelabs-media-library' ) : __( 'Start from the second', 'vergelabs-media-library' ) ) );
			} ) ),
			el( 'p', null, el( 'button', { type: 'button', className: 'button', onClick: function () { p.onPick( null ); } }, __( 'Neither, let me explain', 'vergelabs-media-library' ) ) )
		);
	}

	function Shaping( p ) {
		var input = useState( '' ), text = input[ 0 ], setText = input[ 1 ];
		var bottom = useRef( null );
		useEffect( function () { if ( bottom.current ) { bottom.current.scrollIntoView( { block: 'end' } ); } }, [ p.turns.length, p.busy ] );
		var send = function ( payload ) { if ( p.busy ) { return; } setText( '' ); p.onTurn( payload ); };
		var placed = p.draft.folders.reduce( function ( n, f ) { return n + ( f.count || 0 ); }, 0 );
		return el( 'div', { className: 'vgml-guide-shaping' },
			el( 'section', { className: 'vgml-guide-treepane' },
				el( 'h2', null, sprintf( __( 'Version %1$s · %2$s folders · about %3$s pictures placed', 'vergelabs-media-library' ), p.draft.version, p.draft.folders.length, placed.toLocaleString() ) ),
				el( Tree, { folders: p.draft.folders, editable: true, changed: p.changed, onEdit: p.onEdit } ),
				el( Tags, { tags: p.draft.tags } ),
				el( Confirm, { label: __( 'This is the structure I want →', 'vergelabs-media-library' ), onConfirm: p.onConfirm, busy: p.busy, secondary: p.canUndo ? __( 'Back one version', 'vergelabs-media-library' ) : null, onSecondary: p.onUndo } )
			),
			el( 'section', { className: 'vgml-guide-chat' },
				el( 'div', { className: 'vgml-guide-turns' },
					p.turns.length === 0 ? el( 'div', { className: 'vgml-msg is-ai' }, __( 'Tell me how you think about this library, or ask me to change something in the tree.', 'vergelabs-media-library' ) ) : null,
					p.turns.map( function ( t, i ) {
						return el( 'div', { key: i, className: 'vgml-msg ' + ( t.role === 'user' ? 'is-me' : 'is-ai' ) }, t.text,
							t.role === 'assistant' && t.choices && t.choices.length && i === p.turns.length - 1
								? el( 'div', { className: 'vgml-chips' }, t.choices.map( function ( c ) { return el( 'button', { key: c, type: 'button', className: 'vgml-chip', onClick: function () { send( { choice: c } ); } }, c ); } ) )
								: null );
					} ),
					p.busy ? el( 'div', { className: 'vgml-msg is-ai is-thinking' }, '…' ) : null,
					p.error ? el( 'div', { className: 'vgml-msg is-error' }, p.error ) : null,
					el( 'div', { ref: bottom } )
				),
				el( 'form', { className: 'vgml-guide-compose', onSubmit: function ( e ) { e.preventDefault(); if ( text.trim() ) { send( { text: text } ); } } },
					el( 'input', { type: 'text', value: text, placeholder: __( 'e.g. monitors by size, colour and brand', 'vergelabs-media-library' ), onChange: function ( e ) { setText( e.target.value ); }, disabled: p.busy || p.capped } ),
					el( 'button', { type: 'submit', className: 'button button-primary', disabled: p.busy || p.capped || ! text.trim() }, __( 'Send', 'vergelabs-media-library' ) )
				),
				el( 'p', { className: 'vgml-guide-cap' }, sprintf( __( '%1$s of %2$s turns used', 'vergelabs-media-library' ), p.used, vgmlGuide.cap ) )
			)
		);
	}

	function Review( p ) {
		var total = p.draft.folders.reduce( function ( n, f ) { return n + ( f.count || 0 ); }, 0 );
		return el( Fragment, null,
			el( 'h1', null, __( 'The structure, before anything moves', 'vergelabs-media-library' ) ),
			el( 'p', { className: 'vgml-guide-lede' }, sprintf( __( '%1$s folders. About %2$s of %3$s pictures have a place; the rest stay unfiled, and the run will say why.', 'vergelabs-media-library' ), p.draft.folders.length, total.toLocaleString(), p.described.toLocaleString() ) ),
			el( Tree, { folders: p.draft.folders } ), el( Tags, { tags: p.draft.tags } ),
			el( Confirm, { label: sprintf( __( 'File %s pictures now', 'vergelabs-media-library' ), p.described.toLocaleString() ), onConfirm: p.onConfirm, busy: p.busy, secondary: __( 'Back to shaping', 'vergelabs-media-library' ), onSecondary: p.onBack } )
		);
	}

	function Apply( p ) {
		var r = p.report || {};
		var pct = r.total ? Math.round( 100 * ( r.seen || 0 ) / r.total ) : 0;
		return el( Fragment, null,
			el( 'h1', null, r.running ? sprintf( __( 'Filing %s pictures', 'vergelabs-media-library' ), ( r.total || 0 ).toLocaleString() ) : __( 'Done', 'vergelabs-media-library' ) ),
			el( 'div', { className: 'vgml-guide-bar' }, el( 'i', { style: { width: pct + '%' } } ) ),
			el( 'p', { className: 'vgml-guide-lede' }, r.message || '' ),
			r.running ? el( 'p', { className: 'vgml-guide-hint' }, __( 'You can leave this page; it carries on.', 'vergelabs-media-library' ) ) : el( 'p', null, el( 'a', { className: 'button button-primary', href: vgmlGuide.foldersUrl }, __( 'See the folders', 'vergelabs-media-library' ) ), ' ', el( 'button', { type: 'button', className: 'button', onClick: p.onStartOver }, __( 'Start a new session', 'vergelabs-media-library' ) ) )
		);
	}

	/* ------------------------------------------------------------ the app */

	function Guide() {
		var st = useState( null ), session = st[ 0 ], setSession = st[ 1 ];
		var bz = useState( false ), busy = bz[ 0 ], setBusy = bz[ 1 ];
		var er = useState( '' ), error = er[ 0 ], setError = er[ 1 ];
		var rp = useState( null ), report = rp[ 0 ], setReport = rp[ 1 ];
		var ch = useState( {} ), changed = ch[ 0 ], setChanged = ch[ 1 ];
		var described = parseInt( document.getElementById( 'vgml-guide' ).getAttribute( 'data-described' ) || '0', 10 );

		useEffect( function () { api( 'GET', 'session' ).then( setSession ); }, [] );
		useEffect( function () {
			if ( ! session || session.state !== 'applying' ) { return; }
			var t = setInterval( function () { api( 'GET', 'progress' ).then( function ( r ) { setReport( r ); if ( r.state === 'done' ) { setSession( Object.assign( {}, session, { state: 'done' } ) ); } } ); }, 3000 );
			return function () { clearInterval( t ); };
		}, [ session && session.state ] );
		useEffect( function () {
			if ( session && session.state === 'library' && ! session.summary ) { api( 'POST', 'summary' ).then( function ( s ) { setSession( Object.assign( {}, session, { summary: s } ) ); } ); }
		}, [ session && session.state ] );

		var save = function ( patch ) {
			var next = Object.assign( {}, session, patch );
			setSession( next );
			return api( 'POST', 'session', { session: next } ).then( function ( saved ) { setSession( saved ); return saved; } );
		};
		var fail = function ( e ) { setError( e && e.message ? e.message : __( 'That did not work. Try again.', 'vergelabs-media-library' ) ); setBusy( false ); };

		if ( ! session ) { return el( 'p', { className: 'vgml-guide-lede' }, __( 'Loading…', 'vergelabs-media-library' ) ); }
		if ( described === 0 ) {
			return el( 'div', { className: 'vgml-guide-empty' }, el( 'h1', null, __( 'Describe the library first', 'vergelabs-media-library' ) ), el( 'p', null, __( 'The guide reasons from the AI descriptions. Run a describe on the AI screen, then come back.', 'vergelabs-media-library' ) ), el( 'a', { className: 'button button-primary', href: vgmlGuide.aiUrl }, __( 'Go to AI', 'vergelabs-media-library' ) ) );
		}

		var step = stepOf[ session.state ] || 0;
		var body;
		if ( session.state === 'library' ) {
			body = el( Library, { summary: session.summary, goal: session.goal, setGoal: function ( g ) { setSession( Object.assign( {}, session, { goal: g } ) ); }, busy: busy,
				onConfirm: function () { setBusy( true ); setError( '' ); save( { goal: session.goal } ).then( function () { return api( 'POST', 'propose', { goal: session.goal } ); } ).then( function ( r ) { setSession( Object.assign( {}, session, { state: 'proposal', proposals: r.proposals } ) ); setBusy( false ); } ).catch( fail ); } } );
		} else if ( session.state === 'proposal' ) {
			body = el( Proposal, { proposals: session.proposals, onPick: function ( tree ) { setBusy( true ); save( { state: 'shaping', draft: tree || { folders: [], tags: [] } } ).then( function () { setBusy( false ); } ).catch( fail ); } } );
		} else if ( session.state === 'shaping' ) {
			body = el( Shaping, { draft: session.draft, turns: session.turns || [], busy: busy, error: error, changed: changed, used: session.assistant_turns || 0, capped: ( session.assistant_turns || 0 ) >= vgmlGuide.cap, canUndo: ( session.history || [] ).length > 0,
				onTurn: function ( payload ) {
					setBusy( true ); setError( '' );
					var optimistic = Object.assign( {}, session, { turns: ( session.turns || [] ).concat( [ { role: 'user', text: payload.text || payload.choice || payload.edit } ] ) } );
					setSession( optimistic );
					api( 'POST', 'turn', Object.assign( { draft: session.draft }, payload ) ).then( function ( a ) {
						var marks = {};
						if ( a.draft ) { a.draft.folders.forEach( function ( f ) { var was = session.draft.folders.filter( function ( o ) { return o.name === f.name && o.parent === f.parent; } )[ 0 ]; if ( ! was || was.matches !== f.matches ) { marks[ f.name ] = true; } } ); }
						setChanged( marks );
						setSession( Object.assign( {}, optimistic, { draft: a.draft || session.draft, assistant_turns: a.assistant_turns, turns: optimistic.turns.concat( [ { role: 'assistant', text: a.message, choices: a.choices } ] ) } ) );
						setBusy( false );
					} ).catch( fail );
				},
				onEdit: function ( action, folder, value ) {
					var folders = session.draft.folders.slice(), words = '';
					if ( action === 'rename' ) { folders = folders.map( function ( f ) { return f === folder ? Object.assign( {}, f, { name: value } ) : ( f.parent === folder.name ? Object.assign( {}, f, { parent: value } ) : f ); } ); words = sprintf( __( 'renamed %1$s to %2$s', 'vergelabs-media-library' ), folder.name, value ); }
					if ( action === 'remove' ) { folders = folders.filter( function ( f ) { return f !== folder && f.parent !== folder.name; } ); words = sprintf( __( 'removed %s', 'vergelabs-media-library' ), folder.name ); }
					if ( action === 'add' ) { var name = window.prompt( __( 'Folder name', 'vergelabs-media-library' ) ); if ( ! name ) { return; } folders.push( { name: name, parent: folder ? folder.name : '', matches: '', classes: [], kinds: [], audience: '', count: 0 } ); words = sprintf( __( 'added %1$s under %2$s', 'vergelabs-media-library' ), name, folder ? folder.name : __( 'the top level', 'vergelabs-media-library' ) ); }
					var draft = Object.assign( {}, session.draft, { folders: folders } );
					setSession( Object.assign( {}, session, { draft: draft } ) );
					if ( action !== 'rename' ) { save( { draft: draft } ).then( function () { if ( words ) { api( 'POST', 'turn', { edit: words, draft: draft } ).then( function ( a ) { setSession( function ( s ) { return Object.assign( {}, s, { turns: ( s.turns || [] ).concat( [ { role: 'user', text: __( 'I ', 'vergelabs-media-library' ) + words }, { role: 'assistant', text: a.message, choices: a.choices } ] ), draft: a.draft || s.draft, assistant_turns: a.assistant_turns } ); } ); } ).catch( function () {} ); } } ); }
				},
				onUndo: function () { var h = session.history || []; var last = h[ h.length - 1 ]; if ( last ) { save( { draft: last.draft } ); } },
				onConfirm: function () { save( { state: 'review' } ); } } );
		} else if ( session.state === 'review' ) {
			body = el( Review, { draft: session.draft, described: described, busy: busy, onBack: function () { save( { state: 'shaping' } ); },
				onConfirm: function () { setBusy( true ); api( 'POST', 'apply' ).then( function ( r ) { setReport( r ); setSession( Object.assign( {}, session, { state: 'applying' } ) ); setBusy( false ); } ).catch( fail ); } } );
		} else {
			body = el( Apply, { report: report, onStartOver: function () { api( 'POST', 'session', { session: { state: 'library', draft: { folders: [], tags: [] } } } ).then( function () { window.location.reload(); } ); } } );
		}
		var meta = session.state === 'shaping' ? sprintf( __( 'version %1$s · %2$s folders', 'vergelabs-media-library' ), session.draft.version, session.draft.folders.length ) : '';
		return el( 'div', { className: 'vgml-guide-app' }, el( TopBar, { step: step, meta: meta } ), el( 'main', { className: 'vgml-guide-main' }, body ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'vgml-guide' );
		if ( root ) { wp.element.render( el( Guide ), root ); }
	} );
} )();
```

Rename in the tree edits the name locally and saves on blur: add `onBlur` to the rename input that calls `save( { draft } )` and posts the edit turn with the words, the same way `remove` and `add` do (so a rename is one turn, not one per keystroke).

- [ ] **Step 2: Write the stylesheet**

```css
/* plugin/css/vergeml-guide.css — the guide is the only thing on its page. */
.vgml-guide-wrap { margin: 0; padding: 0; }
.vgml-guide-app { min-height: calc( 100vh - 32px ); background: #fff; color: #1d2327; display: flex; flex-direction: column; }
.vgml-guide-top { display: flex; align-items: center; gap: 24px; padding: 14px 28px; border-bottom: 1px solid #e3e4e8; position: sticky; top: 32px; background: #fff; z-index: 2; }
.vgml-guide-steps { display: flex; gap: 22px; font-size: 13px; color: #8a8f98; }
.vgml-guide-step.is-now { color: #1d2327; font-weight: 600; }
.vgml-guide-step.is-done { color: #3c434a; }
.vgml-guide-meta { margin-inline-start: auto; font-size: 13px; color: #6b7280; }
.vgml-guide-leave { font-size: 13px; }
.vgml-guide-main { max-width: 1180px; width: 100%; margin: 0 auto; padding: 36px 28px 64px; flex: 1; }
.vgml-guide-main h1 { font-size: 26px; line-height: 1.25; margin: 0 0 8px; }
.vgml-guide-main h2 { font-size: 16px; margin: 0 0 10px; }
.vgml-guide-lede { font-size: 15px; color: #50575e; margin: 0 0 24px; max-width: 64ch; }
.vgml-guide-tiles { display: grid; grid-template-columns: repeat( auto-fill, minmax( 240px, 1fr ) ); gap: 12px; margin-bottom: 20px; }
.vgml-guide-tile { border: 1px solid #e3e4e8; border-radius: 10px; padding: 14px 16px; }
.vgml-guide-tile .n { font-size: 22px; font-weight: 600; }
.vgml-guide-tile .s { font-size: 12.5px; color: #6b7280; margin-top: 4px; }
.vgml-guide-evidence { display: flex; flex-wrap: wrap; gap: 8px 18px; font-size: 13px; color: #50575e; margin-bottom: 24px; }
.vgml-guide-goal { display: block; margin-bottom: 24px; font-weight: 600; }
.vgml-guide-goal textarea { display: block; width: 100%; max-width: 64ch; margin-top: 6px; font-weight: 400; }
.vgml-guide-confirm { display: flex; gap: 12px; align-items: center; margin-top: 28px; }
.vgml-guide-two { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.vgml-guide-proposal { border: 1px solid #e3e4e8; border-radius: 12px; padding: 20px; }
.vgml-tree, .vgml-tree ul { list-style: none; margin: 0; padding: 0; }
.vgml-tree-line { display: flex; align-items: baseline; gap: 10px; padding: 5px 0; border-bottom: 1px solid #f1f1f3; }
.vgml-tree-name { font-weight: 500; }
input.vgml-tree-name { border: 0; border-bottom: 1px dashed transparent; background: transparent; padding: 0; font: inherit; min-width: 8ch; }
input.vgml-tree-name:focus { border-bottom-color: #1d2327; outline: 0; box-shadow: none; }
.vgml-tree-count { color: #8a8f98; font-variant-numeric: tabular-nums; font-size: 12.5px; }
.vgml-tree-why { color: #6b7280; font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 34ch; }
.vgml-tree-actions { margin-inline-start: auto; display: none; gap: 10px; }
.vgml-tree-row:hover > .vgml-tree-line > .vgml-tree-actions { display: flex; }
.vgml-tree-row.is-changed > .vgml-tree-line { background: #fff8e5; }
.vgml-guide-tags { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.vgml-guide-label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #8a8f98; }
.vgml-guide-tag { border: 1px solid #cfd2d8; border-radius: 999px; padding: 3px 10px; font-size: 12.5px; }
.vgml-guide-shaping { display: grid; grid-template-columns: 1.15fr 1fr; gap: 28px; align-items: start; }
.vgml-guide-chat { border: 1px solid #e3e4e8; border-radius: 12px; display: flex; flex-direction: column; height: calc( 100vh - 200px ); position: sticky; top: 96px; }
.vgml-guide-turns { flex: 1; overflow: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.vgml-msg { max-width: 92%; padding: 10px 12px; border-radius: 12px; line-height: 1.45; font-size: 14px; }
.vgml-msg.is-ai { background: #f3f4f6; border-bottom-left-radius: 4px; }
.vgml-msg.is-me { background: #1d2327; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
.vgml-msg.is-error { background: #fbeaea; color: #8a1f1f; }
.vgml-msg.is-thinking { color: #8a8f98; }
.vgml-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.vgml-chip { border: 1px solid #cfd2d8; background: #fff; border-radius: 999px; padding: 4px 10px; font-size: 12.5px; cursor: pointer; }
.vgml-chip:hover { border-color: #1d2327; }
.vgml-guide-compose { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e3e4e8; }
.vgml-guide-compose input { flex: 1; }
.vgml-guide-cap { font-size: 12px; color: #8a8f98; padding: 0 12px 10px; margin: 0; }
.vgml-guide-bar { height: 8px; border-radius: 4px; background: #e6e6ea; overflow: hidden; margin: 18px 0; max-width: 64ch; }
.vgml-guide-bar i { display: block; height: 100%; background: #1d2327; transition: width 400ms ease; }
.vgml-guide-hint { color: #8a8f98; font-size: 13px; }
.vgml-guide-empty { max-width: 56ch; }
@media ( max-width: 960px ) { .vgml-guide-shaping, .vgml-guide-two { grid-template-columns: 1fr; } .vgml-guide-chat { position: static; height: 60vh; } }
@media ( prefers-reduced-motion: reduce ) { .vgml-guide-bar i { transition: none; } }
```

Run `node tools/rtl.mjs` after saving (it writes `css/vergeml-guide-rtl.css` only if the tool lists the file; if it takes a fixed list, add `vergeml-guide.css` to it).

- [ ] **Step 3: Check it parses and deploy**

Run: `node --check js/vergeml-guide.js`, then commit and push; open `admin.php?page=media-guide` on the box as an admin.
Expected: screen 1 with tiles and evidence; confirm → two proposals; pick → shaping with tree and chat; a turn answers; confirm → review → apply with progress → done.

- [ ] **Step 4: Commit**

```bash
cd plugin && git add js/vergeml-guide.js css/vergeml-guide.css css/vergeml-guide-rtl.css tools/rtl.mjs && git commit -m "feat(guide): the four screens in wp.element" && git push origin HEAD
```

---

### Task 5: Entry points, nav, sweep, docs

**Files:**
- Modify: `plugin/core/admin-shell.php` — add the nav page `media-guide` (icon `librarian`, sub "Arrive at a structure, step by step", label "Sort with a guide", cap `manage_categories`) right after `media-librarian`; give the guide page the shell's full-width treatment by adding `vgml-guide` to the page's body class (filter `admin_body_class` in `core/guide.php`) and hiding the shell nav for it in `css/vergeml-guide.css`: `body.vgml-guide .vgml-shell-nav { display: none; }` (check the nav's real class in `admin-shell.php` first).
- Modify: `plugin/core/librarian.php` — the chat card gets a quiet link "Prefer a guided walk-through? Sort with a guide →" pointing at `admin.php?page=media-guide` (find `vergeml_talk_card()` in `core/folder-talk.php:1379` and add the link below the card's lede).
- Modify: `plugin/tests/ui/sweep.spec.mjs` — add `'media-guide'` to the list of screens that "open cleanly".
- Modify: `plugin/docs/architecture.md` — one paragraph on the guide (session option, routes, `/v1/guide`); `plugin/docs/ai-service.md` — the `/v1/guide` contract.

**Interfaces:** none new.

- [ ] **Step 1: Add the nav entry and the entry link, run the sweep**

Run: commit, push; `gh run watch` the `box-ui.yml` run for the pushed sha.
Expected: 29 passed (the sweep list grew by one).

- [ ] **Step 2: Run the scripted walk end to end, with apply**

Run: `gh workflow run box-fix.yml -f job=guide-walk -f apply=1`, then `-f job=tree`.
Expected: the walk prints `apply:` and a final `progress:` with `running: false` and the outcome message; the tree reflects the draft.

- [ ] **Step 3: Commit**

```bash
cd plugin && git add core/admin-shell.php core/guide.php core/folder-talk.php tests/ui/sweep.spec.mjs docs/architecture.md docs/ai-service.md && git commit -m "feat(guide): entry points, nav, sweep and docs" && git push origin HEAD
```

---

### Task 6: Service — the Braintrust eval set for the conversation

**Files:**
- Create: `service/evals/guide/monitors.json`, `service/evals/guide/audience.json`, `service/evals/guide/tech-blog.json`
- Create: `service/evals/guide.eval.ts` (follows the existing Braintrust layout under `service/evals/`; check `ls service/evals` and copy the runner shape of the describe eval)

**Interfaces:**
- Consumes: `guideTurn`, `proposeTrees` from `lib/guide`.
- Produces: an eval run scored on four checks per conversation.

- [ ] **Step 1: Write the three conversations**

```json
// service/evals/guide/monitors.json
{
  "context": { "summary": { "total": 10000, "evidence": { "brand": 0.62, "size": 0.31, "audience": 0.05, "kinds": { "photo": 8200, "screenshot": 1500, "logo": 300 } }, "classes": [ { "class": "monitor", "count": 1120 }, { "class": "laptop", "count": 1480 } ] }, "goal": "tech blog", "current": [] },
  "draft": { "folders": [ { "name": "Products", "parent": "", "matches": "product shots", "classes": [ "monitor", "laptop", "phone" ], "kinds": [ "photo" ], "audience": "" } ], "tags": [] },
  "input": { "text": "I want monitors structured by size, colour and brand." },
  "expect": { "asks": true, "mentions": [ "size", "brand", "tag" ], "noSlash": true, "keepsNames": [ "Products" ] }
}
```

`audience.json`: goal "fashion shop", evidence audience 0.04, input "split everything by men and women" → expect asks true, mentions "4%". `tech-blog.json`: goal "", input "I run a tech blog" → expect draft present, no folder named by a year, mentions "topic".

- [ ] **Step 2: Write the eval and run it once**

```ts
// service/evals/guide.eval.ts
import { Eval } from 'braintrust';
import { readFileSync, readdirSync } from 'node:fs';
import { guideTurn } from '../lib/guide';

const cases = readdirSync('evals/guide').filter((f) => f.endsWith('.json')).map((f) => JSON.parse(readFileSync(`evals/guide/${f}`, 'utf8')));

Eval('vergelabs-media', {
  data: () => cases.map((c) => ({ input: c, expected: c.expect })),
  task: async (c) => guideTurn({ ...c.context, draft: c.draft, turns: [] }, c.input),
  scores: [
    ({ output, expected }) => ({ name: 'asks when unsure', score: expected.asks ? (output && output.choices.length >= 2 ? 1 : 0) : 1 }),
    ({ output, expected }) => ({ name: 'mentions the evidence', score: output ? (expected.mentions ?? []).filter((m: string) => output.message.toLowerCase().includes(m)).length / Math.max(1, (expected.mentions ?? []).length) : 0 }),
    ({ output }) => ({ name: 'no slash paths', score: output?.draft ? (output.draft.folders.every((f) => !f.name.includes('/')) ? 1 : 0) : 1 }),
    ({ output, expected }) => ({ name: 'keeps the names', score: output?.draft ? ((expected.keepsNames ?? []).every((n: string) => output.draft!.folders.some((f) => f.name === n)) ? 1 : 0) : 1 }),
  ],
});
```

Run: `cd service && npx braintrust eval evals/guide.eval.ts` (the Braintrust key is in Vercel prod env; pull it into the shell as the existing describe eval does).
Expected: three cases, all four scores ≥ 0.75; if "asks when unsure" scores 0 on monitors, tighten rule 6 in `guideRules` and re-run.

- [ ] **Step 3: Commit**

```bash
cd service && git add evals/guide evals/guide.eval.ts && git commit -m "test(guide): three fixed conversations scored on asking, evidence, names and paths" && git push origin HEAD
```

---

## Self-review

- **Spec coverage:** four screens (Task 4), session record (Task 3 `vergeml_guide_fresh`), six routes (Task 3), `/v1/guide` two modes (Tasks 1–2), domain rules verbatim (Task 1 `guideRules`), count estimation without records (Task 3 `vergeml_guide_estimate`), large libraries (summary uses groups + 2,000-record sample; apply is the sliced job), failure modes (Task 3 `vergeml_guide_call` messages; Task 4 error row and the cap disabling the composer; described-count gate), undo (existing), testing (Tasks 3, 5, 6). Gap: tags are shown but not created — stated in Task 3 as out of scope for this cycle, matching the spec's "tags reuse the plugin's extra taxonomies" being a later task.
- **Placeholders:** none; every step carries its code.
- **Type consistency:** `TurnAnswer` / `GuideTree` / `GuideContext` names are used identically in Tasks 1, 2 and 6; REST callback names in Task 3 match the walk in Task 3 Step 1; the `vgmlGuide` localized keys (`ns`, `cap`, `foldersUrl`, `aiUrl`) match Task 4.
