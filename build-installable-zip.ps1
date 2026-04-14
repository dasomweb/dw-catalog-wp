# 깃허브에 푸시하는 플러그인 파일만 담은 설치용 ZIP (GitHub 릴리즈와 동일 구성)
# Run from repo root. Output: dw-catalog-wp-<version>.zip
# ZIP filename = versioned; ZIP contents = single root folder "dw-catalog-wp/" so install dir is always wp-content/plugins/dw-catalog-wp/

$ErrorActionPreference = "Stop"
$config = Get-Content "dw-catalog-wp.php" -Raw
if ($config -match "plugin_version'\s*=>\s*'([^']+)'") { $ver = $Matches[1] } else { $ver = "1.0.0" }
$buildDir = "build"
$pluginSlug = "dw-catalog-wp"
$pluginDir = "$buildDir\$pluginSlug"

if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null

# PDF export: install Composer deps so vendor/ is included
if (Test-Path "composer.json") {
  if (Get-Command composer -ErrorAction SilentlyContinue) {
    composer install --no-dev --optimize-autoloader 2>&1 | Out-Null
  }
}

# 푸시한 파일만 복사 (release.yml과 동일)
Copy-Item "dw-catalog-wp.php" $pluginDir
Copy-Item "index.php" $pluginDir
Copy-Item "includes" $pluginDir -Recurse -Force
Copy-Item "assets" $pluginDir -Recurse -Force
if (Test-Path "vendor") { Copy-Item "vendor" $pluginDir -Recurse -Force }
if (Test-Path "uninstall.php") { Copy-Item "uninstall.php" $pluginDir }

# WordPress용 요약
@"
=== DW Catalog WP ===
Requires at least: 5.0
Requires PHP: 7.4
Stable tag: $ver
License: GPLv2 or later

Product catalog post type, custom fields, bulk import, GitHub updates.
"@ | Set-Content "$pluginDir\README.txt" -Encoding UTF8

# ZIP filename = versioned (dw-catalog-wp-1.5.6.zip). Contents = single root folder "dw-catalog-wp/" so WordPress always installs to wp-content/plugins/dw-catalog-wp/
$zipName = "$pluginSlug-$ver.zip"
$zipPath = (Join-Path (Get-Location) $zipName)
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path "$buildDir\$pluginSlug" -DestinationPath $zipPath -Force
Remove-Item $buildDir -Recurse -Force

Write-Host "Created: $zipName (version $ver)" -ForegroundColor Green
Write-Host "Upload this file in WordPress: Plugins > Add New > Upload Plugin"
Write-Host "Install folder will always be: wp-content/plugins/dw-catalog-wp/"
