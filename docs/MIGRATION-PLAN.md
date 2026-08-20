# dw-catalog-wp 마이그레이션 — 보호 경계 합의서

> 본 합의서는 [PLUGIN-AGENT-KICKOFF.md §0](https://github.com/dasomweb/DASOM-Forge/blob/main/docs/platform/PLUGIN-AGENT-KICKOFF.md) 에 따른 의무 산출물입니다.
> 합의서에 없는 변경은 별도 운영자 합의가 필요합니다.

| 항목 | 값 |
|---|---|
| 작성일 | 2026-08-19 |
| 운영자 합의 | dasomweb@gmail.com |
| 에이전트 | Claude Code (Opus 5) |
| 대상 버전 | v1.2.1 → **v2.0.0** |
| 참조 패키지 | dasomforge-plugin-kickoff-2026-08-07-2a7f5e1 (HEAD `2a7f5e1`) |
| 적용 범위 | **A — 게이트 + 무결성 + ZIP 구조** (런타임 JS 미적용) |

---

## 1. 핵심 기능 (한 줄 정의)

> **"포스트 타입마다 관리자가 직접 정의한 동적 커스텀 필드 스키마로 카탈로그를 구성하고, 그 데이터를 프런트 숏코드 3종으로 노출한다."**

---

## 2. 가치 창출 로직 (보호 대상)

경쟁자가 복제하면 가치 있는 부분:

- [x] **동적 필드 스키마 엔진** — 위치: [`includes/class-pc-config.php`](../includes/class-pc-config.php), [`includes/class-pc-settings.php`](../includes/class-pc-settings.php)
      포스트 타입별 필드 정의·타입·select 옵션·list 노출·title 필드 지정을 UI 로 관리. 이 플러그인의 진짜 자산.
- [x] **필드 값 저장/해석 파이프라인** — 위치: [`includes/class-pc-meta-box.php`](../includes/class-pc-meta-box.php)
- [x] **CSV 대량 가져오기 매핑 로직** — 위치: [`includes/class-pc-bulk-import.php`](../includes/class-pc-bulk-import.php)
      헤더 정규화 · 중복 스킵 · 이미지 사이드로드 · 분류 자동 생성
- [x] **PDF 카탈로그 조판** — 위치: [`includes/class-pc-pdf-export.php`](../includes/class-pc-pdf-export.php)
- [x] **숏코드 렌더 + 디자인 변수 주입** — 위치: [`includes/class-dwcat-shortcodes.php`](../includes/class-dwcat-shortcodes.php), [`includes/class-dwcat-design-settings.php`](../includes/class-dwcat-design-settings.php)

## 3. 사포화되는 부분 (PHP 평문 OK)

- [x] 어드민 UI 껍데기 · 설정 폼 마크업
- [x] 표준 WP hook 연결 (CPT/taxonomy 등록, 메뉴, 컬럼)
- [x] URL/경로 헬퍼 ([`class-pc-url-helper.php`](../includes/class-pc-url-helper.php))
- [x] DW Admin SPA 모듈 등록 (dw-admin 이 소유한 프로토콜)

---

## 4. 적용할 anti-piracy 메커니즘 합의

### 4.1 메커니즘 1 — 런타임 JS 서버 배포

**❌ 미적용** (범위 A 결정).

근거와 트레이드오프:

- 이 플러그인의 가치 로직 대부분은 **관리자 측 스키마 엔진**이지 프런트 렌더가 아닙니다.
  숏코드 3종은 표준 WP_Query + 필드 출력으로, "경쟁자가 베껴도 별 가치 없는" 축에 가깝습니다.
  KICKOFF §0 이 경고하는 *"사포화되는 코드를 과보호"* 케이스에 해당합니다.
- 대신 **게이트 9종**(§5)이 렌더·에셋·저장·스키마 편집·가져오기·PDF·REST 전 경로를 차단합니다.
- 런타임 JS 를 적용하면 운영자가 CDN 번들을 배포하기 전까지 프런트가 완전히 죽습니다 —
  카나리 검증을 먼저 통과시키는 편이 안전하다는 판단.

**재검토 시점**: v2.1 이후 숏코드 렌더가 복잡한 레이아웃 엔진으로 성장하면 그때 §4.1 을 갱신하고
`build/build-runtime.js` + `runtime/` 을 도입합니다. `manifest.json` 은 `runtime` 섹션이 생략된 상태이므로
추가만 하면 됩니다 (`build/generate-release-manifest.js`).

### 4.2 메커니즘 2 — 설정 서명 (config signing)

**❌ 미적용** (범위 A 결정).

- 이 플러그인의 공개 노출 데이터는 `wp_postmeta` 의 일반 포스트 필드 값입니다.
  런타임 JS 가 없으므로 서명을 검증할 주체(브라우저 측 runtime)가 없어 서명만 붙이면 장식이 됩니다.
- SDK 는 `sign_config()` / `verify_signature()` 를 **구현해 둔 상태**이므로 §4.1 재검토 시 즉시 사용 가능.
- 30일 grace 정책은 적용 시점에 재논의.

### 4.3 메커니즘 3 — 무결성 검사

**✅ 적용** (전면).

매니페스트 포함 파일: **릴리스 ZIP `wp-plugin/` 하위 전체** (v2.0.0 기준 667개 — PHP·CSS·JS·`vendor/` 643개 포함).

- `integrity.json` 은 **CI 가 스테이징된 실물 위에서** 생성합니다 (`build/generate-integrity.js --stage`).
  소스트리를 걷고 제외 패턴을 손으로 맞추면 ZIP 실물과 반드시 어긋나고, 그 즉시 `MANIFEST_INCOMPLETE` 입니다.
- `build/verify-package.js` 가 릴리스 ZIP 에 대해 **서버가 할 판정을 그대로 재현**합니다
  (서버 해시 대상 집합 == 플러그인 전송 집합). CI 필수 단계.
- 키 규약: **§7.2.2 규약 1 (루트상대)**. `integrity.json` 의 `files` 를 SDK 가 그대로 전송합니다.
  ※ 다음 담당자 주의 — 규약 2(슬러그 접두사)로 바꾸지 마십시오.

---

## 5. 라이선스 게이트 분산 위치 (9개)

정의: [`includes/license/gates.php`](../includes/license/gates.php)

| 게이트 | 호출 위치 | 검증 경로 | 차단 효과 |
|---|---|---|---|
| `dwcat_can_render()` | `DWCAT_Shortcodes::shortcode_grid\|carousel\|magazine` | `has_valid_token()` | 프런트 카탈로그 전체 |
| `dwcat_can_load_assets()` | 동 클래스 enqueue 지점 3곳 | `verify_token_signature()` (로컬 RSA) | CSS/JS 미로딩 |
| `dwcat_can_save_meta()` | `DWCAT_Meta_Box::save_meta` | 토큰 클레임 `domain` 교차 확인 | 필드 값 저장 |
| `dwcat_can_manage_fields()` | `DWCAT_Settings::handle_save_fields` | 라이선스 option + 클레임 `aud` | **스키마 편집 = 핵심 자산** |
| `dwcat_can_bulk_import()` | `DWCAT_Bulk_Import::handle_import` | 서명 검증 + `exp` 직접 확인 | CSV 가져오기 |
| `dwcat_can_export_pdf()` | `DWCAT_PDF_Export::handle_export` | `has_valid_token()` **and** `verify_token_signature()` | PDF 생성 |
| `dwcat_can_render_admin()` | `DWCAT_Admin_Pages::render_list_page` | `get_status()` ∈ {licensed, grace} | 관리 화면 |
| `dwcat_can_use_shortcode()` | 숏코드 3종 진입부 | 토큰 + `integrity.json` 버전 정합 | 매니페스트 제거·교체 탐지 |
| `dwcat_can_call_rest()` | `dw_spa_modules` 필터 등록 | 토큰 option 직접 판독 (SDK 우회 경로) | SPA 모듈 노출 |

**설계 의도**: 네 가지 서로 다른 검증 경로를 섞었습니다. 전부 `has_valid_token()` 한 곳을 거치면
`return true;` 한 줄로 9개가 동시에 뚫립니다.

**dev bypass**: `DWCAT_DEV_BYPASS` — `wp-config.php` 에서만 정의. 릴리스 ZIP 에 `wp-config.php` 가
섞이면 `.github/scripts/package-release.sh` 가 빌드를 실패시킵니다.

---

## 6. tier · feature 정의 (운영자 결정)

- **basic**: v2.0.0 은 단일 tier (KICKOFF FAQ Q16 권장 따름). 기능 분기 없음.
- plus / pro: v2.1+ 별도 sprint. `License.features` 배열은 SDK 가 이미 저장·노출하므로
  분기 지점만 추가하면 됩니다.
- **max_domains**: 운영자 결정 필요 (기본 제안 basic=1).

---

## 7. 백워드 호환 (v1.x 사용자)

**정책: 자동 업데이트로 v2.0.0 을 받아도 기존 사이트 카탈로그가 즉시 사라지지 않습니다** (FAQ Q8).

구현:

- `dwcat_maybe_grant_legacy_grace()` — `dwcat_version` 옵션이 존재하고 `< 2.0.0` 이면
  **1회만** `dwcat_legacy_grace_until = now + 30일` 부여. 신규 설치는 유예 없음.
- 유예 중에는 9개 게이트가 모두 통과하고, 관리자에게 잔여 일수 notice 를 띄웁니다
  (7일 이하면 `notice-error` 로 격상).
- 유예 기간: `DWCAT_LEGACY_GRACE_DAYS` 상수 (기본 30). 운영자가 늘리려면 `wp-config.php` 에서 재정의 가능.

| 항목 | 값 |
|---|---|
| v1.x 활성 라이선스 수 | **운영자 확인 필요** |
| 깨질 수 있는 데이터 형태 | 없음 — 옵션/포스트메타 스키마 무변경 |
| 마이그레이션 전략 | 30일 유예 + 점증 notice, 데이터 변환 없음 |
| Legacy API grace | `/license/verify` 계속 사용 (일일 cron) |

**v1 옵션 키 보존**: 라이선스 상태는 v1 과 동일한 `dw_license_dw_catalog_wp` 에 저장합니다 —
기존 활성화 사용자는 키 재입력 없이 승계됩니다.

---

## 8. 카나리 라이선스

- 운영자 발급 받음? **[ ] 아직**
- 도메인: (운영자 결정)
- tier: `basic`
- 만료: 발급일 + 1년
- max_domains: 5
- 발급 시점: **첫 SyncRelease 직후, 통합 검증 이전**

---

## 9. SDK 배포 전략 — **Prefix** (PLUGIN-DEV-GUIDE §3.5 B)

| 항목 | 값 |
|---|---|
| 클래스명 | `DW_DWCAT_License_Manager` · `DW_DWCAT_Forge_Client` |
| 파일명 | 정본과 동일 (`class-dw-license-manager.php` · `class-dw-forge-client.php`) — rebase 편의 |
| 선택 이유 | dw-catalog-wp 는 DW Admin SPA 의 **소비자**이지 shared admin surface 제공자가 아니며, 릴리스 주기가 dw-admin 과 독립적. canonical 이면 dw-admin 이 구 SDK winner 일 때 게이트 9개가 전부 조용히 false 로 떨어짐 |
| 대가 | 라이선스 입력 UI · daily verify cron · 토큰 캐시가 이 플러그인 전용 (사용자가 플러그인별로 키 입력) |

**SDK 출처 (중요 — 다음 담당자 필독)**:

정본 SDK v2.1.0 두 파일은 **DW-MCP ref `7f7eafff` 에 인덱싱되어 있지 않습니다** (해당 ref 는
`docs/platform/*.md` 11개만 포함). 따라서 이 플러그인의 SDK 는 운영자 승인 하에
**API-CONTRACT v1.2 · API-USAGE v1.1 · SDK-README 공개 API 표를 기준으로 새로 작성**한 것입니다.

→ 정본과 내부 구현이 다를 수 있습니다. 정본을 입수하면 diff 후 rebase 하십시오.
   공개 API 시그니처는 SDK-README 표와 1:1 로 맞춰 두었습니다.

---

## 10. 빌드 파이프라인

```
tag v2.0.0 push
  → 버전 정합 확인 (헤더 == config == 태그)      ← 어긋나면 INTEGRITY_MANIFEST_NOT_FOUND
  → composer install (lock 필수)                  ← lock 없으면 빌드 실패
  → package-release.sh
       rsync → release/wp-plugin/
       README.txt 생성
       위생 점검 (wp-config.php · Zone.Identifier · 공개키 PEM)
       generate-integrity.js --stage             ← ZIP 실물 기준
       generate-release-manifest.js              ← 버전·파일존재 자기 점검
       chmod 644/755 + touch SOURCE_DATE_EPOCH
       zip -X (정렬된 엔트리)
  → 같은 소스로 두 번 빌드 → SHA256 일치 확인      ← §11.3.4
  → verify-package.js                            ← 서버 판정 재현
  → GitHub Release
```

`SOURCE_DATE_EPOCH` = 태그 커밋 시각. `.gitattributes` 가 라인엔딩을 LF 로 고정합니다.

---

## 11. 미해결 항목 — 운영자 확인 필요

| # | 항목 | 상세 | 왜 에이전트가 못 정하는가 |
|---|---|---|---|
| 1 | **`/auth/token` 요청 본문 크기** | 실측 **82.6 KB** (667개 파일 해시). vendor 643개가 대부분. 서버 body-parser 한도가 이보다 작으면 매 요청 실패 | 서버 코드 미열람. API-CONTRACT 는 50KB 한도를 `/configs/sign` **payload** 에만 명시하고 `/auth/token` 전체 본문 한도는 규정하지 않음 |
| 2 | `dw-catalog-wp` Product 등록 여부 | `/admin/products` 에 slug 존재 확인 | 운영자 어드민 권한 필요 (FAQ Q13) |
| 3 | 카나리 라이선스 발급 | §8 | 운영자 전용 |
| 4 | `max_domains` (basic) | §6 | 운영자 결정 |
| 5 | v1.x 활성 라이선스 수 | §7 유예 정책 검증용 | 운영자 데이터 |
| 6 | 정본 SDK v2.1.0 diff | §9 | 정본 파일 미입수 |

**#1 이 가장 급합니다.** 한도에 걸리면 선택지:
- (a) 운영자가 `/auth/token` body limit 상향 — 가장 저렴
- (b) vendor 를 ZIP 에서 제외 → PDF 내보내기 포기 (기능 손실)
- (c) 서버가 파일 수 기반 청크 프로토콜 도입 → API-CONTRACT 변경 (v2 엔드포인트)

읽기 전용 확인은 `GET /integrity/dw-catalog-wp/v2.0.0` 로 가능하지만
(`User-Agent: DW-dev-diag/dw-catalog-wp-agent`), **본문 한도는 그것으로 알 수 없습니다** —
`/auth/token` 은 부작용 있는 endpoint 라 [PLUGIN-AGENT-API-CONDUCT](https://github.com/dasomweb/DASOM-Forge/blob/main/docs/platform/PLUGIN-AGENT-API-CONDUCT.md) §MUST 2 에 따라
운영자 확인 없이 호출하지 않았습니다.

---

## 12. 범위 밖에서 발생한 필수 변경 — dompdf 2.x → 3.x

결정론적 빌드(§11.2.b)는 `composer.lock` 을 요구하는데, `dompdf/dompdf ^2.0` 은
**2.x 전 버전이 보안 권고로 차단**되어 lock 생성 자체가 불가능했습니다
(CVE-2023-50262 포함 10건, `composer audit`).

- `^2.0` → `^3.1` (설치본 v3.1.6), `composer audit` 클린
- 사용 중인 API 5개 (`new Dompdf(array)` · `setPaper` · `loadHtml` · `render` · `stream`) 는 3.x 에서 동일
- `php ^7.1 || ^8.0` 이므로 플러그인의 PHP 7.4 하한 유지
- 신규 전이 의존성: `masterminds/html5`, `sabberworm/php-css-parser`, `thecodingmachine/safe`

**즉, v1.2.1 릴리스 ZIP 에는 취약한 dompdf 2.x 가 실려 있었습니다.** v2.0.0 에서 해소.

---

## 13. Sprint 일정

| Sprint | 범위 | 상태 |
|---|---|---|
| MP1 | SDK 교체 + 게이트 분산 + 무결성 + ZIP 구조 | ✅ 본 변경 |
| MP2 | 카나리 검증 (활성화 → 토큰 → 게이트 개방 → tamper 감지) | 대기 — 운영자 §11 |
| MP3 | v2.0.0 stable 출시 + 7일 모니터링 | 대기 |
| MP4 | (선택) 런타임 JS + config 서명 — §4.1 재검토 | 미정 |

---

## 14. 운영자 승인

- [ ] 운영자(dasomweb) 검토 완료
- [ ] §11 미해결 항목 6건 회신
- [ ] 카나리 라이선스 발급
- [ ] v2.0.0 태그 → SyncRelease 승인

승인 일자: ____
서명: ____ (이메일)

---

> 합의서에 *없는* 변경은 별도 운영자 합의 필요. 합의 변경 시 본 문서를 갱신하고 commit 으로 추적.
