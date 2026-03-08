[CmdletBinding()]
param(
    [string]$ImageDir = "public/upload/domanda/image",
    [int]$MaxDimension = 300,
    [int]$JpegQuality = 6
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ffmpeg = (Get-Command ffmpeg -ErrorAction Stop).Source
$ffprobe = (Get-Command ffprobe -ErrorAction Stop).Source

function Get-RasterDimensions {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    $output = & $ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0:s=x -- $Path
    if ($LASTEXITCODE -ne 0 -or -not $output) {
        return $null
    }

    $value = ($output | Select-Object -First 1).Trim()
    if ($value -match '^(?<w>\d+)x(?<h>\d+)$') {
        return @{
            Width = [int]$Matches.w
            Height = [int]$Matches.h
        }
    }

    return $null
}

function Invoke-FfmpegResize {
    param(
        [Parameter(Mandatory = $true)]
        [System.IO.FileInfo]$File,
        [Parameter(Mandatory = $true)]
        [int]$MaxSide,
        [Parameter(Mandatory = $true)]
        [int]$Quality
    )

    $ext = $File.Extension.ToLowerInvariant()
    $tempPath = Join-Path $File.DirectoryName ($File.BaseName + ".tmp" + $File.Extension)
    if (Test-Path $tempPath) {
        Remove-Item $tempPath -Force
    }

    if ($ext -in @(".jpg", ".jpeg")) {
        $args = @(
            "-y", "-loglevel", "error",
            "-i", $File.FullName,
            "-vf", "scale=${MaxSide}:${MaxSide}:force_original_aspect_ratio=decrease",
            "-q:v", $Quality.ToString(),
            $tempPath
        )
    } elseif ($ext -eq ".png") {
        $args = @(
            "-y", "-loglevel", "error",
            "-i", $File.FullName,
            "-vf", "scale=${MaxSide}:${MaxSide}:force_original_aspect_ratio=decrease",
            "-compression_level", "9",
            $tempPath
        )
    } elseif ($ext -eq ".gif") {
        $args = @(
            "-y", "-loglevel", "error",
            "-i", $File.FullName,
            "-filter_complex", "fps=10,scale=${MaxSide}:${MaxSide}:force_original_aspect_ratio=decrease:flags=lanczos,split[s0][s1];[s0]palettegen=max_colors=96[p];[s1][p]paletteuse=dither=bayer:bayer_scale=3",
            $tempPath
        )
    } elseif ($ext -eq ".webp") {
        $args = @(
            "-y", "-loglevel", "error",
            "-i", $File.FullName,
            "-vf", "scale=${MaxSide}:${MaxSide}:force_original_aspect_ratio=decrease",
            "-q:v", $Quality.ToString(),
            $tempPath
        )
    } else {
        return $null
    }

    & $ffmpeg @args
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $tempPath)) {
        throw "ffmpeg resize failed for $($File.Name)"
    }

    return Get-Item $tempPath
}

function Update-SvgDimensions {
    param(
        [Parameter(Mandatory = $true)]
        [System.IO.FileInfo]$File,
        [Parameter(Mandatory = $true)]
        [int]$MaxSide
    )

    $raw = Get-Content $File.FullName -Raw
    $raw = [regex]::Replace($raw, '<metadata>[\s\S]*?</metadata>', '')
    $raw = [regex]::Replace($raw, '<!--[\s\S]*?-->', '')

    $width = $null
    $height = $null

    if ($raw -match 'width="(?<w>[0-9.]+)"') {
        $width = [double]$Matches.w
    }
    if ($raw -match 'height="(?<h>[0-9.]+)"') {
        $height = [double]$Matches.h
    }
    if (($null -eq $width -or $null -eq $height) -and $raw -match 'viewBox="[^"]*?\s(?<w>[0-9.]+)\s(?<h>[0-9.]+)"') {
        $width = [double]$Matches.w
        $height = [double]$Matches.h
    }
    if ($null -eq $width -or $null -eq $height) {
        return $false
    }

    $scale = [Math]::Min(1.0, $MaxSide / [double][Math]::Max($width, $height))
    $newWidth = [Math]::Round($width * $scale, 3)
    $newHeight = [Math]::Round($height * $scale, 3)
    $newWidthText = [string]$newWidth
    $newHeightText = [string]$newHeight

    if ($raw -match 'width="(?<w>[0-9.]+)"') {
        $raw = [regex]::Replace($raw, 'width="[0-9.]+"', "width=""$newWidthText""", 1)
    }
    if ($raw -match 'height="(?<h>[0-9.]+)"') {
        $raw = [regex]::Replace($raw, 'height="[0-9.]+"', "height=""$newHeightText""", 1)
    }

    $raw = [regex]::Replace($raw, '(?<=-?\d+\.\d)\d+', '')
    $raw = [regex]::Replace($raw, '>\s+<', '><')
    $raw = [regex]::Replace($raw, '\s{2,}', ' ')
    $raw = $raw.Trim()

    Set-Content -Path $File.FullName -Value $raw -NoNewline
    return $true
}

$root = Resolve-Path $ImageDir
$files = Get-ChildItem $root -File
$processed = 0
$skipped = 0
$savedBytes = [long]0
$errors = @()

foreach ($file in $files) {
    $ext = $file.Extension.ToLowerInvariant()

    try {
        if ($ext -in @(".jpg", ".jpeg", ".png", ".gif", ".webp")) {
            $dimensions = Get-RasterDimensions -Path $file.FullName
            if ($null -eq $dimensions) {
                throw "unable to read dimensions"
            }

            $maxSide = [Math]::Max($dimensions.Width, $dimensions.Height)
            if ($maxSide -le $MaxDimension) {
                $skipped++
                continue
            }

            $originalLength = $file.Length
            $tempFile = Invoke-FfmpegResize -File $file -MaxSide $MaxDimension -Quality $JpegQuality
            $savedBytes += [Math]::Max(0, $originalLength - $tempFile.Length)
            Move-Item $tempFile.FullName $file.FullName -Force
            $processed++
            continue
        }

        if ($ext -eq ".svg") {
            $originalLength = $file.Length
            $changed = Update-SvgDimensions -File $file -MaxSide $MaxDimension
            if (-not $changed) {
                $skipped++
                continue
            }

            $newLength = (Get-Item $file.FullName).Length
            $savedBytes += [Math]::Max(0, $originalLength - $newLength)
            $processed++
            continue
        }

        $skipped++
    } catch {
        $errors += [PSCustomObject]@{
            file = $file.Name
            error = $_.Exception.Message
        }
    }
}

[PSCustomObject]@{
    image_dir = $root.Path
    processed = $processed
    skipped = $skipped
    saved_mb = [Math]::Round($savedBytes / 1MB, 2)
    errors = $errors.Count
}

if ($errors.Count -gt 0) {
    $errors | Select-Object -First 20
}
