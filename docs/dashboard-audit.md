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
