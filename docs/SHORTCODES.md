# Frontend Shortcodes Guide

DW Catalog WP 프론트엔드 숏코드 사용법.

---

## 1. `[dw_catalog_grid]` — 카탈로그 그리드

### 기본 사용
```
[dw_catalog_grid]
```

### 옵션
| 속성 | 기본값 | 설명 |
|------|--------|------|
| `post_type` | `product` | 포스트 타입 슬러그 |
| `columns` | `3` | 컬럼 수 (1~6) |
| `per_page` | `12` | 표시할 상품 수 |
| `category` | (없음) | 카테고리 슬러그 또는 ID (쉼표 구분) |
| `ids` | (없음) | 특정 포스트 ID 목록 (쉼표 구분) |
| `show_link` | `yes` | 상세보기 링크 (`yes` / `no`) |
| `show_fields` | (없음) | 표시할 필드 meta_key (쉼표 구분). 비우면 `show_in_list` 필드 모두 표시 |
| `image_size` | `medium` | 이미지 크기 (`thumbnail`, `medium`, `large`, `full`) |
| `order` | `DESC` | 정렬 (`ASC` / `DESC`) |
| `orderby` | `date` | 정렬 기준 |

### 예제
```
<!-- 4컬럼 그리드, 상세보기 없음 -->
[dw_catalog_grid columns="4" show_link="no"]

<!-- 특정 카테고리, 특정 필드만 표시 -->
[dw_catalog_grid category="seafood" show_fields="dw_pc_item_code,dw_pc_brand_raw"]

<!-- 특정 상품만 -->
[dw_catalog_grid ids="10,25,42"]
```

---

## 2. `[dw_catalog_carousel]` — 카탈로그 캐로셀

### 기본 사용
```
[dw_catalog_carousel]
```

### 옵션
| 속성 | 기본값 | 설명 |
|------|--------|------|
| `post_type` | `product` | 포스트 타입 |
| `per_slide` | `3` | 한 화면에 표시할 상품 수 (1~6) |
| `per_page` | `12` | 총 상품 수 |
| `autoplay` | `yes` | 자동 재생 (`yes` / `no`) |
| `interval` | `5000` | 자동 재생 간격 (ms) |
| `category` | (없음) | 카테고리 필터 |
| `ids` | (없음) | 특정 포스트 ID |
| `show_link` | `yes` | 상세보기 링크 |
| `show_fields` | (없음) | 표시할 필드 |
| `image_size` | `medium` | 이미지 크기 |

### 예제
```
<!-- 자동 재생 끄고, 4개씩 표시 -->
[dw_catalog_carousel autoplay="no" per_slide="4"]

<!-- 7초마다 자동 재생, 2개씩 표시 -->
[dw_catalog_carousel per_slide="2" interval="7000"]

<!-- 상세보기 없이 단순 이미지만 -->
[dw_catalog_carousel show_link="no" show_fields=""]
```

### 동작
- 좌우 화살표 버튼으로 수동 이동
- 호버 시 자동 재생 일시정지
- 반응형: 데스크톱 `per_slide` / 태블릿 2개 / 모바일 1개

---

## 3. `[dw_catalog_magazine]` — 매거진 스타일 상세

제품 이미지를 배경으로, 제목과 커스텀 필드를 오버레이로 표시하는 매거진 스타일 레이아웃.

### 기본 사용 (단일 포스트 페이지에서)
```
[dw_catalog_magazine]
```
현재 포스트의 데이터를 자동으로 사용합니다.

### 특정 포스트 지정
```
[dw_catalog_magazine post_id="42"]
```

### 옵션
| 속성 | 기본값 | 설명 |
|------|--------|------|
| `post_id` | 현재 포스트 | 포스트 ID |
| `position` | `bottom-right` | 오버레이 위치 |
| `show_fields` | (전체) | 표시할 필드 meta_key. 비우면 `show_in_list` 필드 모두 |
| `show_title` | `yes` | 제목 표시 여부 |
| `height` | `600` | 배너 높이 (px) |
| `overlay` | `dark` | 오버레이 스타일 (`dark` / `light` / `none`) |

### `position` 옵션
- `top-left`: 좌측 상단
- `top-right`: 우측 상단
- `bottom-left`: 좌측 하단
- `bottom-right`: 우측 하단 (기본)
- `middle` 또는 `center`: 중앙

### 예제
```
<!-- 중앙 정렬, 밝은 오버레이 -->
[dw_catalog_magazine position="middle" overlay="light"]

<!-- 좌측 하단, 높이 800px -->
[dw_catalog_magazine position="bottom-left" height="800"]

<!-- 특정 필드만 표시 -->
[dw_catalog_magazine show_fields="dw_pc_brand_raw,dw_pc_origin_raw"]

<!-- 오버레이 없이 (이미지 위에 직접 텍스트) -->
[dw_catalog_magazine overlay="none" position="bottom-right"]
```

---

## 실전 예제

### 메인 페이지 히어로
```
[dw_catalog_carousel per_slide="1" autoplay="yes" interval="4000" show_link="yes"]
```

### 상품 목록 페이지
```
[dw_catalog_grid columns="3" per_page="24" show_fields="dw_pc_item_code,dw_pc_brand_raw"]
```

### 카테고리 페이지
```
[dw_catalog_grid category="seafood" columns="4"]
```

### 제품 상세 페이지 (single-product.php 또는 Kadence 동적 컨텐츠)
```
[dw_catalog_magazine position="bottom-right" overlay="dark" height="500"]
```

### 관련 상품 (특정 ID 목록)
```
<h3>Related Products</h3>
[dw_catalog_grid ids="10,25,42,56" columns="4" show_link="yes"]
```

---

## 반응형 동작

- **그리드**: 데스크톱 `columns` / 태블릿 2컬럼 / 모바일 1컬럼
- **캐로셀**: 데스크톱 `per_slide` / 태블릿 2개 / 모바일 1개
- **매거진**: 모바일에서 오버레이 전체 너비 + 패딩 축소

---

## 스타일 커스터마이징

CSS 변수로 간단 수정:
```css
.dwcat-card {
	border-radius: 12px;
	border-color: #ddd;
}
.dwcat-card-title {
	color: #111;
	font-size: 18px;
}
.dwcat-magazine-content {
	background: rgba(255, 107, 53, 0.9) !important;
}
```

테마의 `functions.php`에서 `wp_enqueue_style('your-theme')`에 `.dwcat-` 오버라이드 추가.
