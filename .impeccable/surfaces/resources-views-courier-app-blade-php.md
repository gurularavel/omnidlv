---
version: 1
slug: "resources-views-courier-app-blade-php"
primary_target: "resources/views/courier/app.blade.php"
related_targets: ["resources/views/courier/task.blade.php","resources/views/courier/login.blade.php"]
---

## Scope

Courier PWA home (`/courier`), the screen a courier keeps open all shift; task detail (`/courier/tasks/{id}`) and sign-in share its world. Operate mode, phone, outdoors in sunlight, one hand.

## Audience and job

A courier on the street answers one question again and again: where now, and what do I press. Constraints: porcelain variant of the midnight transit world (DESIGN.md), large touch targets, az/en/ru/tr, works offline (queued actions), GPS only while the app is open.

## Direction contract

THESIS: The next stop owns the screen; everything else waits in a drawer. Refuses the delivery-app default of a scrolling card list with small buttons.

OWN-WORLD: Porcelain ground (#ffffff) with ink-900 type, outlines in --outline; the courier's own line ink (cobalt/teal/green/violet) for their route line and next-stop ring; scarlet only for the primary action and late; amber only for at-risk minutes; Fira Sans Condensed uppercase for the address and labels, tabular minutes.

STORY: The courier opens the app, sees the next address and minutes left, calls or navigates, presses the one big action; the screen advances to the next stop; the drawer shows the rest of the day as a line.

FIRST VIEWPORT: Top bar with shift ring, shift state and counts (done/total, pool). Hero: label "next stop · code", huge minutes numeral coloured by urgency, the address set large in condensed uppercase, building/floor/apartment, recipient name and phone. A 170px map strip with the courier's position and the stop as a station on their ink line. Requirement chips. Call and Navigate side by side. One full-width scarlet primary action. At the foot, "Then N" with a mini line of upcoming stations that opens the drawer (remaining stops, pool, done today).

FORM: Surface structure "next stop", candidate 2 of the grounded list, dealt by seed 2f5a2a25 and chosen by the user. Signature move: the mini transit line at the foot, the day's remaining stops as stations, expanding into the drawer's full line.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
