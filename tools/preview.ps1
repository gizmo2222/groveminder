<#
.SYNOPSIS
  Preview a GroveMinder site on this computer.

.DESCRIPTION
  Builds the site in a temporary folder the same way the deploy does (the
  product's pages plus one site-config.js) and serves it on localhost.

  PHP is not run, so emails and calendar sync do not work in the preview.
  With -Theme or -Site the pages talk to that site's real Firebase project:
  a form you submit, or a change you save in the admin, is real.

.EXAMPLE
  .\tools\preview.ps1
  The demo (no backend, nothing leaves the browser).

.EXAMPLE
  .\tools\preview.ps1 -Theme ..\holly-poppins
  A theme repo laid over this product.

.EXAMPLE
  .\tools\preview.ps1 -Site
  This folder's own site-config.js.
#>
param(
    [string]$Theme,
    [switch]$Site,
    [int]$Port = 8080
)
$ErrorActionPreference = 'Stop'

$product = Split-Path -Parent $PSScriptRoot
$pages   = 'site-core.js', 'demo-firebase.js', 'index.html', 'admin.html', 'email-templates.json'
$php     = 'hp-lib.php', 'mailer.php', 'cal-push.php', 'ical-proxy.php'

$out = Join-Path $env:TEMP 'groveminder-preview'
if (Test-Path $out) { Remove-Item -Recurse -Force $out }
New-Item -ItemType Directory -Force $out | Out-Null

foreach ($f in $pages) { Copy-Item (Join-Path $product $f) $out }

if ($Theme) {
    $themeDir = (Resolve-Path $Theme).Path
    if (-not (Test-Path (Join-Path $themeDir 'site-config.js'))) { throw "No site-config.js in $themeDir" }
    # The theme's own files: its config and images. Product file names are
    # skipped, so a theme can never replace the product's pages.
    Get-ChildItem -File $themeDir |
        Where-Object { $_.Name -notmatch '^\.' -and $_.Extension -ne '.md' -and
                       ($pages + $php + 'firestore.rules') -notcontains $_.Name } |
        Copy-Item -Destination $out
    $what = "theme $themeDir"
} elseif ($Site) {
    Copy-Item (Join-Path $product 'site-config.js') $out
    $what = 'site-config.js'
} else {
    Copy-Item (Join-Path $product 'site-config.demo.js') (Join-Path $out 'site-config.js')
    $what = 'the demo'
}

$python = Get-Command python -ErrorAction SilentlyContinue
if (-not $python) { $python = Get-Command py -ErrorAction SilentlyContinue }
if (-not $python) { throw 'Python was not found. Install it, or serve this folder another way: ' + $out }

Write-Host "Previewing $what at http://localhost:$Port/  (admin: http://localhost:$Port/admin.html)"
Write-Host 'Press Ctrl+C to stop.'
& $python.Source -m http.server $Port --bind 127.0.0.1 --directory $out
