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
 
$ExactExcludes   = @('.git', '.dist', 'builds', '.vscode', '.vs', '.ignore', '..test')
$PatternExcludes = @('*.zip', '.gitignore', 'overview v2.txt', 'overwiew v2.txt', 'build.ps1', 'Package-build.sh')
 
Get-ChildItem -Path $RootDir -Force | Where-Object {
    $name = $_.Name
    $excluded = $false
    foreach ($ex in $ExactExcludes)   { if ($name -eq $ex)   { $excluded = $true; break } }
    foreach ($ex in $PatternExcludes) { if ($name -like $ex) { $excluded = $true; break } }
    -not $excluded
} | ForEach-Object {
    $dest = Join-Path $DestDir $_.Name
    if ($_.PSIsContainer) {
        Copy-Item -Path $_.FullName -Destination $dest -Recurse -Force
    } else {
        Copy-Item -Path $_.FullName -Destination $dest -Force
    }
}
 
# Create zip archive
Write-Host "Compressing..."
Compress-Archive -Path $DestDir -DestinationPath $ZipPath -Force
 
# Write latest.json manifest
$Manifest = @"
{
  "name": "PKN Backend",
  "slug": "pkn-backend",
  "version": "$Version",
  "built_at": "$DateUtc",
  "package_url": "https://github.com/JIFO0/PKN/raw/main/builds/$ZipName",
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
