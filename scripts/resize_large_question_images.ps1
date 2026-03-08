[CmdletBinding()]
param(
    [string]$ImageDir = "public/upload/domanda/image",
    [int]$MaxDimension = 1600,
    [int]$JpegQuality = 82,
    [long]$MinBytesToReencode = 1572864
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Drawing

function Get-JpegCodec {
    return [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() |
        Where-Object { $_.MimeType -eq "image/jpeg" } |
        Select-Object -First 1
}

function Save-Jpeg {
    param(
        [Parameter(Mandatory = $true)]
        [System.Drawing.Bitmap]$Bitmap,
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [int]$Quality
    )

    $codec = Get-JpegCodec
    $encoder = [System.Drawing.Imaging.Encoder]::Quality
    $params = New-Object System.Drawing.Imaging.EncoderParameters 1
    $params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter($encoder, [long]$Quality)
    $Bitmap.Save($Path, $codec, $params)
    $params.Dispose()
}

function New-ResizedBitmap {
    param(
        [Parameter(Mandatory = $true)]
        [System.Drawing.Image]$Image,
        [Parameter(Mandatory = $true)]
        [int]$TargetWidth,
        [Parameter(Mandatory = $true)]
        [int]$TargetHeight
    )

    $bitmap = New-Object System.Drawing.Bitmap $TargetWidth, $TargetHeight
    $bitmap.SetResolution($Image.HorizontalResolution, $Image.VerticalResolution)

    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.DrawImage($Image, 0, 0, $TargetWidth, $TargetHeight)
    $graphics.Dispose()

    return $bitmap
}

$root = Resolve-Path $ImageDir
$files = Get-ChildItem $root -File
$processed = 0
$skipped = 0
$savedBytes = [long]0
$errors = @()

foreach ($file in $files) {
    $ext = $file.Extension.ToLowerInvariant()
    if ($ext -notin @(".jpg", ".jpeg", ".png")) {
        $skipped++
        continue
    }

    try {
        $image = [System.Drawing.Image]::FromFile($file.FullName)
        $width = $image.Width
        $height = $image.Height
        $maxSide = [Math]::Max($width, $height)

        $needsResize = $maxSide -gt $MaxDimension
        $needsReencode = ($ext -in @(".jpg", ".jpeg")) -and ($file.Length -gt $MinBytesToReencode)

        if (-not $needsResize -and -not $needsReencode) {
            $image.Dispose()
            $skipped++
            continue
        }

        $targetWidth = $width
        $targetHeight = $height
        if ($needsResize) {
            $scale = $MaxDimension / [double]$maxSide
            $targetWidth = [Math]::Max(1, [int][Math]::Round($width * $scale))
            $targetHeight = [Math]::Max(1, [int][Math]::Round($height * $scale))
        }

        $bitmap = if ($needsResize) {
            New-ResizedBitmap -Image $image -TargetWidth $targetWidth -TargetHeight $targetHeight
        } else {
            New-Object System.Drawing.Bitmap $image
        }

        $image.Dispose()

        $tempPath = "$($file.FullName).tmp"
        if (Test-Path $tempPath) {
            Remove-Item $tempPath -Force
        }

        if ($ext -in @(".jpg", ".jpeg")) {
            Save-Jpeg -Bitmap $bitmap -Path $tempPath -Quality $JpegQuality
        } else {
            $bitmap.Save($tempPath, [System.Drawing.Imaging.ImageFormat]::Png)
        }

        $bitmap.Dispose()

        $newInfo = Get-Item $tempPath
        $savedBytes += [Math]::Max(0, $file.Length - $newInfo.Length)

        Move-Item $tempPath $file.FullName -Force
        $processed++
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
