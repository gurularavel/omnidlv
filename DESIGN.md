---
name: OmniDLV
description: Delivery operations drawn as a midnight transit diagram; couriers are lines, tasks are stations, hubs are interchanges.
colors:
  night-950: "#070c20"
  night-900: "#0b1230"
  night-850: "#0f1939"
  night-800: "#152147"
  night-700: "#1f2d5a"
  night-600: "#33447a"
  night-500: "#4d5f97"
  porcelain: "#f4f2ec"
  porcelain-dim: "#c3c9da"
  porcelain-mute: "#8e99b8"
  ink-scarlet: "#e21d2d"
  ink-cobalt: "#2f6bff"
  ink-amber: "#ffc20e"
  ink-green: "#22b14c"
  ink-teal: "#16b3a5"
  ink-violet: "#9b6bff"
  ink-scarlet-text: "#ff6b6b"
  ink-cobalt-text: "#86a8ff"
  ink-green-text: "#5fd98a"
  paper: "#ffffff"
  paper-2: "#f3f4f7"
  line: "#d8dce6"
  ink-900: "#0b1230"
  ink-600: "#45506e"
  porcelain-danger: "#c8102e"
  porcelain-risk: "#8a5d00"
typography:
  display:
    fontFamily: "Fira Sans Condensed, Fira Sans, ui-sans-serif, sans-serif"
    fontSize: "32px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "0.02em"
  hero:
    fontFamily: "Fira Sans Condensed, Fira Sans, ui-sans-serif, sans-serif"
    fontSize: "56px"
    fontWeight: 700
    lineHeight: 0.95
    letterSpacing: "0.01em"
    fontFeature: "tnum"
  title:
    fontFamily: "Fira Sans Condensed, Fira Sans, ui-sans-serif, sans-serif"
    fontSize: "20px"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.04em"
  value:
    fontFamily: "Fira Sans Condensed, Fira Sans, ui-sans-serif, sans-serif"
    fontSize: "20px"
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: "0.02em"
    fontFeature: "tnum"
  body:
    fontFamily: "Fira Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    fontFeature: "tnum"
  body-courier:
    fontFamily: "Fira Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
    fontFeature: "tnum"
  label:
    fontFamily: "Fira Sans Condensed, Fira Sans, ui-sans-serif, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    letterSpacing: "0.08em"
rounded:
  tag: "4px"
  control: "6px"
  marker: "8px"
  plate: "10px"
  round: "999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  plate-x: "18px"
  page: "28px"
components:
  button-primary:
    backgroundColor: "{colors.ink-scarlet}"
    textColor: "{colors.paper}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.porcelain}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-secondary-hover:
    backgroundColor: "{colors.night-800}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.porcelain}"
    rounded: "{rounded.control}"
    height: "38px"
  button-small:
    rounded: "{rounded.control}"
    padding: "0 10px"
    height: "30px"
  input:
    backgroundColor: "{colors.night-900}"
    textColor: "{colors.porcelain}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "40px"
  plate:
    backgroundColor: "{colors.night-850}"
    textColor: "{colors.porcelain}"
    rounded: "{rounded.plate}"
  plate-porcelain:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-900}"
    rounded: "{rounded.plate}"
  tag:
    textColor: "{colors.porcelain-dim}"
    rounded: "{rounded.tag}"
    padding: "0 8px"
    height: "22px"
  nav-link:
    textColor: "{colors.porcelain-dim}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "40px"
  nav-link-active:
    textColor: "{colors.porcelain}"
---

# Design System: OmniDLV

## Overview

**Creative North Star: "The Midnight Transit Diagram"**

The delivery network is drawn as a city rail map. Each courier's day is a coloured line, each task is a station tick on that line, and each hub is an interchange ring. The panel sits on midnight-blue enamel, with porcelain-white outlines and type. Lines are cased in the ground colour so they read like lines on a printed map. The map itself is re-inked into the enamel, so the courier inks carry the picture and the streets drop back to faint blue-grey hairlines.

