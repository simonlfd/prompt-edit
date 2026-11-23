# UI/UX style guide

Based on promptedit.com's production CSS and copy (pulled 2026-11-23). promptedit.com is itself a
Laravel + Inertia + Vue app on the official starter kit (shadcn-vue tokens such as `--primary` and
`--sidebar-border`, plus Tailwind v4). That means these tokens drop straight into our
`resources/css/app.css`.

PromptEdit has two visual layers:
- **Sales** (marketing pages): warm paper canvas, Bricolage Grotesque display type, lime "aurora" gradients.
- **Studio** (the logged-in app): calm neutrals, Inter, one lime accent, mono for numbers.

**Our app is a studio screen.** Use studio tokens everywhere, and use the sales layer for at most one
heading on the login/welcome page.

---

## 1. Design principles

1. **Neutral canvas, one accent.** Almost everything is warm grey/ink. Lime is used for the single
   primary action and for "success/ready", never as decoration.
2. **Numbers are first-class.** Costs, token counts and sizes are set in Roboto Mono with tabular
   figures so they line up and read as precise.
3. **Say exactly what happens.** Copy on the site is literal and specific ("2K and 4K images from text
   and up to 10 reference images"). Do the same for cost and status.
4. **The image is the hero.** Chrome recedes (hairline borders, soft shadows); media gets the space.

---

## 2. Colour tokens

### Studio (app) palette
| Token | Light | Dark | Use |
|---|---|---|---|
| `--studio-page` | `#fbfaf9` | `#171717` | App background (warm off-white, not pure white) |
| `--studio-page-alt` | `#f3f0ed` | `#1f1f1f` | Sidebar, sunken panels, image placeholders |
| `--studio-card` | `#ffffff` | `#262626` | Cards, form panels |
| `--studio-ink` | `#262626` | `#ffffff` | Primary text |
| `--studio-secondary` | `#595959` | `#ffffffcc` | Secondary text |
| `--studio-muted` | `#6b6b6b` | `#ffffff99` | Hints, timestamps, labels |
| `--studio-hairline` | `#e5e5e5` | `#ffffff1a` | Borders, dividers |
| `--studio-field` | `#ffffff` | `#303030` | Inputs |
| `--studio-choice` | `#f0f0f0` | `#1d1d1d` | Segmented control track / unselected chips |
| `--studio-accent` | `#9ae600` | `#9ae600` | Primary button fill, focus, success dot |
| `--studio-accent-hover` | `#7ccf00` | `#7ccf00` | Primary button hover |
| `--studio-accent-vivid` | `#497d00` | — | Lime-family **text/icons on light backgrounds** (`#9ae600` fails contrast as text) |
| `--studio-alert` | `#dc2626` | `#f87171` | Errors, failed state |
| `--studio-alert-hover` | `#b91c1c` | — | Destructive hover |
| warning (`--warning`) | `#faa23d` | `#ffa538` | Retrying / rate-limited |
| info (`--studio-audio`) | `#0092b8` | `#0092b8` | Processing / informational |

### shadcn tokens (starter kit), as on promptedit.com
Light: `--background #fff`, `--foreground #0a0a0a`, `--primary #059467`, `--secondary #ebebeb`,
`--muted #f5f5f5`, `--muted-foreground #737373`, `--border #ededed`, `--input #ccc`,
`--ring #d6d3d1`, `--destructive #ef4444`, `--radius .5rem`.
Dark: `--background #0a0a0a`, `--foreground #fafafa`, `--primary #fafafa`, `--secondary #262626`,
`--muted #292929`, `--border #262626`, `--destructive #ef4343`.

> Note: the shadcn `--primary` (emerald `#059467`) is used by stock components. The studio UI
> overrides primary actions with lime. In our app, **set `--primary` to the lime accent** (below) so
> stock shadcn-vue buttons match.

### Rules
- **Text on lime is ink (`#262626`), never white.** White on `#9ae600` is unreadable.
- Lime text/links on light backgrounds use `#497d00`.
- Never use more than one lime element per view region.

---

## 3. Typography

Fonts (Google Fonts, as loaded by the site):
```html
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300..800&family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@400;500;700&display=swap" rel="stylesheet">
```

| Role | Font | Weight | Size (Tailwind) |
|---|---|---|---|
| Display (welcome/login hero only) | Bricolage Grotesque | 600–700 | `text-4xl`–`text-6xl`, `tracking-tight` |
| Page title | Inter | 600 | `text-2xl` |
| Section / card title | Inter | 600 | `text-base`–`text-lg` |
| Body / UI | Inter | 400–500 | `text-sm` (14px is the studio default) |
| Labels / eyebrows | Inter | 500 | `text-xs`, muted colour |
| **Money, tokens, IDs, sizes** | Roboto Mono | 500 | `text-sm`, `tabular-nums` |

- Sentence case everywhere ("Generate image", not "Generate Image").
- Line length: ≤ 70ch for any paragraph.

---

## 4. Shape, elevation, spacing

**Radius** (base `--radius: .5rem`):
`xs 2px` · `sm 4px` · `md 6px` · `lg 8px` (inputs, buttons) · `lg-plus 12px` · `xl 16px` (cards) ·
`2xl 24px` (large panels, image frames) · `3xl 32px` · `full` (chips, status pills, avatars).

**Shadows:** flat by default with a hairline border. Lift only for floating things.
```css
--studio-pop-light:   0 4px 8px -3px #2626261a, 0 14px 28px -8px #26262621;   /* popovers, dropdowns */
--studio-modal-light: 0 8px 16px -6px #2626261f, 0 32px 64px -16px #2626263d; /* dialogs */
--studio-pop-dark:    0 4px 8px -3px #0000008c, 0 14px 28px -8px #000000a6;
--studio-modal-dark:  0 8px 16px -6px #0009,     0 32px 64px -16px #000c;
/* Primary (lime) button */
--accent-shadow: inset 0 1px 0 0 #ffffff73, inset 0 -2px 0 0 #26262624,
                 0 1px 2px 0 #2626261a, 0 8px 20px -6px #497d0033;
```

**Spacing:** Tailwind 4px scale. Card padding `p-5`/`p-6`, section gaps `gap-6`, form field gaps `gap-4`.

**Layout:** the studio uses a two-column workspace on `lg`: controls on one side, media on the other
(e.g. `lg:grid-cols-[minmax(0,1fr)_24rem]`). Media height is capped at
`min(40rem, 100dvh - header - 1rem)`.

---

## 5. Components for this app

### Buttons
| Variant | Style |
|---|---|
| Primary ("Generate") | lime fill, ink text, `rounded-lg`, `font-medium`, accent shadow, hover `#7ccf00` |
| Secondary | card bg, hairline border, ink text, hover `--studio-raised-hover` (`#2626260d`) |
| Ghost | no border, muted text → ink on hover |
| Destructive | `--studio-alert` fill, white text |
- One primary per screen. Disabled = 50% opacity + `cursor-not-allowed` + a reason nearby
  ("Not enough credits").
- Loading: keep the width fixed, replace the label with a spinner + "Submitting…".

### Inputs
- Field bg, hairline border, `rounded-lg`, `text-sm`, focus ring in accent (`--studio-focus`).
- Prompt textarea: auto-grow, min 4 rows, character count bottom-right in muted mono.
- **Size & quality: segmented controls**, not dropdowns: `--studio-choice` track, selected
  segment = card bg + pop shadow, radius = track radius − 3px.
- Reference image URLs: a repeatable row list ("+ Add reference image"), each row with a small
  thumbnail preview (or a broken-image placeholder) and a remove "×". Show the limit: "2 of 4".

### Cost display (the most important UI element)
```
Estimated cost          $0.0425
  Input tokens   1,024 × rate     $0.0102     ← collapsible breakdown, mono
  Output (est.)  ...              $0.0238
  Service fee 25%                 $0.0085
Final cost is confirmed when the image is ready.
```
- Updates live as fields change (debounced ~250 ms). Show a subtle skeleton while recalculating, and
  never a stale number without an indicator.
- Prefix estimates with `~` or label them "Estimated". Actual costs have no prefix.
- In history show both: `Est. $0.0425 → $0.0411`, with the difference in muted text. Failed:
  `$0.00 · not charged` in alert colour, struck-through estimate.
- Format: always `$`, mono, tabular-nums. Pick one precision for sub-cent values (e.g. 4 decimals)
  and use it consistently. Full precision in a tooltip.

### Status pills (`rounded-full`, `text-xs font-medium`, dot + label)
| Status | Dot | Pill bg (light) | Label |
|---|---|---|---|
| Queued | `#737373` | `--studio-choice` | Queued |
| Processing | `#0092b8`, pulsing | `#0092b81f` | Generating… |
| Retrying (rate-limited) | `#faa23d` | amber-50 | Waiting for capacity |
| Succeeded | `#9ae600` | lime 12% | Ready |
| Failed | `#dc2626` | red-50 | Failed |

### Generation card (history)
- `rounded-2xl` image frame, `aspect-square` (or the generation's aspect ratio), `--studio-page-alt`
  background.
- While pending: shimmer skeleton inside the frame plus the status pill. On image `@error` (expired
  URL), show a neutral placeholder: "Image link expired".
- Below the image: prompt (2-line clamp, full text on expand), meta row in mono muted
  `1024×1024 · high · 2 refs`, cost line, relative time ("2 min ago", absolute on hover).
- Grid: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6`. Newest first, and a newly submitted
  generation appears at the top immediately (optimistic).

### Alerts and toasts
- Inline alert: tinted bg (10–12% of the status colour), 1px edge at ~50%, icon + title + one sentence.
- Toasts bottom-right for async results ("Your image is ready", "Generation failed, you weren't
  charged").

### Admin tables (stretch)
- Hairline row dividers, no zebra stripes. Numbers right-aligned, mono. Profit coloured
  `#497d00`, or alert colour if negative. Sticky header, totals row in `font-semibold`.

---

## 6. Voice and microcopy

The site's copy is plain, concrete and quantified, uses British spelling ("colour"), has no hype, and
uses sentence case. Examples from the site:
> "A five-second clip from your still, with subtle motion and the original framing and light kept intact."
> "Kling AI's 4.0 image model: 2K and 4K images from text and up to 10 reference images."

Apply this to our app:
| Situation | Write |
|---|---|
| Submit button | "Generate image" |
| Estimate note | "Final cost is confirmed when the image is ready." |
| Rate limited | "Pixelforge is busy. We'll keep retrying, and you won't be charged while it waits." |
| Failed | "This generation failed: {reason}. You weren't charged." |
| Insufficient credits | "This costs about $0.04 and you have $0.01. Buy credits to continue." |
| Empty history | "No images yet. Write a prompt to make your first one." |
| Expired image | "Image link expired" |

Rules: lead with what happened, then what it means for their money, then what to do. Never blame the
user, and never show raw error codes as the main message (put them in a muted detail line).

---

## 7. Motion and accessibility
- Transitions are 150–200 ms ease-out. Use a pulse only on the "processing" dot. Respect
  `prefers-reduced-motion`.
- Contrast: body text ≥ 4.5:1. Remember lime is a fill colour, not a text colour on light backgrounds.
- Status is never colour-only: always a dot **and** a text label.
- Live status region: wrap the history list in `aria-live="polite"` so status changes are announced.
- Focus ring visible on all interactive elements (accent, 2px, offset 2px).
- Support dark mode via the starter kit's appearance toggle using the dark column above.

---

## 8. Drop-in CSS for `resources/css/app.css`

```css
:root {
  --studio-page: #fbfaf9; --studio-page-alt: #f3f0ed; --studio-card: #fff;
  --studio-ink: #262626; --studio-secondary: #595959; --studio-muted: #6b6b6b;
  --studio-hairline: #e5e5e5; --studio-choice: #f0f0f0;
  --studio-accent: #9ae600; --studio-accent-hover: #7ccf00; --studio-accent-vivid: #497d00;
  --studio-alert: #dc2626; --studio-info: #0092b8; --studio-warning: #faa23d;

  /* map onto shadcn-vue so stock components match */
  --background: var(--studio-page);
  --foreground: var(--studio-ink);
  --card: var(--studio-card);
  --primary: var(--studio-accent);
  --primary-foreground: #262626;
  --muted-foreground: var(--studio-muted);
  --border: var(--studio-hairline);
  --ring: var(--studio-accent);
  --destructive: var(--studio-alert);
  --radius: .5rem;
}
.dark {
  --studio-page: #171717; --studio-page-alt: #1f1f1f; --studio-card: #262626;
  --studio-ink: #fff; --studio-secondary: #ffffffcc; --studio-muted: #ffffff99;
  --studio-hairline: #ffffff1a; --studio-choice: #1d1d1d; --studio-alert: #f87171;
  --background: var(--studio-page); --foreground: var(--studio-ink); --card: var(--studio-card);
  --primary: var(--studio-accent); --primary-foreground: #262626;
  --border: var(--studio-hairline); --destructive: var(--studio-alert);
}
@theme inline {
  --font-sans: Inter, ui-sans-serif, system-ui, sans-serif;
  --font-mono: "Roboto Mono", ui-monospace, monospace;
  --font-display: "Bricolage Grotesque", ui-serif, Georgia, serif;
}
```
