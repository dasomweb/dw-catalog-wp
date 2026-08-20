# Changelog

DW Catalog WP 의 변경 이력. [SemVer](https://semver.org/lang/ko/) 를 따릅니다
(PLUGIN-DEV-GUIDE §7.1).

릴리스마다 **anti-piracy 관련 변경**을 별도 표기합니다 (§7.6 의무).

---

## [2.0.0-rc.1] — 2026-08-19

DASOM-Forge 플랫폼 통합. **MAJOR** — 라이선스 흐름이 바뀝니다.

> **RC 인 이유**: 카나리 검증(MP2)이 아직 수행되지 않았습니다. GitHub 에는
> **prerelease** 로 게시되므로 기존 v1.2.1 사이트의 `/releases/latest` 조회에
> 잡히지 않습니다 — 실사용 사이트에 breaking 업데이트가 새어 나가지 않습니다.
> 카나리 검증 통과 후 `v2.0.0` 을 stable 로 태깅합니다
> (PLUGIN-RELEASE-CHECKLIST §9).

### ⚠ 사용자 영향

- **이 플러그인은 이제 유효한 DASOM-Forge 라이선스를 요구합니다.**
  카탈로그 프런트 출력 · 필드 스키마 편집 · CSV 가져오기 · PDF 내보내기가
  라이선스 게이트 뒤로 들어갔습니다.
- **기존 v1.x 설치는 자동 업데이트로 즉시 끊기지 않습니다** — 업그레이드가
  감지되면 **30일 유예**가 1회 부여되고, 남은 일수가 관리자 화면에 표시됩니다
  (KICKOFF FAQ Q8). 유예 중 라이선스를 활성화하면 그대로 이어집니다.
- 라이선스 옵션 키(`dw_license_dw_catalog_wp`)는 v1.x 와 동일합니다 →
  **이미 활성화한 사용자는 키를 다시 넣을 필요가 없습니다.**

### Added

- **DASOM-Forge SDK** (`DW_DWCAT_License_Manager` · `DW_DWCAT_Forge_Client`)
  — Prefix 배포 전략 (PLUGIN-DEV-GUIDE §3.5 B). 다른 DW 플러그인의 SDK 버전과
  완전 격리되어 winner race 가 발생하지 않습니다.
- **라이선스 게이트 9종** (`includes/license/gates.php`) — 6개 파일에 분산,
  4가지 서로 다른 검증 경로 혼합 (§6).
- **무결성 매니페스트** — `integrity.json` 을 CI 가 릴리스 스테이지 실물 위에서
  생성 (§7.2.2 규약 1 · 루트상대 키).
- **릴리스 계약 자가 검증** (`build/verify-package.js`) — 서버가 SyncRelease 에서
  하는 판정(해시 대상 집합 일치)을 빌드 시점에 재현합니다.
- **결정론적 빌드** — `.gitattributes` + `SOURCE_DATE_EPOCH` + `zip -X`,
  CI 가 같은 소스로 두 번 빌드해 SHA256 일치를 강제 (§11.2.b).
- **멀티사이트 지원** (FAQ Q10) — 라이선스는 네트워크 단위, 토큰 캐시는 사이트 단위.
  네트워크 관리자 메뉴 추가.
- **진단 엔드포인트** `GET /wp-json/dw-catalog-wp/v1/license/status` (§3.6 SHOULD #2)
  — 관리자 인증 필수. 라이선스 키·토큰은 노출하지 않습니다.
- **부트스트랩/게이트 런타임 테스트** (`tests/test-bootstrap.php`) —
  게이트 fail-closed · 유예 · dev bypass 를 실행으로 검증.
- `dasomforge.pub` 공개키 동봉 (`includes/keys/`).
- `docs/MIGRATION-PLAN.md` — §0 보호 경계 합의서.

### Changed

- **API 베이스가 `api.dasomforge.com` 으로 전환**되었습니다
  (기존 `api-production-a3f4.up.railway.app`).
- 릴리스 ZIP 이 **플랫폼 표준 멀티루트 구조**로 바뀌었습니다 —
  `manifest.json` + `wp-plugin/` (§7.2). 이 ZIP 은 워드프레스에 직접 업로드하지
  않고 dasomforge "Sync Release" 를 거칩니다.
- **GitHub 업데이터를 완전히 비활성화**했습니다 (§7.4 "GitHub 직접 다운로드 ❌").
  dasomforge 가 유일한 배포 채널입니다. 기술적으로도 필수 — v2.0 부터 GitHub
  Release 자산은 **플랫폼 멀티루트 ZIP** 이라 워드프레스가 직접 설치할 수 없습니다
  (§7.2.1 제출 ZIP ≠ 고객 설치 ZIP). `DWCAT_GitHub_Updater` 클래스 파일은
  롤백 대비로 남기되 인스턴스화하지 않습니다.
- 라이선스 관리자 화면을 admin-post 기반으로 교체 (기존 AJAX → nonce + capability).

### Security

- **dompdf `^2.0` → `^3.1`** — 2.x 전 버전이 보안 권고로 차단된 상태였습니다
  (CVE-2023-50262 포함 10건). v1.2.1 릴리스 ZIP 에는 취약한 dompdf 2.x 가
  실려 있었습니다. `composer.lock` 을 커밋해 vendor 내용을 고정했습니다.
- 릴리스 패키징이 `wp-config.php` · `Zone.Identifier` 혼입 시 빌드를 실패시킵니다.

### Fixed

- 레거시 유예 판정이 `version_compare('2.0.0-rc.1', '2.0.0', '>=')` 에 의존해
  프리릴리스를 v1 으로 오인, **v2 사이트에 30일 유예를 잘못 부여**하던 버그.
  메이저 버전 비교로 교체하고 회귀 테스트를 추가했습니다.

### anti-piracy 관련 변경 (§7.6 의무 표기)

| 항목 | 내용 |
|---|---|
| 해시 변경 사유 | **전체 파일 재해싱** — SDK 신규 추가 + vendor(dompdf 3.x) 교체. 매니페스트 재등록 필수 |
| 새 게이트 | 9개 신규 (`dwcat_can_render` · `_load_assets` · `_save_meta` · `_manage_fields` · `_bulk_import` · `_export_pdf` · `_render_admin` · `_use_shortcode` · `_call_rest`) |
| 무결성 키 규약 | **규약 1 (루트상대)** 채택 — MIGRATION-PLAN §4.3 |
| runtime_version 변경 | 해당 없음 — 런타임 JS 미적용 (MIGRATION-PLAN §4.1) |
| 공개키 회전 | 없음 (최초 도입) |
| 마이그레이션 필요 여부 | 사용자 조치 = 라이선스 키 입력 1회. 데이터 변환 없음 |
| 업데이트 채널 | GitHub → **dasomforge R2 signed URL** 로 전환 (§7.4) |

---

## [1.2.1] — 2026-04

- 마케팅 문서에 v1.2.0 디자인 설정 반영.

## [1.2.0] — 2026-04

- 숏코드 디자인 설정 (관리자 타이포그래피 + 컬러).

## [1.1.0] — 2026-04

- 프런트엔드 숏코드 3종 (Grid, Carousel, Magazine).

## [1.0.7] — 2026-04

- WP 플러그인 패키징 가이드 추가.

## [1.0.6] — 2026-04

- DW License Manager SDK (v1) + DW Admin SPA 통합.
