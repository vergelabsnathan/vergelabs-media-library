#!/usr/bin/env bash
#
#  The fix step for a red verdict: a non-interactive Claude Code run implements
#  the plan from the issue on its own branch and opens a PR. A human merges.
#
#      bash tools/watch/fix.sh <issue-number>
#
#  Needs: gh (authenticated), claude on PATH, and CLAUDE_CODE_OAUTH_TOKEN (from
#  `claude setup-token` -- an interactive login only Nathan can do; the Action
#  reads it from a secret). Never touches main, never force-pushes. When the
#  gates are not green afterwards the PR is opened as a draft that says so.
set -euo pipefail

ISSUE=${1:?issue number}
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT"

TITLE=$(gh issue view "$ISSUE" --json title -q .title)
BODY=$(gh issue view "$ISSUE" --json body -q .body)
SLUG=$(echo "$TITLE" | sed -E 's/^(Broke|Check): //; s/ — .*$//' | tr -cs 'A-Za-z0-9.' '-' | tr 'A-Z' 'a-z' | sed 's/-$//')
BRANCH="watch/${SLUG}-${ISSUE}"

git fetch -q origin main
git switch -q -c "$BRANCH" origin/main

PROMPT="You are fixing a proven compatibility break in this WordPress plugin (PHP 7.4 floor, no build step, ES5 JS).
Issue #$ISSUE: $TITLE

$BODY

Rules: follow the plan's tasks in order; change only the files the plan names; keep the repo's idiom (comment density, naming); PHP 7.4 syntax only; do not touch readme.txt, the version, or anything under tests/ unless the plan says so. When done, list what you changed and why in one paragraph."

claude -p "$PROMPT" \
    --permission-mode acceptEdits \
    --allowedTools "Edit,Write,Read,Grep,Glob,Bash(php -l *),Bash(node tools/watch/contract-check.mjs *)" \
    --max-turns 40 \
    --output-format text > /tmp/fix-$ISSUE.log 2>&1 || true

echo "--- claude run (tail)"; tail -20 /tmp/fix-$ISSUE.log

if git diff --quiet; then
    gh issue comment "$ISSUE" --body "The fix step ran and made no change. Log tail:
\`\`\`
$(tail -30 /tmp/fix-$ISSUE.log)
\`\`\`"
    echo "no change; nothing to open"
    exit 0
fi

# Gate 1: parse on the box's PHP is not the floor, but a file that does not parse at all is a hard stop.
LINT=$(find . -name '*.php' -not -path './node_modules/*' -exec php -l {} \; 2>&1 | grep -v 'No syntax errors' || true)
# Gate 2: PHP 8 syntax on the 7.4 floor.
FLOOR=$(grep -rnE 'match\s*\(|\?\->|readonly |enum [A-Z]' --include=*.php . | grep -v '/tests/' | grep -v '// ' || true)
DRAFT=""
NOTE="Gates: php -l $( [ -z "$LINT" ] && echo clean || echo FAILED ), 7.4 floor $( [ -z "$FLOOR" ] && echo clean || echo FAILED )."
if [ -n "$LINT$FLOOR" ]; then DRAFT="--draft"; NOTE="$NOTE Opened as a draft: a gate is red.
\`\`\`
$LINT
$FLOOR
\`\`\`"; fi

git add -A
git -c user.name=watch-fix -c user.email=noreply@vergelabs.nl commit -q -m "fix: $TITLE

Drafted by the watch's fix step for #$ISSUE."
git push -q -u origin "$BRANCH"

gh pr create $DRAFT --title "fix: $TITLE" --base main --head "$BRANCH" --body "Closes #$ISSUE

$NOTE

The full validation gate (\`/validate\`) has **not** run on this branch; run it before merging.

Claude run log tail:
\`\`\`
$(tail -25 /tmp/fix-$ISSUE.log)
\`\`\`"
