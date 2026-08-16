# Vacheron Constantin 재고 감시

일본 시계 판매 사이트의 Vacheron Constantin 현재 판매 재고를 주기적으로 수집하고, 직전 성공 스냅샷과 비교해 신규 상품이 있을 때만 Slack Incoming Webhook으로 알리는 PHP 8 기반 모니터링 시스템입니다.

관리 페이지 URL:

```text
http://localhost:8080/
```

현재 운영 관리 페이지 URL:

```text
https://aeogae.com/watch/
```

현재 운영 스크레이핑 실행 URL:

```text
https://aeogae.com/watch/scrape.php
```

30분 제한을 무시하고 멈춘 RUNNING 실행까지 정리한 뒤 다시 실행하는 URL:

```text
https://aeogae.com/watch/scrape.php?force=1
```

스크레이핑 원인 확인/진단 URL:

```text
https://aeogae.com/watch/diagnostics.php
```

브라우저에서 `Bad Request`가 보이면 localhost에 남은 쿠키 영향일 수 있으므로 아래 URL도 사용할 수 있습니다.

```text
http://127.0.0.1:8080/
```

앱 내부 scheduler는 없습니다. 운영 스케줄은 외부 `crontab`에서 `/scrape.php`를 호출하는 방식으로 구성합니다.

## 요구 환경

- Docker
- Docker Compose

컨테이너 내부는 PHP 8.3, Apache, Composer, SQLite, PDO_SQLITE로 실행됩니다.

## 설치

```bash
git clone <repo-url>
cd scraping-watch
cp .env.example .env
docker compose up -d --build
```

관리 화면:

```text
http://localhost:8080/
```

SQLite DB와 로그는 host의 `storage/`에 남습니다.

```text
storage/database.sqlite
storage/logs/app.log
storage/debug/
```

## Lolipop 배포 매뉴얼

Lolipop 같은 렌탈서버에서는 Docker Compose를 사용하지 않습니다. PHP 파일과 `vendor/`를 업로드해서 Apache/PHP 환경에서 직접 실행합니다.

참고 공식 문서:

- PHP 버전 변경: https://lolipop.jp/manual/user/php-setting/
- SSH 설정: https://lolipop.jp/manual/user/ssh/
- cron 설정: https://lolipop.jp/manual/user/cron/

### 1. 사전 조건

- PHP 8.1 이상으로 설정
- PDO SQLite 사용 가능 여부 확인
- SSH 사용 가능 플랜 권장
- Composer를 서버에서 실행하거나, 로컬에서 `composer install` 후 `vendor/`까지 업로드

Lolipop 공식 cron 문서 기준으로 cron 등록 수와 최소 실행 간격은 플랜별로 다릅니다. 또한 PHP cron은 cron 설정 시점의 Lolipop 도메인 PHP 버전을 사용하므로, PHP 버전을 바꾼 뒤에는 cron을 다시 등록해야 합니다.

### 2. 로컬에서 배포 패키지 준비

```bash
./scripts/build-dist.sh
```

생성 결과:

```text
dist/watch/                 <- 업로드할 폴더
dist/watch-lolipop.tar.gz   <- 압축 업로드용 파일
```

중요: GitHub에서 받은 소스만 서버에 올리면 `vendor/`가 없어서 실행되지 않습니다. 반드시 `./scripts/build-dist.sh`로 만든 `dist/watch/` 전체를 올리거나, 서버에서 `composer install --no-dev --optimize-autoloader`를 실행해야 합니다.

스크립트는 `public/`, `src/`, `config/`, `vendor/`, `.htaccess`, 루트 `index.php`, `bootstrap.php`, `composer.json`, `.env.example`, 빈 `storage/` 구조를 `dist/watch/`에 배치합니다. `storage/database.sqlite`, 로그, lock 파일, tests, Docker 파일은 배포물에 포함하지 않습니다.

로컬에 Composer가 있으면 `dist/watch/vendor`를 production dependency로 설치합니다. Composer가 없으면 Docker 이미지에서 `vendor/`와 `composer.lock`을 추출합니다.

`.env` 예:

```env
APP_ENV=production
APP_TIMEZONE=Asia/Tokyo
APP_HOST=YOUR_DOMAIN
APP_BASE_PATH=/watch
DEBUG_MODE=false
DB_PATH=/home/users/X/lolipop.jp-ACCOUNT/web/watch/storage/database.sqlite
SCRAPE_TOKEN=긴_랜덤_문자열
SCRAPE_MIN_INTERVAL_MINUTES=30
SCRAPE_STALE_AFTER_MINUTES=10
FIRST_RUN_NOTIFY=false
SLACK_WEBHOOK_URL=
HTTP_USER_AGENT=WatchInventoryMonitor/1.0
HTTP_CONNECT_TIMEOUT=10
HTTP_TIMEOUT=30
```

