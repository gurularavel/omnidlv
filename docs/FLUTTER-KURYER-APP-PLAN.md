# OmniDLV Kuryer: Flutter native tətbiq planı

Tarix: 2026-10-21. Məqsəd: hazırkı kuryer PWA-nı App Store və Google Play-də yayımlanan native Flutter tətbiqi ilə əvəz etmək. Əsas səbəb **arxa fonda GPS**dir: PWA ekran sönəndə və ya kuryer Waze-ə keçəndə mövqe göndərmir, native tətbiq isə göndərir.

Backend (Laravel) dəyişmir, sadəcə mobil tətbiq üçün token əsaslı API əlavə olunur. Kuryerin bütün iş qaydaları (status keçidləri, tutum, təhvil kodu, POD) artıq `CourierWorkService`-dədir. Tətbiq yalnız onu çağırır, iş məntiqi təkrarlanmır.

---

## 1. Sizdən lazım olan qərarlar və hesablar

İşə başlamazdan əvvəl lazımdır. Bəziləri həftələr çəkir, ona görə paralel başlamaq lazımdır.

| # | Nə | Tövsiyə | Niyə vacibdir |
|---|---|---|---|
| 1 | **Apple Developer Program** | Şirkət (Organization) hesabı, $99/il | Fərdi hesabla yükləsəniz, tətbiqdə satıcı kimi şəxsin adı görünür. Şirkət hesabı üçün **D-U-N-S nömrəsi** lazımdır: pulsuzdur, amma alınması 1–2 həftə çəkə bilər. **İlk iş budur.** |
| 2 | **Google Play Console** | Şirkət hesabı, $25 birdəfəlik, D-U-N-S ilə | Fərdi Play hesabında yeni tətbiq istehsala çıxmazdan əvvəl **12 testerlə 14 gün qapalı test** tələb olunur. Şirkət hesabında bu tələb yoxdur. |
| 3 | Arxa fon GPS kitabxanası | **Transistorsoft `flutter_background_geolocation`** (Android üçün pullu lisenziya; qiyməti onların saytında yoxlanmalıdır) | Hərəkət aşkarlanması, batareya qənaəti, telefon istehsalçılarının tətbiqi "öldürməsinə" qarşı ən yetkin həll. Pulsuz alternativ: `geolocator` + öz foreground service-imiz. İşləyir, amma Xiaomi/Samsung-da əlavə iş və test tələb edir. |
| 4 | Push bildirişlər | **Firebase Cloud Messaging** (pulsuz) | "Yeni tapşırıq", "tapşırıq ləğv olundu". iOS-a da FCM üzərindən gedir, APNs açarı lazımdır. |
| 5 | Xəritə | **flutter_map + OpenStreetMap** (veb ilə eyni) | Google Maps SDK açar və billinq tələb edir. Kuryerin naviqasiyası onsuz da Google Maps / Waze tətbiqinə ötürülür. |
| 6 | Ayrıca repo | `gurularavel/omnidlv-courier` (boş repo yaradın) | Flutter kodu Laravel repo-sundan ayrı saxlanılır. |
| 7 | Tətbiqin adı və identifikatoru | "OmniDLV Kuryer", `az.omnidlv.courier` | Mağazada dəyişmək çətindir, ona görə əvvəlcədən seçilməlidir. |
| 8 | Domen + HTTPS | `app.<domen>` prod-da | Tətbiq yalnız HTTPS API ilə danışacaq. Mağaza üçün məxfilik siyasəti URL-i də lazımdır. |

---

## 2. Arxitektura

```
Flutter tətbiq (iOS + Android)
  ├─ Auth: telefon + OTP → Sanctum token (secure storage)
  ├─ Lokal DB (drift/SQLite): tapşırıqlar keşi + oflayn əməliyyat növbəsi + GPS buferi
  ├─ Arxa fon GPS servisi → partiyalarla /api/courier/v1/locations
  ├─ FCM → push → məlumatı yenilə
  └─ Naviqasiya: Google Maps / Waze (seçim yadda qalır, PWA-dakı kimi)
            │ HTTPS, Bearer token
            ▼
Laravel /api/courier/v1/*  →  CourierWorkService / CourierPresenter (mövcud)
            │
            ├─ Reverb → dispetçer paneli (canlı xəritə)
            └─ Webhooklar → müştəri sistemləri
```

