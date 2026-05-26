# SimpleStom Tooth Chart Contract

Last updated: 2026-05-26

Stage 9 aligns the EspoDental tooth chart with the SimpleStom clinical
contract while keeping `ToothChartSnapshot` as the storage model.

## Dentition

The renderer supports the three SimpleStom dentition modes:

- `adult`: 32 permanent teeth, top row `18` through `28`, bottom row `48`
  through `38`;
- `child`: 20 deciduous teeth, top row `55` through `65`, bottom row `85`
  through `75`;
- `mixed`: adult and child layouts in one chart with visual separation.

The `ToothChartSnapshot.dentitionType` enum remains the backend source of
truth for these modes.

## State Rules

SimpleStom separates whole-tooth states from surface states.

Whole-tooth states:

- `healthy`
- `crown`
- `bridge`
- `implant`
- `extracted`
- `missing`

Surface states use the five standard surfaces `O`, `M`, `D`, `B`, `L`. General
surface states are allowed on any surface, while SimpleStom-specific cosmetic
rules are enforced in the renderer:

- `veneer` is allowed only on `B`;
- `sealant` is allowed only on `O`;
- whole-tooth-only states are not offered in surface selectors.

Bridge teeth receive a stronger dashed outline. Extracted and missing teeth are
muted in the silhouette while the FDI number and place remain visible.

## Patient Workspace

The patient workspace now exposes tooth-chart state without forcing a jump to
the full `ToothChartSnapshot` record:

- `currentSnapshot`: latest snapshot summary, dentition, date, doctor and visit;
- `recentSnapshots`: recent snapshot history for in-place review;
- `summary`: compact list of marked whole-tooth and surface states.

The full EspoCRM record remains available through normal navigation, but the
SimpleStom-style workspace can answer the common clinical-history question from
the patient card itself.
