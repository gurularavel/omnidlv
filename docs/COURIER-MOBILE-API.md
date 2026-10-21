# Courier mobile API (`/api/courier/v1`)

Native courier app (Flutter, repo `gurularavel/omnidlv-courier`). JSON in and out, `Accept: application/json`, `Accept-Language: az|en|ru|tr` picks the language of messages and status names.

## Auth

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/app-config` | – | `{min_version, latest_version, store_urls{android,ios}, location{on_task{interval,distance}, idle{…}, batch_size}, privacy_url, support_phone, locales[]}` |
| POST | `/auth/otp` | `{phone}` | `{sent: true, expires_in: 300, demo_code?}`. Same answer for unknown numbers. `demo_code` only while SMS runs in demo mode. Limit: 3/min per number, 20/h per IP. |
| POST | `/auth/verify` | `{phone, code (6 digits), device_name?}` | `{token, expires_at, courier}`. 422 `errors.code` on a wrong/expired code (5 tries). |
| POST | `/auth/logout` | – | 204. Revokes the token, removes the device's push token. |

Every other call: `Authorization: Bearer <token>`. A 401 means sign in again; a 403 means the courier was deactivated.

**Store reviewers:** when `COURIER_REVIEW_PHONE` + `COURIER_REVIEW_CODE` are set, that number signs in with that code and no SMS is sent.

## Account

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/me` | – | `{courier{id,name,phone,phone_display,locale,ink}, company{id,name,timezone,primary_color}, shift{active,started_at}}` |
| PATCH | `/me/locale` | `{locale}` | `{courier}` |
| POST | `/devices` | `{platform: android\|ios, push_token?, app_version?, os_version?, model?, locale?}` | `{registered: true}`. Call after sign-in and whenever FCM rotates the token. |
| POST | `/account/deletion-request` | `{reason?}` | 202 `{message}` |

## Work

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/board` | – | Home screen (see below) |
| POST | `/shift/start` | `{lat?, lng?}` | board |
| POST | `/shift/end` | – | board. 422 `code: shift_has_tasks` while tasks are open. |
| POST | `/locations` | `{locations: [{lat, lng, recorded_at (ISO 8601), accuracy?, speed?, heading?, battery?, is_mock?}]}` (≤ 200) | `{accepted, stored}`. Send in batches; offline fixes may arrive late. Fixes older than 24 h are dropped. -1 for speed/heading means unknown. |
| GET | `/tasks/{id}` | – | task (see below). 404 if it is not this courier's. |
| POST | `/tasks/{id}/claim` | – | task. 409/422 if someone else took it. |
| POST | `/tasks/{id}/step` | `{action: pickup\|depart\|arrive, notes?, lat?, lng?}` | task |
| POST | `/tasks/{id}/complete` | `{status: delivered\|left_at_door\|delivered_to_other, received_by?, notes?, lat?, lng?}` | task. 422 `photo_required` / `code_required`. |
| POST | `/tasks/{id}/fail` | `{status: recipient_not_available\|wrong_address\|refused\|damaged\|failed, notes?, lat?, lng?}` | task |
| POST | `/tasks/{id}/code` | `{code}` | `{verified, attempts_left, task}` (422 when wrong) |
| POST | `/tasks/{id}/scan` | `{code}` | `{matched, item, task}` (404 when not in the order) |
| POST | `/tasks/{id}/pods` | multipart `type=photo\|signature`, `file` (jpg/png/webp ≤ 10 MB) | task |

**Retries.** Send `X-Action-Id: <uuid v4>` with every POST that changes state. Sending the same id again returns the current state with `replayed: true` instead of acting twice, so a queued offline action can be replayed safely.

**Errors.** Business rule refusals are 422 `{message, code}` (`code` is stable, `message` is translated). Validation errors are 422 `{message, errors{field: [..]}}`.

## Shapes

**board**
```
{ courier{id,name,ink}, company, shift{active,started_at,started_label}, last_seen_at, position{lat,lng}|null,
  next: task|null,               // the stop the home screen is built around
  active: [summary], pool: [summary], done: [summary],
  counts{active,pool,delivered,failed,cash} }
```

**summary**
```
{ id, code, type (drop_off|pickup), category, status, status_form, name, address, lat, lng,
  minutes, minutes_label, minutes_in_hours, urgency (ok|risk|late), window, cash, currency, locked, url }
```

**task** = summary +
```
{ status_slug, starts_at, ends_at, hub, target{name,address,building,floor,apartment,description,phone,lat,lng}, from{…},
  phone, phone_display, notes, recipient_notes, quantity,
  requirements{photo,photo_mandatory,code,code_mandatory,dont_ring,contactless},
  code_verified, code_attempts_left, items[{id,name,sku,barcode,quantity,scanned}], pods[{id,type,url}], has_photo,
  next: claim|pickup|depart|arrive|complete|null, can_complete, can_fail,
  navigate{google,waze}|null, final }
```

## Push (FCM)

Data payload: `{type: task_assigned|task_cancelled, task_id, tracking_code}`. Until Firebase is configured the server uses the `log` push driver.