`DB_PATH`는 반드시 Lolipop의 실제 절대 경로로 바꿉니다. 사용자 전용 페이지의アカウント情報 또는 SSH에서 `pwd`로 확인합니다.

Docker 로컬 개발에서는 브라우저 접속 URL이 `http://localhost:8080/`이지만, 컨테이너 내부에서 `cron_scrape.php`를 CLI 실행할 때는 `APP_HOST=localhost`를 사용합니다.

### 3. 업로드 구조

`dist/watch` 기준 권장 구조:

```text
dist/watch/
├─ public/        <- 도메인 공개 폴더로 지정
├─ src/
├─ config/
├─ vendor/
├─ storage/
├─ .htaccess      <- /watch/ 요청을 public/으로 연결
├─ index.php      <- /watch/ fallback entrypoint
├─ assets.css     <- rewrite가 꺼져 있어도 CSS가 로드되게 하는 fallback
├─ watches.php    <- rewrite fallback
├─ runs.php       <- rewrite fallback
├─ run.php        <- rewrite fallback
├─ failures.php   <- rewrite fallback
├─ diagnostics.php <- rewrite fallback
├─ scrape.php     <- rewrite fallback
├─ bootstrap.php
├─ composer.json
└─ .env.example
```

Lolipop에는 `dist/watch/` 안의 파일들을 서버의 `web/watch/`로 업로드합니다. 서버에 업로드한 뒤 `.env.example`을 복사해서 `.env`를 만들고 운영 값을 입력합니다.

`https://YOUR_DOMAIN/watch/`로 접속하는 배포라면 Lolipop 공개 폴더를 기존 사이트 루트로 두고 `web/watch/` 폴더를 그대로 사용합니다. 루트 `.htaccess`가 `/watch/` 요청을 `public/`으로 연결합니다.

독自ドメイン 전체를 이 앱 전용으로 쓸 때만 Lolipop의公開フォルダ를 아래처럼 지정합니다.

```text
watch/public
```

관리 페이지 URL:

```text
https://YOUR_DOMAIN/watch/
```

`public/`만 공개되게 해야 `.env`, `storage/`, `src/`, `vendor/`가 웹에서 직접 노출되지 않습니다.

### 4. 서버에서 권한 설정

SSH 접속 후:

```bash
cd ~/web/watch
mkdir -p storage/logs storage/debug
chmod 700 storage
chmod 700 storage/logs storage/debug
```

SQLite 파일은 첫 접속 또는 첫 scrape 때 자동 생성됩니다.

### 5. 서버에서 Composer 실행하는 경우

SSH를 켠 뒤 접속합니다. Lolipop 공식 SSH 정보는 보통 다음 형태입니다.

```bash
ssh FTP_ACCOUNT@ssh.lolipop.jp -p 2222
```

서버에서:

```bash
cd ~/web/watch
php -v
composer install --no-dev --optimize-autoloader
```

`composer` 명령이 없으면 로컬에서 `vendor/`까지 만든 뒤 업로드하는 방식이 더 단순합니다.

### 6. 최초 동작 확인

브라우저:

```text
https://YOUR_DOMAIN/watch/
```

수동 scrape:

```bash
curl -fsS "https://YOUR_DOMAIN/watch/scrape.php?token=SCRAPE_TOKEN값"
```

30분 제한과 멈춘 RUNNING 실행을 무시하고 바로 재실행:

```bash
curl -fsS "https://YOUR_DOMAIN/watch/scrape.php?token=SCRAPE_TOKEN값&force=1"
```

진단 페이지:

```text
https://YOUR_DOMAIN/watch/diagnostics.php
```

정상 예:

```json
{"status":"SUCCESS 또는 PARTIAL_SUCCESS", "...":"..."}
```

30분 이내 재호출 정상 예:

```json
{"status":"SKIPPED","reason":"last scrape was less than 30 minutes ago"}
```

### 7. Lolipop cron 설정

Lolipop 사용자 전용 페이지에서 `cron設定`을 엽니다. 공식 문서상 실행ファイルパス는 FTPトップディレクトリからのパス를 넣습니다.

