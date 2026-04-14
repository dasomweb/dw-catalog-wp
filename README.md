# DW Catalog WP

WordPress 플러그인 - 도메인 변경에 친화적인 제품 카탈로그 플러그인

Domain-change friendly WordPress product catalog plugin

## 📦 GitHub을 통한 배포 (GitHub Deployment)

이 플러그인은 GitHub Releases를 통해 자동으로 배포되고 업데이트됩니다.

This plugin is automatically deployed and updated through GitHub Releases.

### 🚀 릴리스 생성 방법 (How to Create a Release)

#### 방법 1: 자동 릴리스 (권장) - Automated Release (Recommended)

1. **버전 업데이트 및 태그 생성:**
   ```bash
   # 버전 번호 업데이트 (플러그인 파일에서 수동으로)
   # Update version number (manually in plugin file)
   
   # Git 태그 생성
   git tag v1.0.0
   git push origin v1.0.0
   ```

2. **GitHub Actions가 자동으로:**
   - 플러그인 ZIP 파일 생성
   - GitHub Release 생성
   - 릴리스 자산으로 ZIP 파일 첨부

#### 방법 2: GitHub Actions 워크플로우 사용

1. GitHub 저장소에서 **Actions** 탭으로 이동
2. **Update Plugin Version** 워크플로우 선택
3. **Run workflow** 클릭
4. 새 버전 번호 입력 (예: `1.0.1`)
5. 워크플로우가 자동으로:
   - 플러그인 파일의 버전 업데이트
   - 커밋 및 푸시
   - 버전 태그 생성
   - 릴리스 생성

#### 방법 3: 수동 릴리스

1. GitHub 저장소에서 **Releases** > **Draft a new release** 클릭
2. 태그 선택 또는 새 태그 생성 (예: `v1.0.0`)
3. 릴리스 제목 및 설명 작성
4. 플러그인 ZIP 파일을 수동으로 업로드
   - ZIP 파일명: `dw-catalog-wp-{version}.zip`
   - ZIP에는 `.git`, `.github` 폴더 제외

### 📥 설치 방법 (Installation)

#### WordPress에서 직접 설치

1. WordPress 관리자 페이지로 이동
2. **플러그인** > **새로 추가** 클릭
3. **플러그인 업로드** 클릭
4. GitHub Releases에서 다운로드한 ZIP 파일 업로드
5. **지금 설치** 클릭
6. **플러그인 활성화** 클릭

#### 자동 업데이트

플러그인이 설치되면 자동으로 GitHub에서 업데이트를 확인합니다:

- WordPress 관리자 > **플러그인** 페이지에서 업데이트 알림 확인
- **지금 업데이트** 버튼 클릭하여 최신 버전 설치
- 도메인 변경 후에도 업데이트 기능이 정상 작동합니다

### 🔧 개발 환경 설정 (Development Setup)

```bash
# 저장소 클론
git clone https://github.com/dasomweb/dw-catalog-wp.git
cd dw-catalog-wp

# WordPress 플러그인 디렉토리에 심볼릭 링크 생성 (선택사항)
# Create symbolic link to WordPress plugins directory (optional)
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/dw-catalog-wp
```

### 📋 릴리스 체크리스트 (Release Checklist)

릴리스 전 확인사항:

- [ ] 플러그인 버전 번호 업데이트 (`dw-catalog-wp.php`)
- [ ] `pc_get_plugin_config()` 함수의 버전 번호 업데이트
- [ ] 변경사항 문서화 (CHANGELOG 또는 릴리스 노트)
- [ ] 코드 테스트 완료
- [ ] 도메인 변경 테스트 완료 (`verify-domain-agnostic.php` 실행)
- [ ] 하드코딩된 URL 확인 (없어야 함)
- [ ] Git 태그 생성 및 푸시

### 🔄 버전 관리 (Version Management)

버전 번호는 다음 위치에서 관리됩니다:

1. **플러그인 헤더** (`dw-catalog-wp.php`):
   ```php
   * Version: 1.0.0
   ```

2. **중앙 설정 함수** (`pc_get_plugin_config()`):
   ```php
   'plugin_version' => '1.0.0',
   ```

**중요:** 릴리스 전에 두 위치 모두 업데이트해야 합니다.

### 🌐 도메인 변경 친화적 설계 (Domain-Change Friendly)

이 플러그인은 도메인 변경에 완전히 독립적으로 설계되었습니다:

- ✅ 하드코딩된 사이트 URL 없음
- ✅ WordPress 함수 사용 (`site_url()`, `home_url()`, `admin_url()`)
- ✅ 중앙 설정 시스템 (`pc_get_plugin_config()`)
- ✅ 도메인 독립적인 GitHub 업데이터

자세한 내용은 [DOMAIN-CHANGE-GUIDE.md](DOMAIN-CHANGE-GUIDE.md)를 참조하세요.

### 📚 문서 (Documentation)

- [도메인 변경 가이드](DOMAIN-CHANGE-GUIDE.md) - Domain Change Guide
- [구현 요약](IMPLEMENTATION-SUMMARY.md) - Implementation Summary
- [도메인 독립성 검증 스크립트](verify-domain-agnostic.php) - Verification Script

### 🛠️ 기술 스택 (Tech Stack)

- **PHP**: 7.4+
- **WordPress**: 5.0+
- **GitHub API**: Releases API
- **배포**: GitHub Actions

### 📝 라이선스 (License)

GPL v2 or later

### 👥 기여 (Contributing)

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### 📞 지원 (Support)

이슈가 있으시면 [GitHub Issues](https://github.com/dasomweb/dw-catalog-wp/issues)에 등록해주세요.

---

**Made with ❤️ by Dasom Web**
