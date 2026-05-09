# Project Rules

## 1. Build Workflow (Gulp)
### Lancer gulp en local
**Windows CMD :**
```cmd
set MODULE_NAME=saturne && node node_modules/gulp/bin/gulp.js --gulpfile gulpfile-shared.js
```

**Linux / macOS / PowerShell :**
```bash
npm start   # équivalent, défini dans package.json via cross-env
```

**Tâches disponibles :**

| Commande | Description |
|----------|-------------|
| `default` | Compile SCSS + JS puis lance le watch (dev) |
| `build` | Compilation prod one-shot, minifiée, sans sourcemaps |
| `scss_core` | SCSS dev uniquement (sourcemaps + minification) |
| `js_backend` | JS concat + uglify uniquement |

### Ce qui est compilé
| Fichier | Local | CI/prod |
|---------|-------|---------|
| `css/saturne.min.css` | ✅ gulp (avec sourcemaps) | ✅ gulp `build` (sans sourcemaps) |
| `js/saturne.min.js` | ✅ gulp | ✅ gulp `build` |
| `css/saturne.min.css.map` | ✅ gulp (debug local) | ❌ jamais généré ni commité |

### Git et assets compilés
- La CI (`build-assets.yml`) compile et commite `.min.css` / `.min.js` automatiquement sur push vers `main` ou `develop` — **ne jamais les commiter manuellement**
- Le sourcemap `.map` est dans `.gitignore` — uniquement utile en local pour le debug DevTools
- En local, Git ignore les modifications sur les `.min` grâce à `assume-unchanged` :
  ```bash
  git update-index --assume-unchanged css/saturne.min.css
  git update-index --assume-unchanged js/saturne.min.js
  ```
- Use `npm ci` in CI (reproducible installs from lock file), `npm install` locally

## 2. Quality Tooling
| Tool | Purpose | Config file | Enforced in CI |
|------|---------|-------------|----------------|
| **PHPCS** | PHP style enforcement (PSR-12) | `.phpcs.xml` | ✓ (blocks build) |
| **phpcbf** | Auto-fix PSR-12 violations | `.phpcs.xml` | — (local only) |
| **JSHint** | JS validation | `.jshintrc` | ✓ (blocks build) |
| **PHPStan** | Static analysis — max level | `phpstan.neon` | ✓ (quality job) |
| **Phan** | Deep static analysis | `.phan/config.php` | ✓ (quality job) |
| **PHPUnit** | Unit tests | `tests/phpunit/phpunittest.xml` | ✓ (quality job) |
| **EditorConfig** | Indentation, charset, line endings consistent across all editors | `.editorconfig` | — (editor-side) |

PHPCS and JSHint run **before** compilation in CI (`build-assets-reusable.yml` — `lint` job must pass before `build` job starts).
PHPStan, Phan, and PHPUnit run in a separate `quality.yml` workflow, triggered on push/PR to `main` and `develop`.

**Indentation** — this project uses **spaces** (PSR-12, 4 spaces), unlike Dolibarr core which uses tabs. Never mix the two.

Run locally:
```bash
# PHPCS — check
~/.composer/vendor/bin/phpcs --standard=.phpcs.xml --extensions=php --ignore=vendor,node_modules,css,js .

# phpcbf — auto-fix (run before committing)
~/.composer/vendor/bin/phpcbf --standard=.phpcs.xml .

# JSHint
jshint js/modules/*.js

# PHPStan (0 errors when baseline is current)
vendor/bin/phpstan analyse --memory-limit=512M

# PHPUnit
vendor/bin/phpunit --configuration tests/phpunit/phpunittest.xml --testdox

# Phan — requires php-ast; runs in CI (PHP 8.1) only
# vendor/bin/phan --config-file=.phan/config.php
```

**PHPStan baseline** — `phpstan.baseline.neon` suppresses pre-existing errors.
When you fix a baselined error, regenerate it:
```bash
vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline phpstan.baseline.neon
```

**PHPUnit bootstrap** — `tests/phpunit/bootstrap.php` is stub-only (no Dolibarr DB).
Tests that load `saturne_functions.lib.php` require `DOL_DOCUMENT_ROOT` to point to a Dolibarr `htdocs/` directory (available locally and in CI via sparse checkout).

EditorConfig is picked up automatically by most editors (VSCode, PhpStorm, etc.) — install the plugin if prompted.

## 3. Git Conventions
**Branch**: `{type}/{issue-number}-{short-description}`
→ `fix/503-mail-eventpro`, `feat/478-menu-reorder`

**Never commit directly to `main` or `develop`.** Dev branch: `develop`. PR required with ≥1 reviewer.

**One issue = one branch = one PR.** Never mix multiple issues in a single branch or PR.

**Commit format**: `#{issue} [{Scope}] {type}: {short description}`

| Type | Usage |
|------|-------|
| `feat` / `add` | New feature |
| `fix` | Bug fix |
| `rework` | Refactor/rework |
| `chore` / `ci` | Build, CI, config |
| `docs` / `style` | Docs, formatting |

**Scope**: business element if broad (`Projet`, `EventPro`), technical category if focused (`JS`, `SCSS`, `CI`).

```
#503 [EventPro] fix: returnurl construction before tpl include
#478 [Menu] rework: reorder left menu entries
#1305 [JS] add: counter for all maxlength fields
```

**Issue labels**:
- **Story points** — add a Fibonacci label to every issue: `0`, `1`, `2`, `3`, `5`, `8`, `13`, `21`
- **PWA** — add the `PWA` label to issues related to the Progressive Web App feature

## 4. Release Process
See `docs/MEMO_RELEASE.md` for the full release workflow.

Short prompt to generate release notes:
```bash
claude "Generate release notes for version X.X.X based on git log since tag X.X.X. Use RELEASE_NOTES_TEMPLATE.md as format reference. Write in French, group by functional category, add screenshot placeholders for visual features. Save to RELEASE_NOTES.md"
```

## 5. Pitfalls
- **Zero files outside `htdocs/custom/{module}/`** — never touch Dolibarr core
- **Don't copy `gulpfile.js`** into each module — use `gulpfile-shared.js`
- **Test install/uninstall** on a clean Dolibarr instance before opening a PR
- **`.min` files are auto-generated** — conflicts on them = recompile, don't hand-merge
- `$moduleNameLowerCase` must be set before `saturne.main.inc.php` is required