**Flutter stack:**

| Sahə | Paket |
|---|---|
| Versiya | Flutter stable (3.x), Dart 3 |
| State | Riverpod |
| Routing | go_router |
| HTTP | dio (token, retry, `X-Action-Id` interceptor-ları) |
| Lokal DB | drift (SQLite) |
| Token | flutter_secure_storage |
| GPS | flutter_background_geolocation (və ya geolocator + flutter_foreground_task) |
| Kamera / foto | image_picker + flutter_image_compress |
| İmza | signature |
| Barkod | mobile_scanner |
| Push | firebase_messaging |
| Xəta izləmə | Sentry (və ya Firebase Crashlytics) |
| Dillər | flutter_localizations, ARB faylları: az / en / ru / tr |
| Xarici linklər | url_launcher (zəng, Google Maps, Waze) |

**Mühitlər (flavors):** `dev` (lokal), `staging`, `prod`. Hər birinin öz API URL-i və öz Firebase layihəsi olur. Build-lər CI-da hazırlanır: GitHub Actions + Fastlane (TestFlight və Play internal track-ə avtomatik yükləmə).

---

## 3. Backend dəyişiklikləri (Laravel, bu repo)

Yeni prefix: `/api/courier/v1`, Sanctum personal access token (`User` modelinə `HasApiTokens`), tenant token-dan təyin olunur.

| Endpoint | Təsvir |
|---|---|
| `POST /auth/otp` | Telefon → OTP SMS (mövcud `OtpService`) |
| `POST /auth/verify` | Telefon + kod + cihaz adı → `{ token, courier }` |
| `POST /auth/logout` | Token-i ləğv edir |
| `GET /me` | Kuryer, şirkət, növbə, tətbiq ayarları |
| `GET /board` | Ana ekran: növbəti dayanacaq, aktiv / pool / bitmiş tapşırıqlar (mövcud `CourierPresenter::board`) |
| `POST /shift/start`, `/shift/end` | Növbə |
| `POST /locations` | **Partiya**: `[{lat, lng, accuracy, speed, heading, battery, is_mock, recorded_at}]`, 50-yə qədər nöqtə |
| `GET /tasks/{id}` | Tapşırıq detalları |
| `POST /tasks/{id}/claim`, `/step`, `/complete`, `/fail`, `/code`, `/scan` | Mövcud əməliyyatlar, `X-Action-Id` ilə idempotent |
| `POST /tasks/{id}/pods` | Foto / imza yükləmə (multipart) |
| `POST /devices` | FCM token qeydiyyatı (platforma, tətbiq versiyası) |
| `GET /app-config` | `min_version`, `latest_version`, GPS intervalları. Köhnə versiyanı yeniləməyə məcbur etmək üçün |

Əlavə backend işləri:
- **Mövqe partiyaları.** Telefon oflayn olanda toplanan nöqtələr sonradan göndərilir. Server `recorded_at`-a görə sıralayır, köhnə nöqtə "son mövqe"ni əvəz etmir. Saxta GPS (`is_mock`) panelə işarə kimi düşür.
- **"Son görülmə".** Paneldə 2 dəqiqədən çox mövqe gəlməyibsə, kuryer "siqnal yoxdur" kimi göstərilir. Operator köhnə mövqeyə aldanmır.
- **Push göndərmə.** Tapşırıq təyin olunanda, ləğv olunanda və ya dəyişəndə FCM ilə göndərilir (queue ilə).
- **App Review hesabı.** Apple və Google rəyçiləri SMS ala bilmir. `.env`-də göstərilən bir test nömrəsi sabit OTP ilə girir, yalnız ayrıca demo şirkətində. Prod məlumatına çıxışı olmur.
- **Hesabın silinməsi.** Mağaza siyasətinə görə tətbiqdə "Hesabımı silmək istəyirəm" sorğusu və veb səhifə olmalıdır.
- **Məxfilik siyasəti səhifəsi.** Hansı məlumat yığılır: məkan, foto, telefon. Niyə və nə qədər saxlanılır.
- Testlər: hər endpoint üçün feature test + tenant izolyasiyası.

