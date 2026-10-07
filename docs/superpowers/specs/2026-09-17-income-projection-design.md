# Guaranteed Income Projection & Preset Plans — Design

## Problem

Configure Types (`templates/bank-configure.php`, `src/bankTypes.php`) lets a user define
types (Expenses, Savings, Investments, Pension, custom types) and an `income_percent` for
each, plus activate/deactivate/delete them. There is no way today to see what those
percentages actually mean in real money over a year, and new accounts are silently seeded
with a fixed default split (`defaultBankTypeDefinitions()`) with no chance to choose or
understand it first.

This feature adds:

1. A **guaranteed monthly income** figure per account (manually entered, independent of
   real tracked bank income) that drives a **live yearly projection**.
2. A small set of **preset plans** (different `income_percent` splits) a new user can pick
   from during onboarding as a starting point — fully editable afterwards via the existing
   Configure page, exactly like a manually built setup.

Goal: as simple, fast, and understandable as possible — no growth/interest modeling, no
plan management UI, no new persistent "which plan am I on" concept after the initial seed.

## Scope

In scope:
- New `accounts.guaranteed_monthly_income` column.
- Hardcoded preset plan definitions (`src/plans.php`), applied once at onboarding.
- Mandatory one-time onboarding step for new/unfinished accounts.
- Live yearly projection panel embedded in the Configure page.

Out of scope (explicitly, per discussion):
- Compounding / growth rates / interest assumptions.
- Editable or admin-managed plans (DB-backed plan CRUD).
- Ongoing "current plan" tracking after the initial seed — once seeded, it's just Configure
  as it already exists.
- Deriving guaranteed income automatically from actual transaction history.

## Data model

### New column

`accounts.guaranteed_monthly_income FLOAT NULL`

`NULL` means the account has not completed onboarding yet. This is the single flag used
everywhere — no separate `onboarding_completed` boolean.

Add via a new migration file following the existing `db/migrations/YYYY_MM_DD_name.sql`
convention (see `db/migrations/2026_09_17_goal_miss_carryover.sql` for the pattern), e.g.
`db/migrations/2026_09_17_accounts_guaranteed_income.sql`.

### Preset plans (hardcoded)

New `src/plans.php`, mirroring the shape of `defaultBankTypeDefinitions()` in
`src/bankTypes.php`:

```php
function presetPlanDefinitions(): array {
    return [
        'scratch' => [
            'name' => 'Start from scratch',
            'description' => 'Use the standard default split and adjust everything yourself.',
            'types' => defaultBankTypeDefinitions(), // today's existing defaults (no pension %)
        ],
        'balanced' => [
            'name' => 'Balanced',
            'description' => 'A steady, all-around split.',
            'types' => [
                ['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.60, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
                ['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.15, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
                ['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.20, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
                ['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
            ],
        ],
        'aggressive_saver' => [
            'name' => 'Aggressive Saver',
            'description' => 'Prioritizes growing investments faster.',
            'types' => [
                ['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.50, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
                ['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.10, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
                ['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.35, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
                ['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
            ],
        ],
        'steady_starter' => [
            'name' => 'Steady Starter',
            'description' => 'Lower risk, bigger cash buffer to start.',
            'types' => [
                ['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.70, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
                ['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.20, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
                ['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
                ['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
            ],
        ],
    ];
}
```

Every non-scratch plan includes the same four adjustable types (Expenses, Savings,
Investments, Pension) so each plan is a complete, self-consistent split summing to 100%.
The three fixed system types (`income`, `kogumiskonto`, `wallet_adjustment`) are seeded for
every account regardless of which plan is chosen, unchanged from today's
`seedDefaultBankTypes()` behavior — plans only vary the four adjustable types above.

Each plan's `types` entry has the same shape as `defaultBankTypeDefinitions()` rows
(slug, label, color_hex, income_percent, balance_mode, is_system, show_in_pills,
sort_order), so applying a plan reuses the existing `seedDefaultBankTypes()`-style insert
logic, parameterized by plan instead of hardcoded to the one default set.

Plan content (percentages, names, descriptions) is free to tune later — it's just data in
this function, not a design constraint.

## Onboarding flow

### Trigger

`signup()` (`src/auth.php`) stops calling `seedDefaultBankTypes()` directly — seeding now
only happens once a plan is chosen via onboarding.

A single centralized guard is added in `public/index.php`, right after the existing
`$_SESSION['user_id']` checks near the top of request handling: if the logged-in user's
`accounts.guaranteed_monthly_income` is `NULL` and the requested URI is not in an allowlist
(`/onboarding`, `/login`, `/signup`, `/logout`, `/about`, `/`), redirect to `/onboarding`.

This single check covers:
- New signup → login → landing (redirected before reaching the app).
- Direct navigation to any URL while onboarding is incomplete.
- A user who logs out mid-onboarding and logs back in later (state lives in the DB, not
  the session, so nothing is lost).

### Routes

- `GET /onboarding`: renders `templates/onboarding.php` with `presetPlanDefinitions()`.
- `POST /onboarding`: validates `guaranteed_monthly_income` (required, numeric, > 0) and
  `plan` (must be a known key from `presetPlanDefinitions()`, including `'scratch'`).
  On success: saves the income to `accounts`, seeds the chosen plan's types (reusing the
  bank-type insert logic from `bankTypes.php`), redirects to `/bank/configure`. On
  validation failure: re-renders the page with an inline error, preserving the entered
  income value.

### Page UX (`templates/onboarding.php` + `public/assets/js/onboarding.js`)

Single page, no multi-step wizard:

