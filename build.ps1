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
 
Exit-Script 0
