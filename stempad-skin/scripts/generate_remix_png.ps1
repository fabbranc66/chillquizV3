$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Drawing

$root = Split-Path -Parent $PSScriptRoot
$outDir = Join-Path $root "preview"
$outFile = Join-Path $outDir "stempad-remix-v2.png"

if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Force -Path $outDir | Out-Null
}

$width = 2048
$height = 1152

$bmp = [System.Drawing.Bitmap]::new($width, $height)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$g.Clear([System.Drawing.Color]::FromArgb(4, 4, 6))

function Brush-Html([string]$hex) {
    [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml($hex))
}

function Pen-Html([string]$hex, [float]$w = 1) {
    [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml($hex), $w)
}

function RectF([float]$x, [float]$y, [float]$w, [float]$h) {
    [System.Drawing.RectangleF]::new($x, $y, $w, $h)
}

function PointF([float]$x, [float]$y) {
    [System.Drawing.PointF]::new($x, $y)
}

function New-RoundPath([float]$x, [float]$y, [float]$w, [float]$h, [float]$r) {
    $p = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $d = $r * 2
    $p.AddArc($x, $y, $d, $d, 180, 90)
    $p.AddArc($x + $w - $d, $y, $d, $d, 270, 90)
    $p.AddArc($x + $w - $d, $y + $h - $d, $d, $d, 0, 90)
    $p.AddArc($x, $y + $h - $d, $d, $d, 90, 90)
    $p.CloseFigure()
    return $p
}

function Fill-RoundGradient {
    param(
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H,
        [float]$R,
        [string]$Top,
        [string]$Bottom,
        [string]$Border,
        [string]$Glow = ""
    )

    if ($Glow) {
        for ($i = 16; $i -ge 1; $i--) {
            $alpha = [Math]::Max(4, 34 - $i)
            $glowBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($alpha, [System.Drawing.ColorTranslator]::FromHtml($Glow)))
            $gp = New-RoundPath ($X - $i) ($Y - $i) ($W + ($i * 2)) ($H + ($i * 2)) ($R + $i)
            $g.FillPath($glowBrush, $gp)
            $gp.Dispose()
            $glowBrush.Dispose()
        }
    }

    $path = New-RoundPath $X $Y $W $H $R
    $brush = [System.Drawing.Drawing2D.LinearGradientBrush]::new(
        (PointF $X $Y),
        (PointF $X ($Y + $H)),
        [System.Drawing.ColorTranslator]::FromHtml($Top),
        [System.Drawing.ColorTranslator]::FromHtml($Bottom)
    )
    $pen = Pen-Html $Border 3
    $g.FillPath($brush, $path)
    $g.DrawPath($pen, $path)

    $highlight = RectF ($X + 7) ($Y + 7) ($W - 14) ($H * 0.34)
    $hPath = New-RoundPath $highlight.X $highlight.Y $highlight.Width $highlight.Height ($R * 0.7)
    $hBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(72, 255, 255, 255))
    $g.FillPath($hBrush, $hPath)

    $hBrush.Dispose()
    $hPath.Dispose()
    $brush.Dispose()
    $pen.Dispose()
    $path.Dispose()
}

function Draw-CenteredText {
    param(
        [string]$Text,
        [System.Drawing.Font]$Font,
        [string]$Color,
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H
    )

    $sf = [System.Drawing.StringFormat]::new()
    $sf.Alignment = [System.Drawing.StringAlignment]::Center
    $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
    $brush = Brush-Html $Color
    $g.DrawString($Text, $Font, $brush, (RectF $X $Y $W $H), $sf)
    $brush.Dispose()
    $sf.Dispose()
}

function Draw-Pad {
    param(
        [float]$X,
        [float]$Y,
        [string]$Top,
        [string]$Bottom,
        [string]$Border,
        [string]$Glow,
        [string]$Text = ""
    )

    Fill-RoundGradient $X $Y 106 106 22 $Top $Bottom $Border $Glow
    if ($Text) {
        Draw-CenteredText $Text $script:fontPad "#F7F9FB" $X $Y 106 106
    }
}

function Draw-FxButton {
    param(
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H,
        [string]$Text
    )

    Fill-RoundGradient $X $Y $W $H 20 "#FFD59C" "#46226F" "#FF8A2C" "#A53EFF"
    Draw-CenteredText $Text $script:fontButton "#FFF8F2" $X $Y $W $H
}

function Draw-StemButton {
    param(
        [float]$X,
        [float]$Y,
        [string]$Text
    )

    Fill-RoundGradient $X $Y 104 90 20 "#D2C6FF" "#342060" "#9272FF" "#6A40FF"
    Draw-CenteredText $Text $script:fontMini "#F7F4FF" $X $Y 104 90
}

function Draw-VerticalLeds {
    param([float]$X, [float]$Y)
    for ($i = 0; $i -lt 11; $i++) {
        $yy = $Y + ($i * 64)
        Fill-RoundGradient $X $yy 28 42 7 "#858585" "#4C4C4C" "#989898"
    }
}

