# Reference Files

These files are the most complete and representative examples in the codebase. Use them as templates when creating new files of the same type.

## PHP — Action / Hook Class
**`class/actions_saturne.class.php`** (513 lines)

The canonical example of a Dolibarr hook class. Study this file to understand:

- How to structure the hook class: `printMainArea`, `addHtmlHeader`, `llxHeader`, `printCommonFooter`, `doActions`, `emailElementlist`, `getElementProperties`
- Return convention: `return 0` to let Dolibarr continue processing, `return 1` to replace it
- How to accumulate HTML output into `$this->resprints` before returning
- How to return structured data via `$this->results` (used for array-type hook responses)
- How to guard each hook method with a context check (`strpos($parameters['context'], '...')`)

```php
// Skeleton pattern from actions_saturne.class.php
public function printMainArea(array $parameters): int {
    if (strpos($parameters['context'], 'mymodulecontext') !== false) {
        $this->resprints = '<div>…HTML output…</div>';
        return 1; // replace default rendering
    }
    return 0; // continue
}
```

## PHP — View Page
**`view/saturne_list.php`** (247 lines)

The reference implementation of a generic list view. Follow this exact sequence:

1. `saturne_check_access()` — security gate (permissions, module enabled)
2. `saturne_get_objects_metadata()` — loads object definitions
3. `GETPOSTINT()` / `GETPOST()` — read request parameters (never `$_GET`/`$_POST`)
4. `$user->hasRight()` — check write permission before any action
5. `saturne_header(0, '', $title, $help_url)` — renders `<html>` + loads CSS/JS
6. Sequential `require_once` of TPL fragments (list_build, list_header, list_search, list_loop, list_footer)

This file also shows how `$hookmanager->executeHooks()` is called in a view context, and how the body class (`mod-{module}`) is injected by `saturne_header` for SCSS scoping.

## TPL — Admin Config Fragment
**`core/tpl/admin/object/object_const_view.tpl.php`**

Best example of an admin-facing TPL. Demonstrates:

- The mandatory comment block at the top listing all expected global variables (`$object`, `$user`, `$langs`, …)
- `$hookmanager->executeHooks('saturneAdminObjectConst', …)` — the standard hook call inside a TPL
- `ajax_constantonoff()` helper for toggle switches on admin config pages
- How to use `$conf->global->MODULE_CONST` to read/write module config values

## TPL — Action Fragment
**`core/tpl/actions/banner_actions.tpl.php`**

Minimal but canonical TPL. Use it to understand:

- The expected comment-header format declaring every global variable the TPL relies on
- How variables must be fully prepared in the calling `.php` file before the TPL is included (zero business logic inside a TPL)
- The file naming convention: `{category}/{subcategory}/snake_case_name.tpl.php`

## JavaScript — Rich Module (AJAX + DOM)
**`js/modules/object.js`** (155 lines)

The most complete JS module in the codebase. Use it as the gold standard for:

- The three-method skeleton: `init()` → `event()` → named handler functions
- Delegated event binding: `$(document).on('click', '.selector', handler)` (never direct `.click()`)
- `getFields()` — how to collect form field values into a data object
- `$.ajax({url, data, success})` pattern with a named success callback (`reloadListSuccess`)
- `ObjectFromModal()` — how to open a modal and react to its result
- jQuery-only rule: no `document.querySelector`, no `addEventListener`

```javascript
window.saturne.object = {};

window.saturne.object.init = function() {
    window.saturne.object.event();
};

window.saturne.object.event = function() {
    $(document).on('click', '.object-save', window.saturne.object.save);
};

window.saturne.object.save = function(event) {
    var fields = window.saturne.object.getFields();
    // $.ajax(…)
};
```

`saturne.js` auto-calls `window.saturne.object.init()` on `$(document).ready` — **never add an `init()` call at the bottom of the file**.

## JavaScript — Event-Driven Module (data-* attributes)
**`js/modules/modal.js`** (168 lines)

Complements `object.js` by showing the event-only pattern (no AJAX). Study it for:

- Reading configuration from `data-*` attributes on a `.modal-options` element: `$('.modal-options').data('url')`, `$('.modal-options').data('type')`
- `openModal` / `closeModal` — how to toggle CSS classes and manage the `modal-active` state
- `refreshModal` — reloading modal content dynamically
- `loadLazyImages()` — deferred image loading triggered on modal open

## SCSS — Component Partial (state + responsive)
**`css/scss/modules/modal/_modal.scss`** (146 lines)

Reference for a full component partial:

- BEM-like nesting with `&` for element and modifier: `.wpeo-modal { &.modal-active { … } .modal-container { … } }`
- State modifier class pattern: `.modal-active` added/removed by JS to trigger CSS transitions
- Media query with the `$media__small` variable from `_sizes.scss`
- Color variables from `_colors.scss` (`$color__primary`, `$color__white`, etc.)
- Imports a sub-partial at the bottom: `@import "modal-flex"` — one partial per layout concern

## SCSS — Utility Partial (modifier class system)
**`css/scss/modules/button/_button.scss`** (206 lines)

Shows the modifier-class architecture used across all Saturne components:

- Base class `.wpeo-button` with default styles
- Modifier classes: `.button-blue`, `.button-grey`, `.button-red`, `.button-pill`, `.button-square`, etc.
- Color imports via `@import "colors"` at the top
- Sub-partial import at the bottom: `@import "button-add"` for the FAB/add-button variant
- How Dolibarr-specific overrides are scoped inside `.mod-{module}` to avoid polluting global styles
