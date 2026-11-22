# Dashboard display audit

Reviewed 22 November 2026 against the local dashboard.

| Observed issue | Display change |
| --- | --- |
| Similar card treatments gave every section the same emphasis | Grouped upcoming attention, inventory overview and purchase intelligence with distinct headings |
| Repeated segmented status meters made comparisons visually noisy | One composition strip, an estate total and exact count/percentage legend |
| Utilisation occupied a large panel with a small progress bar | Radial gauge paired with the original count and accessible progress value |
| Chart variety was present but visually subdued | Refined category ring and location dots, ranked costs, composition strip and department value tiles |
| Repeated header dividers and heavy footer treatment flattened hierarchy | Quieter dividers, consistent spacing, stronger numeric typography and lighter secondary links |
| Limited visual response to entering a section | Once-per-entry chart and card motion, with reduced-motion and print fallbacks |

## Data and controls

The data endpoint, reporting scopes, currency separation and calculation basis are preserved. No records were edited. Exact counts remain visible; animation never counts through invented intermediate values. The purchase ledger uses its supplied amounts for a proportional visual accent.

## Validation

- 13 existing frontend chart-data tests passed, including currency separation, undated costs, zero values and rounding.
- JavaScript syntax checks and Pint passed.
- Browser checks covered desktop, a narrow viewport, reduced motion, all-category expansion, and switching USD/ZAR and restoring ZAR.
- Mobile content fits the viewport without horizontal page overflow.

This was an interface audit and implementation check, not a moderated usability study. No new Streamline illustration or icon assets were needed; charts use native SVG/CSS and existing product controls.

## Second layout revision

The first styling pass retained too much of the original card stack. The second revision makes the asset-status panel the main visual anchor, pairs utilisation beside it, and places category share, location ranking and availability in one desktop row. Upcoming metrics use compact cards with the number beside the title. Purchase breakdowns use three smaller supporting panels. Native section links show the current location and stack responsively.

Mobile checks confirmed a single-column layout, all four status values and no horizontal page overflow. Section links preserve keyboard navigation and have explicit labels. The data source and calculations remain unchanged.

## Reference-led dark dashboard

The supplied references called for a stronger change than the first two layouts: dark layered surfaces, luminous lilac/lime marks, rounded columns, a half dial, floating readouts and less empty space. These are implemented with native CSS, SVG and the existing data endpoint. Official Stats SA branding is retained. No remote visual assets or reference data were imported.

Location columns and annual purchase bins are inspectable with pointer and keyboard. Currency changes rebuild the purchase plot from that currency's records. The half dial replaces the duplicated full-circle utilisation graphic. The monthly plot does not add projected purchases or invented trend values.

Fourteen chart-data checks passed, including a new annual-bin case for empty months, a different year, aggregation and negative adjustments. Browser verification covered arrow-key readouts, all 26 locations, USD/ZAR switching, reduced motion and mobile plot containment with 44-pixel targets.

## Brand and theme correction

The layout, motion and chart interactions are retained, while the reference images' lilac/lime palette and forced dark shell have been removed. The dashboard uses the same Stats SA surface, text, bronze action and sage/sand/stone chart tokens as the rest of the application. Existing `data-theme` switching controls both modes without a reload or data fetch. Chart series and SVG strokes use CSS variables, so their colours change with the page.

Verified the actual account-menu theme control in both directions, with desktop and narrow layouts. Light uses white/warm-neutral panels and dark text; dark uses the system charcoal panels and light text. Utilisation uses the deployed-status colour, and status strip colours follow status identity rather than whichever status ranks first. Neither data nor animation behaviour was changed by this correction.

## Dashboard detail refinements

The category ring is now 230 pixels with the legend below. Location and purchase tooltips appear only at the hovered or focused bar, with no readout on arrival. Pointer exit and Escape dismiss them; arrow keys update the focused value. Mobile keyboard tooltips remain inside the chart during horizontal scrolling.

Summary counts and the acquisition total use stronger weight, with smaller, quieter context. The summary grid and chart grid use 10-pixel gutters, purchase panels use 12 pixels, and footer arrows align to the right edge. Bronze selection uses white text at a measured 4.83:1 contrast in both modes. Browser checks covered hover placement and dismissal for both plots, keyboard navigation, light/dark selection colours, mobile overflow and legend placement. All 14 chart-data checks and Pint passed. The data source and calculations were unchanged.

### Dashboard motion pacing

Chart entrances now observe the chart itself at 25% visibility, with a 32px inset at the bottom of the viewport. Newly rendered charts are registered after API responses and filter changes. Cards settle over one second; donut segments draw over 2.2 seconds, the utilisation dial sweeps over 2.4 seconds, and columns rise over 1.85 seconds with an 80ms stagger. Status and manufacturer segments unfold individually. Values stay exact and readable. Reduced motion skips entrances and finishes any running animation immediately; printing also finishes active motion. The old overlapping CSS entrances have been removed.

### Animated dashboard figures

Headline totals, status percentages, utilisation, category and financial breakdown values, average purchase cost and cost coverage count smoothly to their exact supplied values over 2.4 seconds when visible. Final-width placeholders prevent layout shifts. Screen readers receive the final figure throughout; reduced motion, printing and leaving the tab finish counters immediately. New data replaces an unfinished count safely; unchanged values remain settled. Dates, table records and hover tooltips keep their precise static display. Frontend checks cover visibility, cents, negative adjustments, mid-animation replacement, accessibility, reduced motion, printing and zero values.

### Counter stability corrections

Counters update existing text nodes instead of replacing nodes every frame, avoiding repeated page-wide mutation scans. The generic interface motion controller skips dashboard card reveals and counters so dashboard motion has one owner. Unchanged numeric updates do not restart active counts. Counts use a bounded 2.6-second ease-in/ease-out, with stable left alignment and tabular numerals retained after settling. Regression checks cover monotonic bounds and preserving text-node identity across frames. Browser samples confirmed increasing currency values and unchanged counter position and width.

### Whole-number rendering and counter layout

A browser frame capture revealed that the default Intl number formatter was exposing three fractional digits for asset and alert counts during interpolation. Counts now use an explicit whole-number counter API, while percentages and financial amounts retain their own precision. The summary metric rule also overrode the counter grid display; the dedicated grid display now takes precedence, so hidden sizing text overlaps instead of taking an additional inline slot. Browser sequences confirmed whole-number intermediate values and unchanged summary widths and card heights. Regression tests now inspect formatted frames, including digit length and precision differences between counts, percentages and money.