1. **Guaranteed monthly income** input pinned at the top of the page.
2. Below it, a grid of **plan cards**, one per entry in `presetPlanDefinitions()` (including
   "Start from scratch"). Each card always shows:
   - Plan name and one-line description.
   - Its type breakdown as small colored chips (type's `color_hex`) with percentages —
     visible even with no income entered yet, so splits are comparable immediately.
3. As soon as the income input has a valid positive value, every card additionally renders
   a row of colored **yearly outlook mini-cards** — one per type in that plan with a
   non-null `income_percent`, **excluding the `expenses` slug** — showing
   `label` + `income × percent × 12` formatted as currency. Recalculated live in plain JS
   on every keystroke of the income input (pure client-side multiplication, no server
   round-trip). If a plan's percentages sum to less than 100%, an extra neutral
   "Unallocated" mini-card shows the leftover's yearly value.
4. Clicking a card selects it (single-select, radio-button behavior — reuse a simple
   `role="radio"`/checked-card pattern). Selecting a card highlights it visually.
5. A "Continue" submit button is disabled until both: income is a valid positive number,
   and exactly one plan card is selected. This mirrors client-side validation that the
   server re-checks on submit.

## Configure page projection panel

`templates/bank-configure.php` gains a new panel, visually consistent with the existing
`.configure-summary` bar (reuse that card/border styling), placed above the type list:

- **Guaranteed monthly income**: an inline, editable numeric input, pre-filled from
  `accounts.guaranteed_monthly_income`. Saves on blur/change via a new
  `POST /bank/configure/income` JSON endpoint (same fire-and-forget AJAX pattern as
  `postConfigure()` already used for type rows in `bank-configure.js`). Invalid input
  (empty, negative, non-numeric) blocks the save and shows an inline error using the
  existing `showRowError`-style pattern.
- Below it, a grid of colored mini-cards — one per **active** type that has a non-null
  `income_percent` set, **excluding only the type with slug `expenses`** (other
  `wallet_out` / expense-like custom types ARE shown, per explicit decision). Each card:
  - Uses the type's own `color_hex` via the existing `hexToRgba()` helper for its
    background tint, matching the visual language already used for type colors elsewhere.
  - Shows the type's label and `guaranteed_monthly_income × income_percent × 12`
    (yearly total only — no monthly figure shown, to keep the panel minimal).
- A neutral-colored "Unallocated" card (reusing the same grey `#888888` styling already
  used for `wallet_adjustment`/`kogumiskonto`) appears whenever the active `income_percent`
  sum across shown types is less than 100%, showing the leftover fraction's yearly value.
- If no active type has an `income_percent` set at all, the grid is replaced with a single
  hint line (e.g. "Set a percentage on a type below to see it here.").

### Recalculation behavior

All recalculation is instant, client-side JS in `public/assets/js/bank-configure.js`,
extending the existing `updateAllocationSummary()` machinery rather than replacing it:

- A new `updateProjection()` function reads the same `.js-configure-row` DOM state
  (`income_percent` input value, active-toggle state, `color_hex`, `label`, `slug`) plus
  the new income input's value, and re-renders the mini-card grid.
- Hooked into: the income input's `input` event, each row's percent-input `input` event,
  and each row's active-toggle `change` event — the same events `updateAllocationSummary()`
  already listens to, so both update together.
- Add/delete already triggers a full `window.location.reload()` today in
  `bank-configure.js`; after reload, the panel is simply re-rendered fresh from PHP with
  current DB state, so no special-case JS is needed for those actions.
- A type with `income_percent === null` (e.g. Pension left unconfigured) is silently
  omitted from the grid — this is not an error state.

## Error handling summary

| Case | Behavior |
|---|---|
| Onboarding: income empty/invalid/≤0 | Continue disabled client-side; server re-validates and re-renders with inline error if bypassed |
| Onboarding: no plan selected | Continue stays disabled |
| Configure: income input invalid on save | Inline error shown, save blocked, previous stored value unaffected |
| Configure: no active type has a percent | Hint message shown instead of empty grid |
| Configure: percentages sum < 100% | "Unallocated" card shown with leftover's yearly value |
| Configure: percentages sum > 100% | Already blocked today by `validateIncomePercentSum()` — unaffected by this feature |

## Example preset plan content

| Plan | Expenses | Savings | Investments | Pension |
|---|---|---|---|---|
| Balanced | 60% | 15% | 20% | 5% |
| Aggressive Saver | 50% | 10% | 35% | 5% |
| Steady Starter | 70% | 20% | 5% | 5% |
| Start from scratch | 60% | 15% | 25% | *(no % — today's existing defaults)* |

These are starting values only; easy to retune later since they live in one hardcoded
function.

## Verification approach

No automated test suite exists in this project. Manual verification checklist:

1. Sign up a new account → confirm redirect to `/onboarding` instead of straight into the
   app.
2. On `/onboarding`, type an income value → confirm all plan cards' yearly mini-cards
   update live as you type; confirm "Continue" stays disabled until a plan is also
   selected.
3. Pick "Aggressive Saver" → confirm landing on `/bank/configure` shows exactly those
   types/percentages, and the projection panel already shows the same income and matching
   yearly numbers.
4. Attempt to navigate directly to `/bank` (or any other allowed page) from a
   not-yet-onboarded account → confirm redirect to `/onboarding`.
5. On Configure: edit a percent, toggle a type inactive, and change the income figure →
   confirm the yearly mini-cards update instantly with no page reload; confirm
   "Unallocated" appears/disappears correctly around the 100% boundary.
6. Confirm the `expenses` type never appears in the yearly grid, but a custom `wallet_out`
   type does.
7. Log out mid-onboarding (before submitting) and log back in → confirm still redirected to
   `/onboarding` (state persisted in DB, not session).