이 프로젝트는 HTTP endpoint 방식이 기본이므로, cron에는 작은 PHP wrapper를 두는 방식을 권장합니다.

`public/cron_scrape.php`가 포함되어 있습니다. `.env`의 `APP_HOST`와 `SCRAPE_TOKEN`을 읽어서 `/scrape.php`를 호출합니다.

Lolipop cron 실행ファイルパス 예:

```text
web/watch/public/cron_scrape.php
```

실행 시각은 10:00, 13:00, 18:00, 21:00 네 개를 각각 등록합니다.

```text
10:00 daily -> web/watch/public/cron_scrape.php
13:00 daily -> web/watch/public/cron_scrape.php
18:00 daily -> web/watch/public/cron_scrape.php
21:00 daily -> web/watch/public/cron_scrape.php
```

cron 실행 결과 메일은 처음에는 켜두고, 안정화 후 필요하면 끕니다.

### 8. 배포 후 점검

관리 화면:

```text
https://YOUR_DOMAIN/watch/
```

확인할 곳:

- Dashboard의 마지막 실행 상태
- `/runs.php`
- `/failures.php`
- `/diagnostics.php`
- `storage/logs/app.log`
- `storage/database.sqlite`

### 9. 업데이트 배포

로컬에서 코드 수정 후:

```bash
composer install --no-dev --optimize-autoloader
```

아래를 업로드합니다.

```text
public/
src/
config/
vendor/
index.php
assets.css
watches.php
runs.php
run.php
failures.php
diagnostics.php
scrape.php
cron_scrape.php
bootstrap.php
composer.json
composer.lock
.htaccess
.env.example
```

업로드하지 않는 것:

```text
.git/
storage/database.sqlite
storage/logs/app.log
tests/
Dockerfile
docker-compose.yml
```

### 10. 백업

SQLite 파일만 복사하면 됩니다.

```bash
cp storage/database.sqlite storage/database-$(date +%Y%m%d-%H%M%S).sqlite
```

FTP로 내려받아도 됩니다.

## 수동 scrape

`SCRAPE_TOKEN`이 비어 있으면 local에서는 token 없이 실행됩니다.

```bash
curl "http://localhost:8080/scrape.php"
```

token을 설정한 경우:

```env
SCRAPE_TOKEN=xxxxxxxxxxxxxxxx
```

```bash
curl "http://localhost:8080/scrape.php?token=xxxxxxxxxxxxxxxx"
```

## Debug Mode

기본적으로 직전 실행 시작 후 30분 이내 재호출은 실제 사이트 요청 없이 `SKIPPED`로 종료됩니다.

```env
SCRAPE_MIN_INTERVAL_MINUTES=30
DEBUG_MODE=false
```

개발 중 반복 실행하려면:

```env
DEBUG_MODE=true
```

## Slack 설정

```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
FIRST_RUN_NOTIFY=false
```

- 신규 상품이 1개 이상 있을 때만 Slack HTTP 요청을 보냅니다.
- 첫 성공 실행은 baseline 저장만 하고, 기본적으로 Slack을 보내지 않습니다.
- 신규 상품이 5개를 넘으면 메시지에는 5개만 표시하고 `외 N개`를 붙입니다.
- `SLACK_WEBHOOK_URL`이 비어 있으면 Slack 기능은 정상적으로 OFF 처리됩니다.

## Cron

운영 timezone 기준은 `Asia/Tokyo`입니다.

```cron
0 10,13,18,21 * * * curl -fsS --max-time 900 "https://example.com/watch/scrape.php?token=YOUR_TOKEN" >/dev/null 2>&1
```

Docker 내부 cron은 사용하지 않습니다.

## 화면

- `/` Dashboard: 마지막 실행, 성공/실패 사이트 수, 현재 재고 수, 사이트별 상태
- `/watches.php` 현재 재고: 사이트, category, 모델명, Reference, 가격 필터와 정렬
- `/runs.php` 전체 실행 기록
- `/run.php?id=123` 실행 상세 및 해당 scrape snapshot
- `/failures.php` 사이트별 실패 기록
- `/diagnostics.php` PHP/DB/로그/최근 실행 진단
- `/scrape.php` HTTP scrape 실행 endpoint

## SQLite 테이블

- `sources`: 사이트 설정 동기화 결과
- `scrape_runs`: 전체 실행 단위
- `scrape_source_runs`: 사이트 단위 성공/실패 결과
- `products`: 현재 및 과거 발견 상품 master
- `scrape_items`: 실행 당시 snapshot
- `notifications`: Slack 통지 결과