PWA bir müddət ehtiyat kimi qalır. Hər iki kanal eyni servisi çağırır, ona görə keçid dövründə qarışıqlıq yaranmır.

---

## 4. Arxa fon GPS: necə işləyəcək

| Vəziyyət | Davranış |
|---|---|
| Növbə bağlıdır | GPS **tam söndürülür**. Bu, həm məxfilik, həm də mağaza qaydası tələbidir. |
| Növbədə, tapşırıq yoxdur | Hər 60 saniyədə və ya 100 m hərəkətdə bir nöqtə |
| Tapşırıq yoldadır | Hər 10–15 saniyədə və ya 30 m-də bir nöqtə |
| Hərəkətsiz (5+ dəq) | Hərəkət sensoru ilə yatır, batareya qənaət edilir |
| İnternet yoxdur | Nöqtələr SQLite-da toplanır, internet qayıdanda partiya ilə göndərilir |

**Android:**
- Foreground service işləyir: bildiriş zolağında "OmniDLV: növbədəsiniz" görünür. Bu, Android 14-ün `FOREGROUND_SERVICE_LOCATION` tələbidir.
- `ACCESS_BACKGROUND_LOCATION` icazəsi istənməzdən əvvəl tətbiqdə **açıq izah ekranı** göstərilir ("prominent disclosure"). Google bunu tələb edir.
- Xiaomi, Huawei, Samsung kimi istehsalçılar fon tətbiqlərini öldürür. Tətbiqdə "batareya optimallaşdırmasını söndürün" bələdçisi olmalıdır (dontkillmyapp.com siyahısına görə).

**iOS:**
- `UIBackgroundModes: location` qoşulur, icazə "Always" olmalıdır. iOS icazəni iki addımda verir: əvvəl "While Using", sonra "Always".
- Info.plist-də izah mətnləri 4 dildə yazılır. Apple rəyçisi bu mətnləri oxuyur.

---

## 5. Ekranlar (PWA-dakı dizaynın native köçürməsi)

Dizayn PWA-dakı "porcelain" kuryer dili ilə qalır: böyük vaxt rəqəmi, BÖYÜK HƏRFLƏ ünvan, dayanacaq xətti. Fira Sans şriftləri tətbiqə daxil edilir.

1. **Giriş:** telefon (yalnız rəqəm, avtomatik format) → OTP (SMS kodu avtomatik oxunur: iOS one-time-code, Android SMS Retriever).
2. **İcazələr bələdçisi:** məkan → arxa fon məkanı → bildirişlər → batareya optimallaşdırması.
3. **Ana ekran:** növbəti dayanacaq, xəritə zolağı, Zəng / Naviqasiya, əsas düymə, dayanacaq xətti, "Günüm" çəkməcəsi.
4. **Tapşırıq:** tələblər, məhsullar, barkod, foto, imza, SMS kodu, təhvil / çatdırılmadı. Təhvil 2 toxunuşla edilir (bu gün düzəltdiyimiz qaydaya uyğun).
5. **Pool:** götürülə bilən tapşırıqlar.
6. **Menyu:** növbəni bitir, dil, naviqasiya tətbiqi, hesabın silinməsi, versiya.
7. **Oflayn zolaq:** "İnternet yoxdur, N əməliyyat göndəriləcək".
8. **Məcburi yeniləmə ekranı:** versiya `min_version`-dan aşağıdırsa göstərilir.

---

## 6. Mərhələlər və müddət (1 Flutter proqramçı + backend dəstəyi)

| Həftə | İş | Nəticə |
|---|---|---|
| 0 (paralel) | Apple/Google hesabları, D-U-N-S, Firebase, repo, ad / ikon | Hesablar hazırdır |
| 1 | Backend: `/api/courier/v1`, token auth, partiya mövqe, cihazlar, app-config, review hesabı + testlər | API hazırdır, testlər keçir |
| 1–2 | Flutter skeleti: flavors, tema, i18n, dio, drift, CI, OTP girişi | TestFlight və Play internal-da ilk build |
| 2–4 | Əsas axın: ana ekran, tapşırıq, addımlar, təhvil / uğursuz, POD foto / imza / kod / barkod, oflayn növbə, naviqasiya seçimi | Kuryer tam gün işləyə bilir (ön planda) |
| 4–5 | Arxa fon GPS, icazə bələdçisi, batareya rejimləri, paneldə "son görülmə" | Ekran sönəndə və Waze-də mövqe gəlir |
| 5 | Push bildirişlər | Yeni tapşırıq bildirişi |
| 6–7 | Real cihaz testi (iPhone + 3–4 fərqli Android: Samsung, Xiaomi, köhnə model), real kuryerlərlə beta | Xəta siyahısı bağlanıb |
| 7–8 | Mağaza materialları, Google-a fon məkanı videosu, Apple-a rəy qeydləri, göndərmə | Hər iki mağazada yayımdadır |

