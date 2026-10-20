# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13 + Blade, Alpine.js, Tailwind CSS v4 (Vite), Leaflet + OpenStreetMap, Laravel Reverb for realtime, PostgreSQL. The courier app is a Blade PWA (installable, camera/GPS), not a native app. Chosen by the user.

## Users

- **Dispatchers at larger delivery operations:** at a desk all day, map and task list open on a large monitor, watching tens of couriers live and reacting fast to late, failed or unassigned orders.
- **Small business owners (SMEs):** sign in a few times a day, often from a laptop or phone, to add orders, assign a courier and check results.
- **Couriers:** on the street, one hand on the phone, outdoors in sunlight, moving between stops; they need the next stop, navigation, and quick status/proof capture.
- **Recipients (end customers):** open a tracking link from an SMS on their phone, with no account, to see where the order is and rate the delivery.
- **Integrators:** developers connecting shops and ERPs through the REST API and webhooks.

The panel must serve both the dense dispatcher scenario and the occasional SME user.

## Product Purpose

OmniDLV is a multi-tenant delivery operations platform: order intake (panel, API, CSV/XLSX), route planning and optimisation, courier assignment, live tracking, proof of delivery, recipient notifications and tracking page, feedback, and analytics. Success means orders reach recipients on time with less dispatcher effort and fewer failed or cancelled deliveries.

## Positioning

Feature parity with QDelivery (qdelivery.app) and a drop-in compatible integration API (same paths and payloads under `/api/integration`), built for the Azerbaijani market first: Azerbaijani-first UI, AZN, Baku addresses and phone formats, self-hosted OSM routing that covers Azerbaijan.

## Operating Context

- Markets: Azerbaijan first, then the region (Turkish- and Russian-speaking markets).
- Languages: az (default), en, ru, tr. Currency default AZN; timezone default Asia/Baku; timestamps stored in UTC.
- Pricing model mirrors QDelivery: per courier per month, plans Start / Scale / Enterprise, 14-day trial.
- Dispatcher workflow: import or receive orders, group into time slots, close a slot to run route optimisation, assign or let couriers claim from a pool, monitor live, handle exceptions.
- Courier workflow: start shift, receive tasks, navigate, change status, capture POD photo / signature / SMS delivery code, scan barcodes.

## Capabilities and Constraints

- Task statuses sit in eight fixed categories: created, pool, assigned, in_progress, on_delivery, completed, cancelled, failed; companies may add sub-statuses.
- Assignment strategies: pool, capacity_aware, capacity_unaware, slot, selected_courier.
- Webhooks: task_status_changed, courier_location_updated, courier_working_status_updated (HMAC signed).
- iOS PWAs cannot report GPS in the background; courier tracking works while the app is in the foreground.
- Undecided: SMS provider, payment provider, production domain.

## Brand Commitments

Name: **OmniDLV**. No logo, colours or typeface exist yet; the visual identity is open.

## Evidence on Hand

No customers, testimonials, case studies, metrics or press exist. Do not fabricate any; QDelivery's marketing claims are theirs, not ours.

## Product Principles

1. The dispatcher should see what needs attention first: late, failed, unassigned and unresolved orders surface before everything else.
2. One payload everywhere: panel, API and import accept the same task shape, so behaviour never differs by channel.
3. Built for Azerbaijani reality first: language, addresses, phone numbers, currency and maps.
4. Recipients get transparency without exposure: masked personal data, courier location only while en route.
5. Works for both the busy control room and the occasional small-shop user without separate products.

## Accessibility & Inclusion

The courier app must be usable one-handed outdoors in bright light: large touch targets and high contrast. The panel targets WCAG 2.1 AA.
