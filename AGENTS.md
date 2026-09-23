# Agent Configuration & Architecture

Saturne is a shared Dolibarr ERP framework. All modules (Digirisk, DigiQuali, Saturne, ReedCRM, etc.) live under `htdocs/custom/{module}/` and inherit from saturne.

## Detailed Documentation
- [Project Rules](docs/PROJECT_RULES.md) (Build, Quality, Git, Release)
- [Reference Files](docs/REFERENCE_FILES.md) (Canonical examples of PHP, JS, SCSS)

## 1. Architecture Philosophy
Saturne follows a strict separation of concerns:

- PHP controllers prepare data only
- TPL files render HTML only
- Business logic belongs to `class/`
- Reusable UI belongs to `core/tpl/`
- JavaScript is modular and event-driven
- Styling is component-scoped through `.mod-{module}`

The framework prioritizes:
- maintainability over cleverness
- consistency over flexibility
- backward compatibility with Dolibarr
- reusable patterns shared across all modules

## 2. AI Assistant Instructions
When generating code:

- Always reuse existing Saturne patterns before creating new ones
- Prefer extending generic Saturne components over module-specific implementations
- Keep generated diffs minimal
- Preserve existing comments and spacing
- Never reformat unrelated code
- Follow the surrounding file style first
- When uncertain, mimic the nearest reference file

## 3. Project Architecture
```
htdocs/custom/saturne/ (or reedcrm)
├── admin/          # Admin config pages
├── class/          # PHP CRUD classes (SaturneObject, ActionsSaturne, …)
├── core/
│   ├── ajax/       # AJAX endpoints
│   ├── tpl/        # Reusable TPL fragments (banner_actions, medias, …)
│   └── triggers/   # Dolibarr event triggers
├── css/scss/       # SCSS source → compiled to css/{module}.min.css
├── js/modules/     # JS feature modules → compiled to js/{module}.min.js
├── lib/            # snake_case PHP utility functions
├── view/           # Generic views (saturne_list.php, saturne_document.php, …)
└── gulpfile.js     # Build config (child modules reference gulpfile-shared.js)
```

**Child module entry point** (`{module}.main.inc.php`):
```php
$moduleName = 'ReedCRM';
$moduleNameLowerCase = strtolower($moduleName);
require_once __DIR__ . '/../saturne/saturne.main.inc.php';
```

## 4. Anti-Patterns
Never:
- Put SQL in views
- Put HTML in classes
- Put business logic in TPL
- Use inline CSS or JS
- Call Dolibarr globals directly inside JS
- Duplicate generic components already existing in `saturne/`
- Create module-specific patterns when a shared Saturne pattern exists

**PHP Rendering**
```php
// BAD
echo '<script>alert()</script>';

// GOOD
saturne_header();
```

**JavaScript Event Binding**
```javascript
// BAD
$('.btn').click(function(){});

// GOOD
$(document).on('click', '.btn', handler);
```

## 5. PHP Conventions
**Style** — follow [PSR-12](https://www.php-fig.org/psr/psr-12/) for all PHP code. Enforced via PHPCS (`phpcs --standard=PSR12`).

**Comments** — place comments on the line **above** the code they document. A good comment explains **why**, not **what**.

**Asset loading** — never use `<link>` or `<script>` manually:
```php
saturne_header(); // auto-loads saturne.min.css + saturne.min.js
                  // also loads {module}.min.css and {module}.min.js
```

**Variables before templates** — all logic runs before `require_once` of TPL:
```php
$title  = $langs->trans('MyPage');
saturne_header(0, '', $title, $help_url);
require_once __DIR__ . '/../../saturne/core/tpl/banner_actions.tpl.php';
```

**Security rules** — always:
```php
$id     = GETPOSTINT('id');           // never $_GET / $_POST
$label  = GETPOST('label', 'alpha');
$name   = dol_sanitize_filename($name);
$html   = dol_escape_htmltag($value);
$user->hasRight('mymodule', 'write'); // check before any action
$db->escape($value);                  // escape SQL values
```

**Hooks** — action class returns `0` (continue) or `1` (replace):
```php
class ActionsMyModule {
    public function printMainArea(array $parameters): int {
        if (strpos($parameters['context'], 'mymodulecontext') !== false) {
            // custom output
        }
        return 0;
    }
}
```

## 6. JavaScript Conventions
**Namespace pattern** — literal object, no IIFE:
```javascript
window.saturne.modal = {};

window.saturne.modal.init = function() {
    window.saturne.modal.event();
};

window.saturne.modal.event = function() {
    $(document).on('click', '.modal-open', window.saturne.modal.openModal);
};

window.saturne.modal.openModal = function(event) { /* … */ };
```

**Rules**:
- Always implement `init()`, `event()`, and handler methods
- `saturne.js` calls `init()` on every `window.saturne.*` automatically via `$(document).ready` — **never call `init()` manually**
- Use jQuery — no Vanilla JS unless jQuery is unavailable
- **No inline JS in TPL files**

## 7. SCSS Conventions
**Rules**:
- Target Dolibarr overrides via the `mod-{module}` class injected by `saturne_header` on `<body>`:
  ```scss
  .mod-mymodule .fichecenter { /* override, not a global selector */ }
  ```
- Partials named `_{name}.scss`, aggregated through `_{category}.scss`

## 8. Performance Rules
- Avoid N+1 fetch loops
- Prefer bulk fetch methods
- Never load unused JS modules
- AJAX endpoints must return minimal payloads
- Large lists must support pagination
- Avoid synchronous AJAX

## 9. Compatibility Rules
- Preserve existing hooks and public method signatures
- Avoid breaking database schema changes
- New constants must have migration safety
- UI changes must remain compatible with existing themes
