# 깃허브에 푸시하는 플러그인 파일만 담은 설치용 ZIP (GitHub 릴리즈와 동일 구성)
# Run from repo root. Output: dw-product-catalog.zip

$ErrorActionPreference = "Stop"
$config = Get-Content "dw-product-catalog.php" -Raw
if ($config -match "plugin_version'\s*=>\s*'([^']+)'") { $ver = $Matches[1] } else { $ver = "1.0.0" }
$buildDir = "build"
$pluginDir = "$buildDir\plugin"

if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null

# 푸시한 파일만 복사 (release.yml과 동일)
Copy-Item "dw-product-catalog.php" $pluginDir
Copy-Item "index.php" $pluginDir
Copy-Item "includes" $pluginDir -Recurse -Force
Copy-Item "assets" $pluginDir -Recurse -Force
if (Test-Path "uninstall.php") { Copy-Item "uninstall.php" $pluginDir }

# WordPress용 요약
@"
=== DW Product Catalog ===
Requires at least: 5.0
Requires PHP: 7.4
Stable tag: $ver
License: GPLv2 or later

Product catalog post type, custom fields, bulk import, GitHub updates.
"@ | Set-Content "$pluginDir\README.txt" -Encoding UTF8

# WordPress uses the ZIP filename (minus .zip) as the plugin folder.
# So we must output "dw-product-catalog.zip" and put FILES at zip root (no inner folder),
# so after extract we get: wp-content/plugins/dw-product-catalog/dw-product-catalog.php
$zipName = "dw-product-catalog.zip"
$zipPath = (Join-Path (Get-Location) $zipName)
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path "$pluginDir\*" -DestinationPath $zipPath -Force
Remove-Item $buildDir -Recurse -Force

Write-Host "Created: $zipName (version $ver)" -ForegroundColor Green
Write-Host "Upload this file in WordPress: Plugins > Add New > Upload Plugin"
Write-Host "After install, path will be: wp-content/plugins/dw-product-catalog/dw-product-catalog.php"
