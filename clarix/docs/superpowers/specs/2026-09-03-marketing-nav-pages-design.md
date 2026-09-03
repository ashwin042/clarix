# Marketing nav pages — design

Every item in the marketing site's Product, Solutions and Resources dropdowns is
currently a dead `'#'`. This gives each of them a real page, and restructures the
marketing site so that thirteen pages share one definition of their chrome.

## Current state

The whole public site is `resources/views/marketing/home.blade.php`: a single
2,744-line standalone `<html>` document with an ~800-line inline `<style>` block,
served by `Route::view('/home', 'marketing.home')->name('home')`. It uses no
layout and shares nothing with the authenticated app's views.

Navigation is declared twice inside that file, as `@php` arrays: `$navMenus`
(line ~820, rendered into both the desktop dropdowns and the flattened mobile
panel) and `$footerNav` (line ~1989). Every href in both is `'#'` except
`#pricing` and the Product menu's AXOKAI entry, which points off-site to
`https://axokai.codesnextdoor.com/`.

## Decisions taken

1. **Extract the layout and migrate the homepage onto it.** One source of truth
   for header, footer and shared CSS. The homepage is touched, so it gets an
   explicit visual check before any new page is built.
2. **AXOKAI gets an on-site page** at `/product/ai-automation` that links out to
   `axokai.codesnextdoor.com` from its primary CTA. The dropdown points at the
   internal page.
3. **Customer Stories ships with clearly-fictional placeholders** — sample studio
   names, role-only bylines, no photographs, no named individuals — and a Blade
   comment naming the arrays to replace with real content.

## Composition strategy

Twelve bespoke pages sharing chrome only would drift; one data-driven template
across twelve pages would produce exactly the interchangeable-SaaS-page look this
work is meant to avoid. So: **a shared section library, with a bespoke hero per
page.**

Structural rhythm — section padding, container width, eyebrow/heading/subhead
type scale, card radius and border, CTA band — comes from Blade components and
cannot drift. The hero mockup, which is the thing a visitor actually looks at, is
hand-built per page from the motifs the homepage already established: the
window-chrome frame, the tilted floating card, the terminal, the credit card, the
arc/ring diagram.

No page introduces a visual idiom the homepage does not already use. Specifically
ruled out: stock gradient blobs, icon-in-a-circle three-column grids, stock
illustration, and abstract shapes unrelated to the product.

## Routing

A `marketing` route group in `routes/web.php`, all names prefixed `marketing.`.
All are `Route::view` unless a page needs data.

| Path | Route name | View |
|---|---|---|
| `/product/task-boards` | `marketing.product.boards` | `marketing.product.boards` |
| `/product/client-portal` | `marketing.product.portal` | `marketing.product.portal` |
| `/product/ai-automation` | `marketing.product.ai` | `marketing.product.ai` |
| `/product/file-management` | `marketing.product.files` | `marketing.product.files` |
| `/product/credits-billing` | `marketing.product.credits` | `marketing.product.credits` |
| `/solutions/agencies` | `marketing.solutions.agencies` | `marketing.solutions.agencies` |
| `/solutions/freelancers` | `marketing.solutions.freelancers` | `marketing.solutions.freelancers` |
| `/solutions/enterprises` | `marketing.solutions.enterprises` | `marketing.solutions.enterprises` |
| `/blog` | `marketing.blog` | `marketing.resources.blog` |
| `/docs` | `marketing.docs` | `marketing.resources.docs` |
| `/customers` | `marketing.customers` | `marketing.resources.customers` |
| `/help` | `marketing.help` | `marketing.resources.help` |

## Shared extraction

### `config/marketing.php`

The single definition of navigation, replacing both `@php` arrays. Shape:

```php
'menus' => [
    'Product' => ['width' => 'w-[332px]', 'items' => [
        ['label' => '…', 'description' => '…', 'route' => 'marketing.product.boards'],
    ]],
],
'links'  => [['label' => 'Pricing', 'url' => '/home#pricing']],
'footer' => ['Product' => [], 'Learn' => [], 'Company' => []],
```

Each item carries **either** `route` (resolved with `route()`) **or** `url` (used
verbatim; `http`-prefixed values still render `target="_blank" rel="noopener
noreferrer"`). Items that have no page yet keep an explicit `url => '#'` so the
link test can distinguish "not built yet" from "typo".

In-page anchors that used to be bare `#pricing` become `/home#pricing`, since
they are now reachable from pages that are not the homepage.

### Components

- `components/marketing/layout.blade.php` — the `<html>` document: head, fonts,
  `@vite`, shared `<style>`, header, `{{ $slot }}`, footer. Props: `title`,
  `description`. Exposes `@stack('styles')` in the head for page-local CSS.