상품 identity는 가격을 사용하지 않습니다. 사이트 고유 ID가 있으면 우선 사용하고, 없으면 normalized product URL을 사용한 hash를 `identity_key`로 저장합니다.

## 신규 판정

신규 상품은 현재 성공 스냅샷과 해당 사이트의 직전 성공 스냅샷을 비교해 판단합니다.

```text
10:00 성공: A B C
13:00 실패
18:00 성공: A B C D
```

이 경우 `D`만 신규입니다. 실패 스냅샷은 비교 대상이 아니며, 실패한 사이트의 기존 상품을 inactive 처리하지 않습니다.

성공한 scrape에서 기존 상품이 사라졌다면 `products.is_active = 0`으로 갱신합니다. 나중에 다시 등장하면 active로 복구되고, 직전 성공 스냅샷에 없던 상품이므로 신규로 취급됩니다.

## 사이트별 scraper 추가

1. `src/Scraper/Sites/NewShopScraper.php`를 만들고 `ScraperInterface` 또는 `AbstractScraper`를 구현합니다.
2. 사이트 고유 CSS selector가 있으면 `cardSelectors()`, `nameSelectors()`, `priceSelectors()`를 override합니다.
3. `config/sites.php`에 `name`, `url`, `category`, `enabled`, `scraper`를 등록합니다.
4. `tests/Fixtures/new_shop.html` fixture를 추가합니다.
5. `tests/Unit/ScraperFixtureTest.php` provider에 추가합니다.
6. `composer test`로 fixture test를 확인합니다.

메인 `ScrapeRunner`에는 사이트별 selector를 넣지 않습니다.

## 장애 확인

- Dashboard의 사이트별 상태
- `/failures.php`
- `/diagnostics.php`
- `storage/logs/app.log`

민감정보인 `SCRAPE_TOKEN`, `SLACK_WEBHOOK_URL`은 로그에 출력하지 않습니다.

실제 조사 시점의 접근성:

| 사이트 | 확인 응답 |
| --- | --- |
| COMMIT GINZA | HTTP 200, Shopify HTML/JSON-LD 상품 단서 확인 |
| GMT | HTTP 403 확인, 실행 시 실패 기록으로 격리 |
| GINZA RASIN | HTTP 200, JSON-LD/DOM 상품 단서 확인 |
| Jackroad | HTTP 200, itemprop/DOM 상품 단서 확인 |
| Kame-Kichi | HTTP 200, JSON-LD/embedded JSON 상품 단서 확인 |
| LIPS | HTTP 200, DOM 상품 단서 확인 |
| BEST VINTAGE | HTTP 200, JSON-LD/data-product 상품 단서 확인 |
| ALLU | HTTP 200, Nuxt/JS 중심 응답. PHP HTTP HTML 파싱에서 상품 0개로 validation failure 처리 |
| OKURA | HTTP 200, JSON-LD/data-product 상품 단서 확인 |
| Housekihiroba | HTTP 200, JSON-LD/itemprop 상품 단서 확인 |
| KOMEHYO | HTTP 500 확인, 실행 시 실패 기록으로 격리 |
| Rodeo Drive | HTTP 200, JSON-LD/itemprop 상품 단서 확인 |
| Couronne | HTTP 200, DOM 상품 단서 확인 |
| MOONPHASE | HTTP 200, JSON-LD/data-product 상품 단서 확인 |
| BLUEK | HTTP 200, data-product/DOM 상품 단서 확인 |

사이트가 CAPTCHA, WAF, 403, 429, JS-only 렌더링, HTML 구조 변경 등으로 상품을 파싱할 수 없으면 해당 사이트만 실패로 기록하고 다음 사이트를 계속 처리합니다. CAPTCHA/WAF 우회는 구현하지 않습니다.

최종 로컬 검증에서는 15개 중 12개 사이트가 성공했고, GMT는 HTTP 403, KOMEHYO는 HTTP 500, ALLU는 서버 HTML 상품 0개로 실패 기록에 격리되었습니다.

## 테스트

Fixture test:

```bash
docker compose exec app composer test
```

실제 사이트 smoke test:

```bash
docker compose exec app composer test:live
```

`composer test:live`는 외부 사이트를 요청하므로 기본 테스트와 분리되어 있습니다.

## 백업

SQLite 파일 하나를 복사하면 됩니다.

```bash
cp storage/database.sqlite backup/database-$(date +%Y%m%d-%H%M%S).sqlite
```
