---
name: deploy-and-watch
description: Push the current branch to main to deploy to production, then watch the CI and deploy workflows to completion and smoke-test the live site. Only when the user explicitly asks to deploy or push.
disable-model-invocation: true
---

# deploy-and-watch

Pushing to `main` **is** deploying to production: `tests` + `linter` run, then the `deploy` workflow (triggered by `tests` succeeding) SSHes into the VPS and runs `git reset --hard`, composer, npm ci/build, migrate, optimize, restart php8.4-fpm. Use this skill only when the user asked to push or deploy.

## 1. Preflight

```bash
git status --short          # working tree should be clean, or you know exactly what's uncommitted
git branch --show-current   # expect main
git log origin/main..HEAD --oneline
```

Show the user the commits that are about to go live. Run the `verify` skill first if it hasn't been run on this change. Don't push a failing build. Check for anything that shouldn't ship (`.env`, keys, debug leftovers), and flag risky migrations (see AGENTS.md "Performance rules").

## 2. Record state, then push

Record the previous deploy run id **before** pushing — "latest run" right after a push can still be the previous, already-finished run, which is how a watcher once reported success for a deploy that later failed.

```bash
PREV_ID=$(gh run list --workflow deploy.yml --limit 1 --json databaseId --jq '.[0].databaseId')
SHA=$(git rev-parse HEAD)
git push origin main
```

## 3. Wait for the *new* deploy run

Long foreground `sleep`s are blocked in Claude Code; run this loop in the background (or with Monitor), printing only the final line. Deploys take about 1–2 minutes after tests (~1 min).

```bash
for i in $(seq 1 60); do
  id=$(gh run list --workflow deploy.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  st=$(gh run list --workflow deploy.yml --limit 1 --json status --jq '.[0].status')
  cn=$(gh run list --workflow deploy.yml --limit 1 --json conclusion --jq '.[0].conclusion')
  if [ "$id" != "$PREV_ID" ] && [ "$st" = "completed" ]; then echo "deploy $id: $cn"; break; fi
  t=$(gh run list --workflow tests.yml --limit 1 --json headSha,conclusion)
  if [ "$(echo "$t" | jq -r '.[0].headSha')" = "$SHA" ] && [ "$(echo "$t" | jq -r '.[0].conclusion')" = "failure" ]; then echo "tests failed - deploy will not run"; break; fi
  sleep 15
done
```

Ending without either line means the 15-minute cap hit — inspect `gh run list --limit 6` by hand.

## 4. Smoke test the live site

```bash
curl -s -o /dev/null -w "HTTP %{http_code} in %{time_total}s\n" https://tes.miftahulhuda.site/enrollments
curl -s "https://tes.miftahulhuda.site/enrollments/data?page=1&page_size=1" -H "Accept: application/json" | jq '.meta.total'
```

Expect 200 and a total of at least 5,000,000. Read-only checks only. Any create/update/delete smoke test on production needs explicit user approval, dummy data that's obviously fake, and cleanup afterwards. Browser caches can show stale assets — hard-reload before judging UI.

## 5. If a run fails

```bash
gh run view <id> --log-failed | tail -60
```

| Symptom | Cause / fix |
|---|---|
| `Error: missing server host` | GitHub secrets not set (`VPS_HOST`, `VPS_USERNAME`, `VPS_SSH_KEY`, optional `VPS_PORT`) |
| `ssh: unable to authenticate` and `Invalid user raul\n` in the VPS `/var/log/auth.log` | A secret has a trailing newline — retype `VPS_USERNAME` (and check `VPS_HOST`) |
| `Your local changes … would be overwritten` | Shouldn't recur; the script uses `git reset --hard`. Check `deploy.yml` wasn't reverted |
| Failure in `composer install`, `npm ci/build`, or `migrate` | Read the log; the site may be half-deployed, so run the smoke test and fix forward with a new commit |

Roll back with `git revert <sha>` and a normal push. Never force-push `main`. `gh run rerun <id>` only helps for infra or secrets problems, not code fixes. Never print secret values; VPS details are in the README "Deployment" section.

## 6. Report

Deploy run id and conclusion, the smoke-test numbers, and anything not verified.