function Draw-Slider {
    param(
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H,
        [string]$KnobTop,
        [string]$KnobBottom,
        [string]$KnobBorder
    )

    $trackBrush = Brush-Html "#595959"
    $trackPen = Pen-Html "#797979" 1.5
    $track = RectF $X $Y $W $H
    $trackPath = New-RoundPath $X $Y $W $H 10
    $g.FillPath($trackBrush, $trackPath)
    $g.DrawPath($trackPen, $trackPath)
    $trackBrush.Dispose()
    $trackPen.Dispose()
    $trackPath.Dispose()

    for ($i = 1; $i -lt 6; $i++) {
        $lineY = $Y + ($i * ($H / 6))
        $pen = Pen-Html "#3D3D3D" 2
        $g.DrawLine($pen, $X + 3, $lineY, $X + $W - 3, $lineY)
        $pen.Dispose()
    }

    Fill-RoundGradient ($X - 2) ($Y + 4) ($W + 4) 110 14 $KnobTop $KnobBottom $KnobBorder $KnobBorder
}

$fontTitle = [System.Drawing.Font]::new("Segoe UI", 31, [System.Drawing.FontStyle]::Bold)
$fontSection = [System.Drawing.Font]::new("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
$fontHeader = [System.Drawing.Font]::new("Segoe UI", 16, [System.Drawing.FontStyle]::Bold)
$fontBpm = [System.Drawing.Font]::new("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
$fontMini = [System.Drawing.Font]::new("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
$fontButton = [System.Drawing.Font]::new("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
$fontPad = [System.Drawing.Font]::new("Segoe UI", 19, [System.Drawing.FontStyle]::Bold)
$fontBig = [System.Drawing.Font]::new("Segoe UI", 38, [System.Drawing.FontStyle]::Bold)

$script:fontMini = $fontMini
$script:fontButton = $fontButton
$script:fontPad = $fontPad

# background aura
for ($i = 0; $i -lt 12; $i++) {
    $alpha = 14 - $i
    $brush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($alpha, 0, 185, 255))
    $g.FillEllipse($brush, 320 - ($i * 30), 180 - ($i * 18), 520 + ($i * 60), 260 + ($i * 35))
    $brush.Dispose()
}
for ($i = 0; $i -lt 12; $i++) {
    $alpha = 12 - $i
    $brush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($alpha, 255, 42, 99))
    $g.FillEllipse($brush, 1210 - ($i * 28), 180 - ($i * 18), 500 + ($i * 56), 250 + ($i * 34))
    $brush.Dispose()
}

# logo and title
Fill-RoundGradient 64 24 176 52 16 "#FF4343" "#B80505" "#FF7171" "#FF2424"
Draw-CenteredText "VIRTUAL DJ" $fontHeader "#FFFFFF" 64 24 176 52
$titleBrush = Brush-Html "#F2F4F8"
$g.DrawString("StemPad Neon Cut", $fontTitle, $titleBrush, 842, 30)
$titleBrush.Dispose()

# top info lines
$penBlue = Pen-Html "#1A4668" 2
$penRed = Pen-Html "#642323" 2
$g.DrawLine($penBlue, 94, 128, 1008, 128)
$g.DrawLine($penRed, 1040, 128, 1956, 128)
$cyan = Brush-Html "#00DFFF"
$white = Brush-Html "#F4F4F5"
$g.DrawString("Trascina un brano in questo lettore per caricarlo", $fontHeader, $cyan, 615, 62)
$g.DrawString("Trascina un brano in questo lettore per caricarlo", $fontHeader, $cyan, 1094, 62)
$g.DrawString("120", $fontBpm, $cyan, 654, 92)
$g.DrawString("BPM", $fontBpm, $white, 760, 92)
$g.DrawString("BPM", $fontBpm, $white, 1118, 92)
$g.DrawString("120", $fontBpm, $cyan, 1235, 92)

# deck grids
$leftX = 62
$rightX = 1658
$topPadsY = 156
$gap = 18
$padCols = @(
    @{ top = "#8BFF59"; bottom = "#159E00"; border = "#7EFF89"; glow = "#1FFF00"; text = @("", "", "", "", "", "", "mic") },
    @{ top = "#FFF07B"; bottom = "#B88B00"; border = "#FFE866"; glow = "#FFD400"; text = @("", "", "", "", "", "", "keys") },
    @{ top = "#90E3FF"; bottom = "#087AD1"; border = "#75CFFF"; glow = "#00A6FF"; text = @("", "", "", "", "", "", "drum") }
)

for ($c = 0; $c -lt 3; $c++) {
    for ($r = 0; $r -lt 7; $r++) {
        $x = $leftX + ($c * (106 + $gap))
        $y = $topPadsY + ($r * (106 + $gap))
        $col = $padCols[$c]
        if ($r -eq 1) {
            Draw-Pad $x $y "#FF93D1" "#AA176C" "#FF6DC1" "#FF2DA3" $col.text[$r]
        } else {
            Draw-Pad $x $y $col.top $col.bottom $col.border $col.glow $col.text[$r]
        }
    }
}

for ($c = 0; $c -lt 3; $c++) {
    for ($r = 0; $r -lt 7; $r++) {
        $x = $rightX + ($c * (106 + $gap))
        $y = $topPadsY + ($r * (106 + $gap))
        $col = $padCols[$c]
        if ($r -eq 1) {
            Draw-Pad $x $y "#FF93D1" "#AA176C" "#FF6DC1" "#FF2DA3" $col.text[$r]
        } else {
            Draw-Pad $x $y $col.top $col.bottom $col.border $col.glow $col.text[$r]
        }
    }
}

# stems fx titles
$g.DrawString("stems FX", $fontSection, $white, 494, 140)
$g.DrawString("stems FX", $fontSection, $white, 1376, 140)
Draw-StemButton 442 190 "mic"
Draw-StemButton 558 190 "keys"
Draw-StemButton 674 190 "drum"
Draw-StemButton 1328 190 "mic"
Draw-StemButton 1444 190 "keys"
Draw-StemButton 1560 190 "drum"

# fx titles
$g.DrawString("FX", $fontSection, $white, 574, 294)
$g.DrawString("FX", $fontSection, $white, 1484, 294)

$leftButtons = @(
    @{ x = 442; y = 344; w = 160; h = 92; t = "ECHO 0.5" },
    @{ x = 618; y = 344; w = 160; h = 92; t = "HP 8" },
    @{ x = 442; y = 454; w = 160; h = 92; t = "ECHO 1" },
    @{ x = 618; y = 454; w = 160; h = 92; t = "SWEEP" },
    @{ x = 442; y = 564; w = 160; h = 92; t = "ECHO 2" },
    @{ x = 442; y = 674; w = 160; h = 92; t = "CHOP" },
    @{ x = 442; y = 784; w = 160; h = 92; t = "ROOM" },
    @{ x = 442; y = 894; w = 160; h = 92; t = "LOOP OUT" }
)

$rightButtons = @(
    @{ x = 1370; y = 344; w = 160; h = 92; t = "HP 8" },
    @{ x = 1546; y = 344; w = 160; h = 92; t = "ECHO 0.5" },
    @{ x = 1370; y = 454; w = 160; h = 92; t = "FILTER" },
    @{ x = 1546; y = 454; w = 160; h = 92; t = "ECHO 1" },
    @{ x = 1546; y = 564; w = 160; h = 92; t = "ECHO 2" },
    @{ x = 1546; y = 674; w = 160; h = 92; t = "CHOP" },
    @{ x = 1546; y = 784; w = 160; h = 92; t = "ROOM" },
    @{ x = 1546; y = 894; w = 160; h = 92; t = "LOOP OUT" }
)

foreach ($b in $leftButtons) { Draw-FxButton $b.x $b.y $b.w $b.h $b.t }
foreach ($b in $rightButtons) { Draw-FxButton $b.x $b.y $b.w $b.h $b.t }

# center
$g.DrawString("cf curve", $fontSection, $white, 944, 140)
Fill-RoundGradient 928 194 112 84 18 "#FF93C3" "#3B1C2A" "#D64D87" "#FF4C96"
Fill-RoundGradient 1054 194 112 84 18 "#95FF70" "#183D16" "#4BD151" "#52FF57"
Fill-RoundGradient 1180 194 130 84 18 "#95FF70" "#183D16" "#4BD151" "#52FF57"
Draw-CenteredText "smooth" $fontHeader "#FFF9FB" 928 194 112 84
Draw-CenteredText "log" $fontHeader "#F7FFF7" 1054 194 112 84
Draw-CenteredText "scratch" $fontHeader "#F7FFF7" 1180 194 130 84

Draw-VerticalLeds 760 156
Draw-VerticalLeds 826 156
Draw-Slider 892 156 50 838 "#9BE8FF" "#0066B7" "#56BEFF"
Draw-Slider 1118 156 50 838 "#FF97CB" "#971C57" "#FF68AC"
Draw-VerticalLeds 1234 156
Draw-VerticalLeds 1300 156

$g.DrawString("100", $fontBig, $cyan, 954, 630)
$g.DrawString("300", $fontBig, $cyan, 954, 718)
$g.DrawString("500 ms", $fontBig, $cyan, 954, 816)
$g.DrawString("0", [System.Drawing.Font]::new("Segoe UI", 54, [System.Drawing.FontStyle]::Bold), $cyan, 968, 928)

$red = Brush-Html "#FF2C2C"
$g.DrawString("500 ms", $fontBig, $red, 1070, 816)
$g.DrawString("0", [System.Drawing.Font]::new("Segoe UI", 54, [System.Drawing.FontStyle]::Bold), $red, 1130, 928)

$bmp.Save($outFile, [System.Drawing.Imaging.ImageFormat]::Png)

$fontTitle.Dispose()
$fontSection.Dispose()
$fontHeader.Dispose()
$fontBpm.Dispose()
$fontMini.Dispose()
$fontButton.Dispose()
$fontPad.Dispose()
$fontBig.Dispose()
$penBlue.Dispose()
$penRed.Dispose()
$cyan.Dispose()
$white.Dispose()
$red.Dispose()
$g.Dispose()
$bmp.Dispose()

Write-Output "Created $outFile"