- `components/marketing/header.blade.php` — desktop dropdowns and the
  checkbox-driven mobile panel, both reading `config('marketing.menus')`.
- `components/marketing/footer.blade.php` — reads `config('marketing.footer')`.
- `components/marketing/section.blade.php` — section wrapper owning vertical
  rhythm and container width. Props: `surface` (`white` | `soft` | `ink`), `id`.
- `components/marketing/eyebrow.blade.php` — the mono uppercase kicker.
- `components/marketing/window.blade.php` — the chrome-and-shadow mockup frame
  (traffic lights, title bar, `win-shadow`). Props: `title`, `tilt`, `dark`.
- `components/marketing/cta-band.blade.php` — the closing conversion block,
  identical across all twelve pages.
- `components/marketing/faq.blade.php` — the homepage accordion, generalised over
  a passed array.

### CSS split

The `<style>` block divides along its existing section comments.

**Shared, moves to the layout:** Type roles; Nav; Scene (`scene-glow`,
`win-shadow`, `win-shadow-dark`, `tilt-*`, `float*`, `rise*`, `scene-fade`);
Footer (`.site-footer` only); and a reduced-motion block covering `.float` and
`.rise`.

**Page-local, moves to a `@push('styles')` in `home.blade.php`:** the four hero
window loops (terminal, board, thread, phone); Problem/Solution; Platform arc;
Pricing; Why Clarix; Schedule a demo; Plan comparison; AXOKAI cards; Ring diagram
scale; `select.demo-field` (which sits under the Footer comment but belongs to
the demo form); and the remainder of the reduced-motion block.

Both halves are the existing rules moved verbatim. Batch 0 changes where the CSS
lives, never what it says.

## Pages

Each page's hero headline delivers on the promise its dropdown entry makes, in
the voice of the existing homepage copy: concise, concrete, product-specific.

Shared skeleton, varying per page in section count and order: hero (headline,
subhead, dual CTA, bespoke mockup) → two or three substance sections → CTA band.

| Page | Dropdown promise | Hero visual |
|---|---|---|
| Task Boards | Plan and track work in one place | Board mockup extended to all six real statuses: Pending, On hold, In progress, Sent for review, Completed, Cancelled |
| Client Portal | Give clients a live view of delivery | The homepage comment thread beside a read-only client view of the same board |
| AI Automation | Let AI handle the busywork | The terminal window running a longer brief-to-task transcript; primary CTA to AXOKAI |
| File Management | Keep every file attached to its task | A task card with its files column expanded — files docked to work, not loose in a drive |
| Credits & Billing | Track spend as work moves | The homepage credit-balance card, plus a ledger of debits keyed to status transitions |
| For Agencies | Manage multiple clients and teams | Org-shape diagram from the arc motif: many units under one organization |
| For Freelancers | Simplify solo project tracking | The same diagram collapsed to one person, several clients |
| For Enterprises | Scale delivery across departments | The same diagram at department scale, with the ERP surfaces named |
| Blog | — | Post grid on the soft surface, card style borrowed from the plan-comparison section |
| Documentation | — | Two-column index: sidebar tree plus section cards, mono labels |
| Customer Stories | — | Story cards with fictional placeholders; featured case study slot |
| Help Center | — | The accordion as a real FAQ, plus a contact panel |

Product facts drawn from the codebase rather than invented: task statuses from
`Task::STATUSES`; `credit_amount` debited per task; units and organizations;
per-plan storage ceilings and features from the homepage's own `$plans` array;
AXOKAI, MCP plugins, Gantt, and the attendance/leave/payroll ERP surfaces.

**Open flag — Client Portal.** `App\Models\User` has no `client` role; roles are
superadmin, admin, pm, supervisor, hr and writer. The homepage already markets a
client portal, so this page ships, but its copy stays within what the product
actually does — a shared live view of task status, notes and files — and claims
no client-specific feature set that does not exist.

## Testing

- `MarketingPagesTest` — every route in the table returns 200, and every page
  renders a distinct `<title>`.
- `MarketingNavTest` — walks `config('marketing')` and asserts no menu or footer
  item is left at `'#'`, that every `route` key names a registered route, and
  that both the desktop and mobile menus render each item.
- `MarketingHomeTest` — the homepage still renders after migration, still carries
  its hero copy, and still exposes the `#pricing` and `#schedule-demo` anchors.
- Responsive check at mobile and desktop on one page per dropdown.

## Sequencing

Each batch is reviewed before the next begins.

0. Shared extraction and homepage migration. **Visual check on the homepage
   before proceeding.**
1. Product, five pages.
2. Solutions, three pages.
3. Resources, four pages.