Cəmi təxminən **7–9 həftə**. Apple rəyi adətən 1–3 gün çəkir, ilk dəfə rədd edilə bilər. Google-da fon məkanı icazəsinin yoxlanması bir neçə gündən bir həftəyə qədər çəkə bilər.

---

## 7. Mağaza tələbləri (rədd olunmamaq üçün)

**Hər ikisi üçün:**
- Məxfilik siyasəti URL-i.
- Test hesabı: telefon + sabit kod, demo şirkətində.
- Ekran şəkilləri (telefon ölçüləri), qısa və uzun təsvir 4 dildə.
- Tətbiqdə hesab silmə sorğusu.

**Apple:**
- "Background location" niyə lazımdır, rəy qeydində yazılır: "kuryerin aktiv çatdırılması zamanı dispetçer və alıcı üçün canlı mövqe".
- App Privacy formu: Location (Precise), Photos, Phone Number, Name. Məlumat istifadəçiyə bağlıdır, izləmə (tracking) üçün istifadə olunmur.
- Tətbiq girişdən sonra açılır. Bu qəbul olunandır, bir şərtlə ki rəyçiyə demo hesab verilsin.

**Google Play:**
- **Location permissions declaration** formu, fon məkanından istifadəni göstərən 30 saniyəlik **video**. İzah ekranı → icazə → növbə → ekran sönülü vəziyyətdə paneldə mövqe.
- Foreground service (location) bəyannaməsi.
- Data safety formu.
- Target API son tələb olunan səviyyədə olmalıdır.

---

## 8. Risklər

| Risk | Nə edirik |
|---|---|
| D-U-N-S / hesab təsdiqi gecikir | İlk gün başlanır. Bu müddətdə TestFlight və Play internal-da işləyirik. |
| Android istehsalçıları fon servisini öldürür | Transistorsoft + batareya bələdçisi + paneldə "siqnal yoxdur" |
| Apple "Always" icazəsini sual altına alır | Dəqiq izah mətni; GPS yalnız növbədə işləyir, növbə bağlananda dayanır |
| Batareya şikayətləri | Adaptiv interval, hərəkətsizlikdə yuxu rejimi, növbədən kənarda sıfır |
| Saxta GPS tətbiqləri | `is_mock` bayrağı paneldə görünür |
| Köhnə tətbiq versiyaları API dəyişəndə sınır | `/v1` versiyalı API + `min_version` ilə məcburi yeniləmə |
| İnternet kəsilir | Oflayn növbə və GPS buferi (SQLite), `X-Action-Id` ilə təkrarsız göndərmə |

---

## 9. İlk addımlar (bu həftə)

1. **Siz:** Apple və Google şirkət hesabları üçün D-U-N-S müraciəti, GitHub-da `omnidlv-courier` repo, tətbiq adının təsdiqi.
2. **Siz:** GPS kitabxanası seçimi: Transistorsoft (pullu, tövsiyə) və ya pulsuz stack.
3. **Mən:** backend `/api/courier/v1` (token auth, partiya mövqe, cihazlar, app-config, review hesabı) + testlər. Bu, hesabları gözləmədən başlaya bilər.
4. **Mən:** Flutter layihəsinin skeleti, OTP girişi və ana ekran. Lokalda Android emulyatorda işləyəcək. iOS build-i üçün Mac lazımdır (və ya CI-da macOS runner).

> Qeyd: iOS tətbiqini yığmaq və App Store-a göndərmək üçün **macOS + Xcode** lazımdır. Bu Windows maşınında yalnız Android build olur. Variantlar: komandada Mac, GitHub Actions macOS runner (Fastlane ilə) və ya Codemagic kimi bulud CI.
