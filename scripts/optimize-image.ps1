# Optimise a public image for the web: downscale to a max width and re-encode
# as JPEG at a set quality. Downscaling only (never upscales), so it can never
# introduce pixelation - it just drops detail invisible at display size.
#
# Usage:
#   powershell -File scripts/optimize-image.ps1 -Path public/images/pieter-du-plessis.jpg
#   powershell -File scripts/optimize-image.ps1 -Path public/images/x.jpg -MaxWidth 1200 -Quality 82
#
# The original is backed up alongside the file as <name>.original.jpg (once).

param(
    [Parameter(Mandatory = $true)][string]$Path,
    [int]$MaxWidth = 1000,
    [int]$Quality = 85
)

Add-Type -AssemblyName System.Drawing

$full = (Resolve-Path $Path).Path
$beforeKB = [math]::Round((Get-Item $full).Length / 1KB)

# Load via a memory copy so the source file is not left locked.
$bytes = [System.IO.File]::ReadAllBytes($full)
$ms = New-Object System.IO.MemoryStream (, $bytes)
$src = [System.Drawing.Image]::FromStream($ms)

if ($src.Width -le $MaxWidth) {
    Write-Host "Already $($src.Width)px wide (<= $MaxWidth). Re-encoding only."
    $newW = $src.Width
    $newH = $src.Height
} else {
    $newW = $MaxWidth
    $newH = [int][math]::Round($src.Height * ($MaxWidth / $src.Width))
}

# High-quality downscale.
$dst = New-Object System.Drawing.Bitmap $newW, $newH
$g = [System.Drawing.Graphics]::FromImage($dst)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
$g.DrawImage($src, 0, 0, $newW, $newH)
$g.Dispose()

# Back up the original once, into a sibling _originals folder so the full-size
# file is never served or committed from public/. (*.original.jpg is also
# git-ignored as a belt-and-braces guard.)
$backupDir = Join-Path (Split-Path $full -Parent) '_originals'
if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }
$backup = Join-Path $backupDir ([System.IO.Path]::GetFileNameWithoutExtension($full) + '.original.jpg')
if (-not (Test-Path $backup)) {
    [System.IO.File]::WriteAllBytes($backup, $bytes)
    Write-Host "Backed up original -> _originals/$([System.IO.Path]::GetFileName($backup))"
}

$src.Dispose()
$ms.Dispose()

# Encode as JPEG at the requested quality.
$jpeg = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() |
    Where-Object { $_.MimeType -eq 'image/jpeg' }
$params = New-Object System.Drawing.Imaging.EncoderParameters 1
$params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter(
    [System.Drawing.Imaging.Encoder]::Quality, [int64]$Quality)

$dst.Save($full, $jpeg, $params)
$dst.Dispose()

$afterKB = [math]::Round((Get-Item $full).Length / 1KB)
Write-Host "Optimised $([System.IO.Path]::GetFileName($full)): ${newW}x${newH}, q$Quality"
Write-Host "  $beforeKB KB -> $afterKB KB  ($([math]::Round((1 - $afterKB/$beforeKB) * 100))% smaller)"
