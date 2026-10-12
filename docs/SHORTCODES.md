# Frontend Shortcodes Guide

DW Catalog WP 프론트엔드 숏코드 사용법.

---

## 0. `[dw_catalog]` — 카탈로그 페이지 (권장)

페이지에 숏코드 하나만 넣으면 **목록 + 검색/필터/정렬 + 상세페이지**가 모두 그 페이지 안에서 동작합니다.

```
[dw_catalog category="refrigerated" filter_fields="dw_pc_brand_raw"]
```

- **목록**: 왼쪽 사이드바(검색 · 카테고리 체크박스 · 필드 필터 · Reset) + 정렬 + 카드 그리드 + 페이지네이션
- **상세**: 카드를 누르면 같은 페이지에서 `?dw_item={slug}` 로 상세 표시 (이미지 갤러리 · 필드 · Variations 표 · 설명 · 관련 상품). "Back to list" 는 필터 상태를 유지합니다.
- 필터 변경은 페이지 새로고침 없이 반영되고 URL 도 함께 바뀝니다 (공유·뒤로가기 가능). JS 가 꺼져 있어도 일반 링크/폼으로 동작합니다.
- 검색 대상: 제품명 + **공개(Detail 체크) 필드** + 공개 Variation 컬럼. 단어 앞부분 일치 (`WK-009` = `wk009`)
- 비공개 필드는 카드 · 상세 · 필터 · 정렬 · 검색 어디에도 쓰이지 않습니다. `show_fields` · `detail_fields` · `filter_fields` 에 넣어도 무시됩니다.
- 상품 고유 주소(`/product/{slug}/`)도 같은 상세 화면으로 표시됩니다.

### 옵션
| 속성 | 기본값 | 설명 |
|------|--------|------|
| `category` | (없음) | 이 페이지에 고정할 카테고리 슬러그/ID. 사이드바에는 그 하위 카테고리가 표시됩니다 |
| `filter_fields` | (없음) | 사이드바 필터로 쓸 필드 meta_key (쉼표 구분). Select 필드는 옵션, 텍스트 필드는 실제 저장된 값이 체크박스가 됩니다 |
| `columns` | `4` | 최대 컬럼 수 (화면이 좁으면 자동으로 줄어듭니다) |
| `per_page` | `20` | 페이지당 상품 수 |
| `show_fields` | (없음) | 카드에 표시할 필드. 비우면 "List" 체크된 필드 |
| `detail_fields` | (없음) | 상세에 표시할 필드. 비우면 Custom Fields 화면에서 "Detail" 체크한 필드 |
| `search` | `yes` | 검색창 표시 |
| `sort` | `yes` | 정렬 메뉴 표시 (최신순 · 오래된순 · 이름순 · Number 필드의 낮은/높은 순) |
| `default_sort` | `date_desc` | 기본 정렬 (`date_desc`, `date_asc`, `title_asc`, `title_desc`, `meta:{meta_key}:asc|desc`) |
| `show_category` | `yes` | 카드 상단 카테고리명 |
| `related` | `4` | 상세 하단 관련 상품 수 (`0` = 끔) |
| `inquiry_url` | (없음) | 상세에 문의 버튼 표시. 상품명이 `?item=` 으로 전달됩니다 |
| `inquiry_label` | `Inquire about this item` | 문의 버튼 문구 |
| `image_size` | `medium` | 카드 이미지 크기 |

### 예제
```
<!-- 카테고리별 페이지 (Fresh / Refrigerated / Frozen ...) -->
[dw_catalog category="refrigerated" filter_fields="dw_pc_brand_raw"]

<!-- 전체 카탈로그, 이름순, 문의 버튼 -->
[dw_catalog default_sort="title_asc" inquiry_url="/wholesale-inquiry/"]
```

> 한 페이지에 `[dw_catalog]` 는 하나만 넣으십시오 (URL 파라미터를 공유합니다).

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
