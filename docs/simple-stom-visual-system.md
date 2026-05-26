# SimpleStom Visual System

Last updated: 2026-05-26

This document locks the scoped UI foundation for SimpleStom-style EspoDental
workspaces. The implementation lives in
`src/files/client/custom/modules/espo-dental/src/lib/simple-stom-ui.js`.

## Scope

The SimpleStom visual system is intentionally scoped to containers with the
`.espo-dental-stom` class. It is not a global EspoCRM theme and must not restyle
standard EspoCRM screens by default.

Future redesigned screens should:

- require `espo-dental:lib/simple-stom-ui`;
- call `SimpleStomUi.ensureStyles()` in `afterRender` or before rendering custom
  markup;
- wrap custom workspace markup with `SimpleStomUi.workspace(...)`;
- use UI-kit panels, badges, buttons, dense tables and split layouts before
  adding one-off inline styles.

## Tokens

| Token | Value | Use |
| --- | --- | --- |
| Page background | `#edf3ef` | SimpleStom-style workspace background. |
| Surface | `#ffffff` | Operational panels, repeated items and modals. |
| Alternate surface | `#f7faf8` | Table headers and quiet controls. |
| Border | `#d8e3dd` | Panel/table separation. |
| Strong border | `#bfd0c8` | Buttons and empty states. |
| Primary | `#438f7e` | Main action button and active state. |
| Primary dark | `#2f705f` | Primary hover/focus and links. |
| Text | `#1f2f2b` | Default workspace text. |
| Muted text | `#65756f` | Secondary labels, hints and table headings. |
| Radius | `8px` | Maximum panel radius for this migration. |
| Compact radius | `6px` | Buttons, badges and list items. |

## Components

### Workspace

Use `.espo-dental-stom` as the root class for every SimpleStom-style custom
workspace. This class provides the soft green background and isolates all
descendant rules.

### Layout

Use `.espo-dental-stom-layout`, `.espo-dental-stom-layout--two` and
`.espo-dental-stom-layout--three` for dense operational split panels. These
layouts collapse to a single column below 900px.

### Panels

Use `.espo-dental-stom-panel` with optional header/body blocks for white
operational surfaces. Panels are for work areas, repeated summaries and modals,
not for wrapping whole page sections inside other cards.

### Tables

Use `.espo-dental-stom-table` for compact operational data. Keep rows readable
at laptop width and avoid viewport-scaled type.

### Badges

Use `.espo-dental-stom-badge` plus tone classes:

- `--primary`
- `--success`
- `--warning`
- `--danger`
- `--info`
- `--muted`

The UI-kit maps appointment-like statuses and risk levels to these classes.

### Buttons

Use `.espo-dental-stom-button`. The primary action in each workspace uses
`.espo-dental-stom-button--primary`; secondary actions use the base or quiet
button. Destructive actions use `--danger`.

## Implementation Rules

- Keep SimpleStom styling inside `.espo-dental-stom`.
- Keep the existing EspoCRM shell, top navigation, list views and admin screens.
- Prefer helper-rendered panels, badges, buttons and empty states for migrated
  workspaces.
- Avoid nested cards, decorative blobs, one-note gradients and marketing
  layouts.
- Do not use viewport-width font scaling.
- Keep text inside controls readable and wrapping safely on narrow screens.

## First Consumers

The first expected consumers are:

1. Stage 3 dashboard action center.
2. Stage 4 calendar feedback workspace.
3. Stage 6 patients two-pane workspace.
4. Stage 10 inventory workspace.
5. Stage 11 cash desk workspace.
