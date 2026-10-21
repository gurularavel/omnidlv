# OmniDLV — DevOps tapşırıqları

Yoxlanılıb: 2026-10-20. Layihə: `C:\lara\www\omnidlv` (Laravel 13.38, PostgreSQL, Reverb, OSRM + VROOM).

---

## 1. DevOps-a göndəriləcək mətn (kopyala-yapışdır)

> Salam. Yeni çatdırılma platforması (OmniDLV) üçün aşağıdakılar lazımdır:
>
> **1. Təcili — şəbəkə çıxışı.** Bizim şəbəkədən (xarici IP `95.86.162.13`; `t1007-mgmt` və `t1007-web01` / `10.10.17.1`) PHP paket reyestri Packagist-ə HTTPS qoşulma timeout olur. GitHub, npm və getcomposer.org isə açılır. Zəhmət olmasa 443 portunda çıxışı açın:
> - `repo.packagist.org` (hazırda `169.150.247.39`, `138.199.37.225`)
> - `packagist.org` (hazırda `138.199.37.232`)
>
> Bunlar CDN (bunny.net) IP-ləridir və dəyişə bilər, ona görə qaydanı IP ilə yox, domen (SNI/FQDN) ilə yazmaq daha düzgündür.
>
> Yoxlama: `curl -I https://repo.packagist.org/packages.json` → `200`.
>
> **2. Routing serveri (VM).** Marşrut optimallaşdırması üçün OSRM + VROOM Docker-də işləyəcək. `t1007-web01`-də buna yer yoxdur: 3.8 GB RAM-ın təxminən 1.4 GB-ı boşdur, 2 CPU var, Docker yoxdur. Ayrıca daxili VM istəyirik:
> - Ubuntu 24.04, 4 vCPU, 8 GB RAM, 60 GB SSD, Docker Engine + compose plugin.
> - Yalnız daxili şəbəkədən `t1007-web01`-dən əlçatan olsun, portlar `5000/tcp` (OSRM) və `3000/tcp` (VROOM). İnternetə açılmasın.
> - Çıxış: `download.geofabrik.de` (aylıq xəritə yeniləməsi), `ghcr.io` və `registry-1.docker.io` (image-lər).
> - SSH girişi `min_admin` istifadəçisi üçün mövcud açarla (`jezahost_rsa`).
>
> Əgər ayrıca VM mümkün deyilsə: `t1007-web01`-in RAM-ını ən azı 8 GB-a qaldırmaq və Docker quraşdırmaq.
>
> **3. `t1007-web01`-də paketlər:**
> - `postgresql-16-postgis-3` (PostgreSQL 16 üçün PostGIS; hazırda yalnız `vector` var).
> - PHP 8.3 üçün `redis` extension (FASTPANEL-in PHP 8.3-ü üçün; hazırda Redis server işləyir, amma PHP extension yoxdur).
>
> **4. Domen və SSL.** Platforma üçün domen (məs. `omnidlv.az` və ya mövcud domenin subdomeni):
> - `app.<domen>` — panel, API və kuryer PWA.
> - `track.<domen>` — müştəri izləmə səhifəsi.
> - Gələcəkdə şirkətlərə xüsusi subdomenlər üçün wildcard `*.<domen>`.
>
> DNS A-qeydləri `95.86.162.13`-ə yönəlsin. SSL FASTPANEL ilə (Let's Encrypt) qurulsun; wildcard üçün DNS-01 lazımdır. Kuryer tətbiqi kamera və GPS istifadə etdiyi üçün HTTPS məcburidir.
>
> **5. WebSocket (Reverb).** Real-time izləmə üçün nginx-də `wss://app.<domen>/app` və `/apps` yolları `127.0.0.1:8082`-yə `Upgrade`/`Connection` başlıqları ilə proksi edilməlidir. Port `8081` artıq `redhopper-reverb` tərəfindən istifadə olunur.
>
> **6. SMS və e-poçt.** Müştəriyə status bildirişləri üçün SMS provayderinin API girişi (provayder adı, API sənədi, test hesabı, göndərən adı/alpha-name) və SMTP hesabı (`noreply@<domen>`) lazımdır.
>
> **7. Saxlama və backup.**
> - POD (çatdırılma sübutu) fotoları üçün S3-uyğun bucket (MinIO və ya xarici) — və ya ilk mərhələdə serverdə ~20 GB əlavə disk.
> - Gündəlik PostgreSQL dump + fotoların backup-ı, ən azı 14 gün saxlanma.
>
> Təşəkkürlər.

---

## 2. Ətraflı tapşırıq siyahısı (yoxlama ilə)

### 2.1 Packagist çıxışı — BLOKLAYICI

**Problem.**
- Bizim şəbəkədən `repo.packagist.org` və `packagist.org`-a TCP 443 qoşulmaları timeout olur (`curl error 28`).
- DNS düzgün işləyir.
- `api.github.com`, `registry.npmjs.org` və `getcomposer.org` açılır.
- Problem hər iki maşında var: `t1007-mgmt` (lokal dev) və `t1007-web01` (prod), çünki ikisinin də xarici IP-si eynidir.

**Hazırkı müvəqqəti həll.** Composer metadata-sı `packagist.pages.dev` güzgüsündən götürüldü, paket arxivləri isə GitHub-dan endirildi. Güzgü konfiqurasiyası sonra `composer.json`-dan silindi. `composer.lock`-da güzgü ünvanı yoxdur.

**Prod-da `composer install`.** Lock fayldan quraşdırma packagist tələb etmir, arxivlər GitHub-dan gəlir. Amma yeni paket əlavə etmək və ya `composer update` üçün packagist açılmalıdır.

**Yoxlama:**
```bash
curl -s -o /dev/null -w "%{http_code}\n" https://repo.packagist.org/packages.json   # 200
ssh t1007 'curl -s -o /dev/null -w "%{http_code}\n" https://repo.packagist.org/packages.json'
```

**Müvəqqəti həllin təkrarlanması** (packagist hələ də bağlıdırsa və yeni paket lazımdırsa):
```bash
mkdir -p /tmp/pagesdev && printf '%s' '{"packages":[],"metadata-url":"https://packagist.pages.dev/p2/%package%.json"}' > /tmp/pagesdev/packages.json
composer config repositories.pagesdev composer file:///tmp/pagesdev
composer config repositories.packagist.org false
composer require <paket> --no-audit
composer config --unset repositories.pagesdev && composer config --unset repositories.packagist.org
grep -c 'pages.dev' composer.lock   # 0 olmalıdır
```

### 2.2 Routing VM (OSRM + VROOM)

**Ölçülər.**
- Azərbaycan OSM extract (~50 MB `.pbf`): OSRM hazırlığı ~1.5–2 GB RAM tələb edir, işləmə ~0.5–1 GB.
- Türkiyə də əlavə olunarsa (~600 MB `.pbf`): hazırlıq 10–12 GB RAM tələb edir. Bu halda VM 16 GB olmalıdır.
- VROOM: ~200–500 MB RAM; böyük slotlarda (500+ tapşırıq) CPU-nu çox istifadə edir.

**Quraşdırma (Docker-dən sonra bunu biz edəcəyik):**
```bash
mkdir -p /srv/osrm && cd /srv/osrm
wget https://download.geofabrik.de/asia/azerbaijan-latest.osm.pbf
docker run -t -v $PWD:/data ghcr.io/project-osrm/osrm-backend osrm-extract -p /opt/car.lua /data/azerbaijan-latest.osm.pbf
docker run -t -v $PWD:/data ghcr.io/project-osrm/osrm-backend osrm-partition /data/azerbaijan-latest.osrm
docker run -t -v $PWD:/data ghcr.io/project-osrm/osrm-backend osrm-customize /data/azerbaijan-latest.osrm
# xidmətlər: osrm-routed (MLD) :5000, vroom-docker :3000 (OSRM-ə baxır)
```

- Aylıq yeniləmə: cron ilə yeni extract yaradılır, sonra konteyner dəyişdirilir (blue/green, kəsintisiz).
- Firewall: `5000` və `3000` portları yalnız `10.10.17.1`-dən açıq olur.

**Tətbiqə qoşulma.** VM hazır olanda yalnız prod `.env`-ə bu sətirlər əlavə olunur (kod dəyişmir):
```
OSRM_CAR_URL=http://<vm-ip>:5000
VROOM_URL=http://<vm-ip>:3000
```
VM olmayana və ya cavab vermədiyi müddətdə tətbiq dayanmır: məsafəni düz xətt üzərindən təxmin edir, marşrutları daxili planlayıcı qurur. Panel bunu "düz xətt təxmini" kimi göstərir.

### 2.3 `t1007-web01` paketləri

| Nə | Niyə | Komanda | Məcburidir? |
|---|---|---|---|
| PostGIS 3 (PG 16) | Komanda poliqonları və geo sorğular; böyük həcmdə sürət | `sudo apt install postgresql-16-postgis-3`, sonra DB-də `CREATE EXTENSION postgis;` | Xeyr. PostGIS olmasa PHP fallback var, amma böyük həcmdə yavaşdır |
| phpredis (PHP 8.3) | Queue, cache və Reverb scaling Redis üzərindən | FASTPANEL → PHP 8.3 → Extensions → `redis`, və ya `pecl` | Tövsiyə olunur |
| Supervisor proqramları | `omnidlv-queue` (`php artisan queue:work --queue=default,webhooks`: webhooklar ayrıca `webhooks` növbəsindədir, yavaş qəbuledici SMS-ləri gecikdirməsin), `omnidlv-reverb` (:8082) | `/etc/supervisor/conf.d/omnidlv-*.conf` (biz hazırlayacağıq) | Bəli |
| Cron | `php artisan schedule:run` hər dəqiqə (sayt istifadəçisi ilə) | FASTPANEL → Cron | Bəli |

### 2.4 Domen, SSL, nginx

- FASTPANEL-də sayt kartı: sahibi yeni site user, PHP 8.3, document root `.../public`.
- Reverb WebSocket proksisi (FASTPANEL "custom nginx config" bölməsində):
  ```nginx
  location ~ ^/(app|apps)/ {
      proxy_pass http://127.0.0.1:8082;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "Upgrade";
      proxy_set_header Host $host;
      proxy_read_timeout 60s;
  }
  ```
- `client_max_body_size 20m` — POD fotoları və CSV/XLSX import üçün.
- White-label izləmə üçün wildcard sertifikat (DNS-01 challenge).

### 2.5 Runtime-da çıxış lazım olan xidmətlər

| Hədəf | Kimdən | Niyə |
|---|---|---|
| `tile.openstreetmap.org` (və ya seçilən tile provayderi) | İstifadəçi brauzeri | Xəritə plitələri. Kommersiya yükündə OSM tile siyasətinə görə öz tile server və ya ödənişli provayder lazım ola bilər |
| `nominatim.openstreetmap.org` | Server | Geocoding (limit: 1 sorğu/san). Böyük həcmdə self-host Nominatim (əlavə 4–8 GB RAM) və ya ödənişli geocoder |
| `download.geofabrik.de` | Routing VM | Aylıq OSM yeniləməsi |
| SMS provayderinin API-si | Server | Status SMS-ləri |
| SMTP | Server | E-poçt bildirişləri |
| Müştərilərin webhook URL-ləri | Server | `task_status_changed` və s. (ixtiyari xarici HTTPS). Daxili/LAN ünvanlara göndərmə kodda bloklanır (SSRF qoruması); prod `.env`-də `WEBHOOKS_ALLOW_PRIVATE_NETWORKS` və `WEBHOOKS_ALLOW_HTTP` yazılmamalıdır |

### 2.6 Monitorinq və backup

- Gündəlik `pg_dump` (sıxılmış) → `~/backups` və serverdən kənar nüsxə, 14+ gün saxlanma.
- POD fotoları: S3 bucket versioning və ya gündəlik rsync.
- Healthcheck: `https://app.<domen>/up` (Laravel), OSRM `/route/v1/driving/...`, VROOM `/health`.
- Disk xəbərdarlığı: `t1007-web01`-də 13 GB boş yer qalıb. Kuryer GPS tarixçəsi gündə ~0.5–1 GB yarada bilər, ona görə 30–90 gün sonra avtomatik təmizlənəcək.

---

## 3. Status

| # | Tapşırıq | Status |
|---|---|---|
| 1 | Packagist çıxışı | ⏳ DevOps |
| 2 | Routing VM (4 vCPU / 8 GB / Docker) | ⏳ DevOps |
| 3 | PostGIS + phpredis (`t1007-web01`) | ⏳ DevOps |
| 4 | Domen + SSL (+ wildcard) | ⏳ Domen adı seçilməlidir |
| 5 | Reverb WebSocket nginx proksisi | ⏳ Sayt kartından sonra |
| 6 | SMS provayderi + SMTP | ⏳ Biznes qərarı |
| 7 | S3 / backup | ⏳ DevOps |
