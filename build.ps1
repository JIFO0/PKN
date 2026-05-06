#BUILDER VERSION 2.11

#Requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
 
# Always pause before exit so the window stays open
function Exit-Script([int]$Code = 0) {
    Write-Host ""
    Write-Host "Press any key to close..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit $Code
	
}

function Ensure-GhConfigured {
    # Check gh is installed
    if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
        Write-Host ""
        Write-Host "GitHub CLI (gh) is not installed." -ForegroundColor Yellow
        Write-Host "Download it from: https://cli.github.com/" -ForegroundColor Cyan
        Write-Host "After installing, re-run this script."
        Exit-Script 1
    }

    # Check if authenticated
    $authStatus = & gh auth status 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "GitHub CLI is not authenticated. Starting login..." -ForegroundColor Yellow
        gh auth login
        if ($LASTEXITCODE -ne 0) {
            Write-Host "ERROR: GitHub authentication failed." -ForegroundColor Red
            Exit-Script 1
        }
        Write-Host "Authentication successful." -ForegroundColor Green
    }

    # Check if inside a git repo with a remote
    $remoteUrl = git remote get-url origin 2>&1
    if ($LASTEXITCODE -ne 0 -or -not $remoteUrl) {
        Write-Host ""
        Write-Host "ERROR: No 'origin' remote found in this repository." -ForegroundColor Red
        Write-Host "Run: git remote add origin https://github.com/YOUR_USER/YOUR_REPO.git" -ForegroundColor Cyan
        Exit-Script 1
    }

    Write-Host "GitHub CLI ready. Remote: $remoteUrl" -ForegroundColor Green
}

try {
 
# Resolve root dir relative to THIS script file â€” works both when
# double-clicked and when run from a PowerShell session.
$ScriptDir = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$RootDir    = (Resolve-Path $ScriptDir).Path
$PluginMain = Join-Path $RootDir "PKN-backend.php"
$BuildsDir  = Join-Path $RootDir "builds"
$DistDir    = Join-Path $RootDir ".dist"
 
Write-Host "Root  : $RootDir"
Write-Host "Plugin: $PluginMain"
 
if (-not (Test-Path $PluginMain)) {
    Write-Host "ERROR: Plugin file not found: $PluginMain" -ForegroundColor Red
    Exit-Script 1
}
 
New-Item -ItemType Directory -Force -Path $BuildsDir | Out-Null
New-Item -ItemType Directory -Force -Path $DistDir   | Out-Null
 
# Parse version from plugin header  (line like:  * Version: 1.2.3)
$VersionLine = Get-Content $PluginMain | Where-Object { $_ -match '^\s*\*\s*Version:\s*' } | Select-Object -First 1
$Version     = ($VersionLine -replace '^\s*\*\s*Version:\s*', '').Trim()
 
if (-not $Version) {
    Write-Host "ERROR: Unable to parse plugin version from $PluginMain" -ForegroundColor Red
    Exit-Script 1
}
 
Write-Host "Version: $Version"
 
$DateUtc     = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
$SafeVersion  = $Version -replace '[\s/\\]', '-'
$ZipName     = "pkn-backend-$SafeVersion.zip"
$ZipPath     = Join-Path $BuildsDir $ZipName
 
# Remove existing zip if present
if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }
 
# Mirror source to .dist\pkn-backend\ excluding unwanted entries
$DestDir = Join-Path $DistDir "pkn-backend"
if (Test-Path $DestDir) { Remove-Item $DestDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $DestDir | Out-Null
 
# Move existing PKN.zip into builds/old/ renamed to its version before overwriting
$CurrentZip = Join-Path $BuildsDir "PKN.zip"
if (Test-Path $CurrentZip) {
    $OldDir = Join-Path $BuildsDir "old"
    New-Item -ItemType Directory -Force -Path $OldDir | Out-Null

    # Read old version from existing latest.json if available, else use timestamp
    $OldManifest = Join-Path $BuildsDir "latest.json"
    if (Test-Path $OldManifest) {
        $OldVersion = (Get-Content $OldManifest | ConvertFrom-Json).version
        $OldZipName = "pkn-backend-$($OldVersion -replace '[\s/\\]','-').zip"
    } else {
        $OldZipName = "pkn-backend-unknown-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"
    }

    $OldZipDest = Join-Path $OldDir $OldZipName
    if (-not (Test-Path $OldZipDest)) {
        Move-Item -Path $CurrentZip -Destination $OldZipDest
        Write-Host "Archived previous build: $OldZipDest" -ForegroundColor DarkGray
    } else {
        Remove-Item $CurrentZip -Force
        Write-Host "Old version already archived, removed duplicate." -ForegroundColor DarkGray
    }
}

# Stage the exact files/folders that belong in the plugin zip
$DestDir = Join-Path $DistDir "pkn-backend"
if (Test-Path $DestDir) { Remove-Item $DestDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $DestDir | Out-Null

# Copy main plugin file
Copy-Item -Path $PluginMain -Destination (Join-Path $DestDir "PKN-backend.php") -Force

# Copy plugin folders
foreach ($folder in @('templates', 'lang', 'includes', 'assets')) {
    $src = Join-Path $RootDir $folder
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $DestDir $folder) -Recurse -Force
    } else {
        Write-Host "WARNING: Folder not found, skipping: $folder" -ForegroundColor Yellow
    }
}

# Create zip — always named PKN.zip
$ZipPath = Join-Path $BuildsDir "PKN.zip"
Write-Host "Compressing..."
Compress-Archive -Path (Join-Path $DestDir '*') -DestinationPath $ZipPath -Force
 
# Write latest.json manifest
$Manifest = @"
{
  "name": "PKN Backend",
  "slug": "pkn-backend",
  "version": "$Version",
  "built_at": "$DateUtc",
  "package_url": "https://github.com/JIFO0/PKN/raw/main/builds/PKN.zip",
  "details_url": "https://github.com/JIFO0/PKN",
  "requires_wp": "6.0",
  "requires_php": "7.4",
  "tested": "6.5",
  "description": "Automated build from GitHub builds folder.",
  "changelog": "See commit history for details."
}
"@
 
$ManifestPath = Join-Path $BuildsDir "latest.json"
$Manifest | Set-Content -Path $ManifestPath -Encoding UTF8
 
Write-Host ""
Write-Host "Build created   : $ZipPath" -ForegroundColor Green
Write-Host "Manifest updated: $ManifestPath" -ForegroundColor Green
 
} catch {
    Write-Host ""
    Write-Host "ERROR: $_" -ForegroundColor Red
    Exit-Script 1
}
 
Ensure-GhConfigured

$Tag = "v$SafeVersion"
Write-Host ""
Write-Host "Uploading to GitHub release: $Tag ..." -ForegroundColor Cyan

$ErrorActionPreference = 'Continue'
$releaseExists = & gh release view $Tag 2>&1
$ErrorActionPreference = 'Stop'
if ($LASTEXITCODE -ne 0) {
    Write-Host "Creating new release $Tag..."
    gh release create $Tag $ZipPath --title "PKN Backend $Version" --notes "Automated release for $Version"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Failed to create GitHub release." -ForegroundColor Red
        Write-Host "Details: $releaseExists" -ForegroundColor Red
        Exit-Script 1
    }
} else {
    Write-Host "Release $Tag already exists, uploading asset..."
    gh release upload $Tag $ZipPath --clobber
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Failed to upload asset to release $Tag." -ForegroundColor Red
        Exit-Script 1
    }
}

Write-Host "Release asset uploaded: $Tag" -ForegroundColor Green
 
Exit-Script 0