The system is dense and operational, built for people who watch tens of couriers at once. Hue is scarce on purpose. It does exactly three jobs: courier identity, action plus late/failed, and at risk. Everything else is porcelain on enamel. Status is read from the form of a line (dashed, solid, double, struck), so the screen still works for someone who cannot tell the colours apart. Labels are uppercase and tracked, and every number is tabular, the way a station sign is set.

The same world comes in two materials. The dispatcher panel uses midnight enamel. The courier app and the public tracking page use the porcelain variant (white ground, same inks, same type) so they stay legible in sunlight. The variant changes only the surface tokens, never the grammar.

**Key Characteristics:**
- Midnight enamel ground (#0b1230 family) with porcelain outlines and type.
- Four identity inks for couriers and teams (cobalt, green, teal, violet). Scarlet and amber are kept for alarms and are never used as line colours.
- Status shown by line form: dashed = waiting, solid = moving or done, double = failed, struck = cancelled.
- Fira Sans for body text and Fira Sans Condensed for labels, values and display, both self-hosted.
- Flat, outlined panels. Shadows appear only under things that float.
- A porcelain variant of the same world for sunlight surfaces.

## Colors

The palette is a midnight enamel ground and porcelain line work, plus a small set of saturated inks, each with one job.

### Primary
- **Signal Scarlet** (`ink-scarlet`): the single action colour (primary buttons, the active nav underline, the live-network pulse) and the colour of late and failed. Because it means both "press here" and "something is wrong", it never appears decoratively. On midnight, scarlet *text* uses **Scarlet Signal Text** (`ink-scarlet-text`) for contrast. On porcelain, danger text uses **Porcelain Danger** (`porcelain-danger`).

### Secondary
- **Line Cobalt** (`ink-cobalt`), **Line Green** (`ink-green`), **Line Teal** (`ink-teal`), **Line Violet** (`ink-violet`): the four courier/team identity inks, assigned in rotation. A courier keeps one ink everywhere: the map line, the day-plan strip, the marker plate, and the bar beside their exceptions. Cobalt also drives focus in the porcelain variant, input focus rings, checked checkboxes and text selection. **Cobalt Text** (`ink-cobalt-text`) and **Green Text** (`ink-green-text`) are the tints that stay legible as text on midnight.

### Tertiary
- **Warning Amber** (`ink-amber`): at risk (20 minutes or less left), the attention icon, the reconnecting status dot, the focus outline and the text caret on midnight. Never a line colour. As text on midnight it is also the value of `--risk-text`.
- **Porcelain Risk** (`porcelain-risk`): the darkened amber that `--risk-text` takes in the porcelain variant, next to **Porcelain Danger**. Raw amber is about 1.6:1 on white; this reads in sunlight (about 5.9:1). Amber stays usable on porcelain as a border or dashed outline (the offline-queue notice, the locked-task notice), never as text.

### Neutral
- **Midnight Enamel** (`night-900`): the panel ground, the nav rail, the header (at 95% with a backdrop blur), the map ground, and the casing under every transit line.
- **Raised Enamel** (`night-850`): panel (plate) surfaces. **Hover Enamel** (`night-800`): row and button hover. **Deep Enamel** (`night-950`): the scrim behind the mobile nav drawer. `night-700` to `night-500`: scrollbars and the edges of the map draw tools.
- **Porcelain** (`porcelain`): primary text, interchange rings, unserved station fills, courier-marker borders.
- **Porcelain Dim** (`porcelain-dim`): secondary text and labels, and the neutral ink for every status dash. Mixed at 36% it becomes the panel `--outline`.
- **Porcelain Mute** (`porcelain-mute`): tertiary text, cancelled status, couriers with no ink.
- **Paper / Paper 2 / Line / Ink 900 / Ink 600** (`paper`, `paper-2`, `line`, `ink-900`, `ink-600`): the porcelain variant's surface, hover, hairline, text and secondary text.

Hairlines are derived, not stored. On midnight, `--hairline` is porcelain at 18% (row dividers) and `--hairline-strong` is porcelain at 42% (control borders, table header rule). `--outline` is porcelain-dim at 36% (plate and rail borders). In the porcelain variant these become `line`, #8f98b0 and #c9cfdb.

Alarm text is also derived. `--danger-text` is `ink-scarlet-text` on midnight and `porcelain-danger` on porcelain. `--risk-text` is `ink-amber` on midnight and `porcelain-risk` on porcelain.

### Named Rules
**The Three Jobs Rule.** Hue does three jobs only: courier identity (the four inks), scarlet for action and late/failed, amber for at risk. Any other coloured element is noise.

**The No Alarm Lines Rule.** Scarlet and amber are never assigned as a courier or team ink. A late line keeps its own ink and shows lateness by form: a scarlet ring around the late station.

**The Neutral Status Rule.** Status dashes are drawn in porcelain-dim and carry meaning by form: dashed (created, pool), solid (assigned through completed), struck (cancelled, in porcelain-mute). The one exception is failed, which is double and scarlet. A status dash never shares a colour with a courier's line.

**The Sunlit Amber Rule.** On porcelain, at-risk minutes and any other at-risk text use `--risk-text` (`porcelain-risk`), never raw amber. Write against the variable, not the ink, so the same markup reads on both materials.

## Typography

**Display Font:** Fira Sans Condensed (with Fira Sans, ui-sans-serif)
**Body Font:** Fira Sans (with ui-sans-serif, system-ui)

**Character:** A humanist transit sans. The condensed cut is uppercase and tracked, like station signage, and gives labels, codes and numerals their voice. The regular cut keeps body text calm and readable across az/en/ru/tr. Both are self-hosted through @fontsource with latin-ext and cyrillic coverage, so ə and Cyrillic render in the real face. Numerals are tabular everywhere (`font-variant-numeric: tabular-nums` on body).

### Hierarchy
- **Hero** (700, 56px, line-height 0.95, 0.01em; courier app only): the next stop's minutes-left, read at arm's length in sunlight. Sized so a three-digit negative value and its label fit a 390px frame.
- **Display** (700, 32px, line-height 1): the headline of a view. The task's tracking code on a task page, the status word on the tracking page. Uppercase there.
- **Title** (700, 20px, 1.2, 0.04em, uppercase): page titles in the header, empty-state titles, the wordmark.
- **Value** (600, 20px, 1.15, 0.02em): values and list numerals. Minutes-left, the clock, key facts in a task header.
- **Body** (400, 14px, 1.5): running text, table cells, form values in the panel.
- **Courier body** (400, 16px, 1.5): running text on the phone surfaces used outdoors: the courier app (address lines, recipient, notes, sign-in copy) and the tracking lookup. The large primary buttons there (56 to 64px tall) set their text at 16px too.
- **Label** (600, 12px, 0.08em, uppercase, porcelain-dim): field labels, table headers, plate-head titles, section headings inside a view.
- **Code** (600, 0.06em, condensed): tracking codes and SKUs, set as the identity line of a record at whatever size the context uses.

### Named Rules
**The Three Steps Rule.** The type scale is label 12, body 14, value/title 20, display 32. A new size needs a reason that one of these cannot meet. The porcelain phone surfaces carry two such reasons: body 16 (read outdoors on a phone) and hero 56 (the courier's next-stop minutes, courier app only). Neither belongs on the panel.

As built, controls (buttons, nav links, status labels) run at 13px and a few rail sub-labels at 10 to 11px. These predate the rule and are drift, not scale steps. New surfaces use the four steps.

**The Tabular Minutes Rule.** Every number is tabular. Minutes-left is a Value numeral (Display on a task page, Hero for the courier's next stop): `--danger-text` when negative (late), `--risk-text` at 20 or less (at risk), plain text colour otherwise. Late minutes carry a minus sign (−15).

## Layout

The panel is a fixed nav rail (236px, outlined right edge) plus a 64px sticky header and the work area. The work area has 16px padding, widening to 28px from 1024px. The live monitor runs full-bleed: map on the left, the "needs attention" column on the right, courier lines under the map. Detail views use a three-column grid of plates that collapses to one column on phones.

Below 1024px the rail becomes an off-canvas drawer over a `night-950` scrim. Header search appears from 768px. Below 640px the header's primary action collapses to its 38px icon so the title keeps its room. Horizontal tab strips and status boards scroll by swipe with the scrollbar hidden.

The porcelain variant is a single centred column for phone use. The courier app's column is max 520px, and its sticky action bar and bottom sheets are capped at the same 520px. The tracking page is max 560px. The sign-in forms are narrower (440px for courier sign-in and code entry, 480px for tracking lookup).

Rhythm is tight and regular. Gaps run 8 / 10 / 12px inside rows. Table cells are 12px by 14px, plate heads 14px by 18px, porcelain plate rows 16px.

## Elevation & Depth

The system is flat and outlined. Depth comes from tone (night-900 ground, night-850 plates, night-800 hover) and from 1.5px porcelain outlines, not from shadows. Shadows appear only under things that float above the map or the page.

### Shadow Vocabulary
- **Floating menu** (`box-shadow: 0 12px 32px rgb(0 0 0 / 0.4)`): dropdown plates such as the language menu.
- **Map popup** (`box-shadow: 0 8px 24px rgb(0 0 0 / 0.35)`): Leaflet popups.
- **Courier marker** (`box-shadow: 0 4px 12px rgb(0 0 0 / 0.45)`): the courier's ink plate on the map.
- **Knockout ring** (`box-shadow: 0 0 0 3px var(--surface)`): not elevation. It cuts an interchange or late-station ring out of the line beneath it, the way a printed map does.

### Named Rules
**The Outline Not Shadow Rule.** Panels and rails are separated by `--outline` (porcelain-dim at 36%, 1.5px), never by a drop shadow. A shadow means the thing floats.

**The Isolated Map Rule.** Leaflet panes stack up to z-index 1000. Every Leaflet container is isolated (`isolation: isolate`) so sticky headers, drawers, action bars and sheets always sit above the map.

## Shapes

Corners are softly squared: 10px for plates and the map frame, 6px for buttons, inputs and nav links, 4px for tags, checkboxes and the focus outline, 8px for courier marker plates. Circles are reserved for transit symbols: stations (14px, 3px ring), interchanges (22px, 4px porcelain ring) and status dots. Lines have round caps and joins. On the map, a route is an 11px casing in the ground colour under a 6px ink core. In lists, a courier line is a 6px rounded bar. Borders are 1.5px for structure and controls and 1px for row hairlines.

## Components

### Buttons
Signage-like: condensed uppercase at 13px, tracked 0.08em, 600.
- **Shape:** gently squared (6px), 38px tall, 16px horizontal padding. Small is 30px with 10px padding at 12px. Icon is 38px square.
- **Primary:** Signal Scarlet fill and border, white text. Reserved for the one thing to press on a view ("+ New task", "Save assignment"). On the tracking page, `--action` takes the company's brand colour.
- **Hover / Focus:** primary darkens to scarlet mixed 86% with black. Secondary fills with Hover Enamel and its border goes to full porcelain. All buttons press down 1px when active. Transitions are 160ms on ease-out-expo. Focus is a 2px amber outline (cobalt on porcelain) with a 2px offset.
- **Secondary:** transparent with a 1.5px `--hairline-strong` border. **Ghost:** no border. **Danger:** scarlet border at 70%, scarlet text. **Disabled:** 45% opacity.

### Chips (tags)
- **Style:** 22px tall, 4px corners, 1px `--hairline-strong` border, 12px porcelain-dim text, no fill.
- **State:** a "late" reason tag takes a scarlet border at 70% and scarlet text. Other reasons stay neutral.

### Cards / Containers (plates)
- **Corner Style:** 10px.
- **Background:** Raised Enamel on midnight, paper on porcelain.
- **Shadow Strategy:** none (see Elevation & Depth).
- **Border:** 1.5px `--outline`. The head is divided from the body by a 1px hairline.
- **Internal Padding:** head 14px by 18px with a Label title, body 16 to 20px.

### Inputs / Fields
- **Style:** 40px tall, 6px corners, ground-colour fill, 1.5px `--hairline-strong` border, 12px padding. Selects carry an inline chevron. The caret is amber on midnight and cobalt on porcelain.
- **Focus:** the border turns Cobalt Text and gains a 3px cobalt ring at 30%. Hovering raises the border to porcelain-dim.
- **Error / Disabled:** `aria-invalid` turns the border scarlet. Error text is 13px danger text below the field. Labels use the Label style with 6px below.

### Navigation
Rail links are condensed uppercase 13px, 40px tall, with an icon, in porcelain-dim. On hover they turn porcelain on Hover Enamel. The current page is porcelain text with a 3px scarlet underline bar inset 12px, like a line marker under a station name. The same treatment is reused for tab strips. The rail foot carries live system status (green or amber dot), local time as a Value numeral, and the user.

### Tables
Header cells are Label style over a `--hairline-strong` rule. Body cells are 12px by 14px with hairline dividers. Rows fill with Hover Enamel on hover.

### Transit symbols (signature)
- **Station:** a 14px ring in the line's ink (3px). Filled = served, ring = upcoming. On the map it is a circle marker with radius 6 and a porcelain fill until served.
- **Late station:** the station gains a knockout ring and a scarlet-text outer ring (`0 0 0 2px` ground, `0 0 0 4px` #ff6b6b).
- **Interchange:** a 22px ground-filled circle with a 4px porcelain ring and a ground knockout. Used for hubs on the map and as the wordmark's mark.
- **Transit line:** casing plus ink core with round caps. Unselected lines dim (core 0.3, casing 0.35) so the selected line burns brightest.
- **Courier marker:** a 30px ink plate with 8px corners, a 2px porcelain border and white condensed initials. Offline couriers show at 55%.
- **Status:** a short line glyph (18px by 3px) before the label at 13px: solid, dashed (4px on, 3px off), double (two 2px rules), or struck text for cancelled.
- **Map tinting:** OSM tiles are inverted and greyscaled, then an enamel pane (#24388a, blend `color`) re-inks them into the night family. Map labels are condensed uppercase with a halo in the ground colour. On porcelain, tiles are quieted (grayscale 0.9, 85% opacity) instead.
- **Map controls:** the zoom control sits bottom-right on maps that are operated (panel maps, the tracking page).
- **Glance strip:** a map shown only to orient (the courier's next stop, 170px tall inside a plate frame) has no controls and no gestures: no zoom control, dragging, scroll, pinch or double-tap zoom. It is hidden from assistive tech, because the address above it carries the information.

## Do's and Don'ts

### Do:
- **Do** give every courier one ink from the four identity inks and use it everywhere that courier appears.
- **Do** show status by line form (dashed, solid, double, struck) with the neutral porcelain-dim ink.
- **Do** keep scarlet for the single primary action and for late/failed, and amber for at risk.
- **Do** outline plates and rails with `--outline` (porcelain-dim at 36%, 1.5px) and separate rows with `--hairline`.
- **Do** case every route line in the ground colour and dim unselected lines.
- **Do** use the porcelain variant (`.theme-porcelain`) for surfaces used outdoors on a phone: the courier app and the tracking page.
- **Do** set labels, buttons and nav in Fira Sans Condensed, uppercase and tracked, and keep every number tabular.
- **Do** colour at-risk text with `--risk-text` and late text with `--danger-text`, so it holds on both materials.
- **Do** isolate every Leaflet container, and strip controls and gestures from maps that are only glance strips.

### Don't:
- **Don't** put kicker or eyebrow labels above headings. A Label names a field, a column or a plate. It never introduces a title.
- **Don't** assign scarlet or amber as a courier or team ink.
- **Don't** colour a status dash with an identity ink or a decorative hue.
- **Don't** separate panels with drop shadows. Shadows are only for menus, popups and map markers.
- **Don't** add type sizes outside label 12 / body 14 / value-title 20 / display 32, plus body 16 and hero 56 on the porcelain phone surfaces.
- **Don't** set text in raw amber on porcelain.
- **Don't** ship a second basemap or a stock blue-pin map. Re-ink the tiles into the enamel.
- **Don't** fall back to a system face for display. Fira Sans Condensed is self-hosted for latin-ext and cyrillic.
