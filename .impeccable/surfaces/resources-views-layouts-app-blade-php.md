---
version: 1
slug: "resources-views-layouts-app-blade-php"
primary_target: "resources/views/layouts/app.blade.php"
related_targets: ["resources/views/layouts/courier.blade.php","resources/views/tracking/show.blade.php"]
---

## Scope

OmniDLV operating surfaces: dispatcher panel (`/app`, Operate), courier PWA (`/courier`, Operate, phone, outdoors), public recipient tracking (`/t/{code}`, Operate, phone, from SMS). One world across all three.

## Audience and job

Dispatchers watch tens of couriers live and clear exceptions; SME owners add orders and check results a few times a day; couriers move stop to stop one-handed; recipients check where their order is. Constraints: az/en/ru/tr (Cyrillic and Azerbaijani ə must render), WCAG 2.1 AA, courier screens legible in sunlight.

## Direction contract

THESIS: The delivery network drawn as a city rail diagram: each courier's day is a line, each task a station, the hub the interchange. Refuses the category default of a navy sidebar, KPI card row and blue-pin map.

OWN-WORLD: Midnight-blue enamel ground (#0B1230 family) with porcelain-white hairline outlines and type; four line inks (scarlet, cobalt, amber, green) assigned to couriers/teams and carrying route identity; status colour only where it means something (scarlet for late/failed, amber for at-risk); interchange circles (white ring) for hubs; humanist transit sans (Fira Sans / Fira Sans Condensed, uppercase tracked labels, tabular minutes). Courier PWA and tracking page translate to the porcelain variant: white ground, the same inks and type, for sunlight. Raised from declined challengers: state readable without hue (solid assigned, dashed pending, struck cancelled); one saturated action colour reserved for what can be pressed; tracking codes set as the identity line; a strict three-step type scale.

STORY: The dispatcher sees the network at a glance, spots the line that is late, follows it, and fixes it (reassign, call, reroute) without leaving the map.

FIRST VIEWPORT: Left nav rail (outlined porcelain, icons + uppercase labels, live system status and local time at the foot); top bar with hub selector, search, primary "+ Yeni tapşırıq" in scarlet; centre the live map with couriers' routes drawn as coloured lines and stops as station ticks, hub as interchange ring; right column "Diqqət tələb edir" list (late, unassigned, unresolved) with minutes-left numerals; bottom strip of outlined panels: Next stops, Alerts, Today's numbers.

FORM: Midnight transit diagram (catalog challenger wayfinding-cartography-signage-midnight-transit-diagram), chosen by the user over assigned candidate 4 (Caspian chart); seed key 71f9c71b. Signature move: courier routes and the day plan drawn as transit lines with station ticks, both on the map and as the per-courier "line" strip in task and plan views; the selected line burns brightest while the others dim.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved

Logo mark (none exists; use the interchange-ring wordmark built in type). Production domain.
