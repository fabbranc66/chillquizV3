$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Drawing

$root = Split-Path -Parent $PSScriptRoot
$outDir = Join-Path $root "preview"
$outFile = Join-Path $outDir "stempad-remix-v3.png"

if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Force -Path $outDir | Out-Null
}

function RectF([float]$x, [float]$y, [float]$w, [float]$h) {
    [System.Drawing.RectangleF]::new($x, $y, $w, $h)
}

function PointF([float]$x, [float]$y) {
    [System.Drawing.PointF]::new($x, $y)
}

function Brush-Html([string]$hex) {
    [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml($hex))
}

function Pen-Html([string]$hex, [float]$w = 1) {
    [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml($hex), $w)
}

function New-RoundPath([float]$x, [float]$y, [float]$w, [float]$h, [float]$r) {
    $p = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $d = $r * 2
    $p.AddArc($x, $y, $d, $d, 180, 90)
    $p.AddArc($x + $w - $d, $y, $d, $d, 270, 90)
    $p.AddArc($x + $w - $d, $y + $h - $d, $d, $d, 0, 90)
    $p.AddArc($x, $y + $h - $d, $d, $d, 90, 90)
    $p.CloseFigure()
    $p
}

function Fill-RoundGradient {
    param(
        [System.Drawing.Graphics]$Graphics,
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
        for ($i = 10; $i -ge 1; $i--) {
            $alpha = [Math]::Max(4, 24 - $i)
            $glowBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($alpha, [System.Drawing.ColorTranslator]::FromHtml($Glow)))
            $glowPath = New-RoundPath ($X - $i) ($Y - $i) ($W + ($i * 2)) ($H + ($i * 2)) ($R + $i)
            $Graphics.FillPath($glowBrush, $glowPath)
            $glowBrush.Dispose()
            $glowPath.Dispose()
        }
    }

    $path = New-RoundPath $X $Y $W $H $R
    $brush = [System.Drawing.Drawing2D.LinearGradientBrush]::new(
        (PointF $X $Y),
        (PointF $X ($Y + $H)),
        [System.Drawing.ColorTranslator]::FromHtml($Top),
        [System.Drawing.ColorTranslator]::FromHtml($Bottom)
    )
    $pen = Pen-Html $Border 2.5
    $Graphics.FillPath($brush, $path)
    $Graphics.DrawPath($pen, $path)

    $shine = New-RoundPath ($X + 6) ($Y + 6) ($W - 12) ($H * 0.28) ($R * 0.6)
    $shineBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(50, 255, 255, 255))
    $Graphics.FillPath($shineBrush, $shine)

    $shineBrush.Dispose()
    $shine.Dispose()
    $brush.Dispose()
    $pen.Dispose()
    $path.Dispose()
}

function Draw-TextCenter {
    param(
        [System.Drawing.Graphics]$Graphics,
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
    $Graphics.DrawString($Text, $Font, $brush, (RectF $X $Y $W $H), $sf)
    $brush.Dispose()
    $sf.Dispose()
}

function Draw-Pad {
    param(
        [System.Drawing.Graphics]$Graphics,
        [float]$X,
        [float]$Y,
        [string]$Top,
        [string]$Bottom,
        [string]$Border,
        [string]$Glow,
        [string]$Label,
        [System.Drawing.Font]$Font
    )
    Fill-RoundGradient $Graphics $X $Y 92 92 18 $Top $Bottom $Border $Glow
    if ($Label) {
        Draw-TextCenter $Graphics $Label $Font "#F7F8FA" $X $Y 92 92
    }
}

function Draw-Button {
    param(
        [System.Drawing.Graphics]$Graphics,
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H,
        [string]$Top,
        [string]$Bottom,
        [string]$Border,
        [string]$Glow,
        [string]$Label,
        [System.Drawing.Font]$Font
    )
    Fill-RoundGradient $Graphics $X $Y $W $H 18 $Top $Bottom $Border $Glow
    Draw-TextCenter $Graphics $Label $Font "#F9FAFB" $X $Y $W $H
}

function Draw-LedColumn {
    param(
        [System.Drawing.Graphics]$Graphics,
        [float]$X,
        [float]$Y,
        [int]$Count
    )
    for ($i = 0; $i -lt $Count; $i++) {
        Fill-RoundGradient $Graphics $X ($Y + ($i * 54)) 18 32 5 "#868686" "#5C5C5C" "#A0A0A0"
    }
}

function Draw-Slider {
    param(
        [System.Drawing.Graphics]$Graphics,
        [float]$X,
        [float]$Y,
        [float]$W,
        [float]$H,
        [string]$KnobTop,
        [string]$KnobBottom,
        [string]$KnobBorder
    )
    $trackPath = New-RoundPath $X $Y $W $H 8
    $trackBrush = Brush-Html "#5B5B5B"
    $trackPen = Pen-Html "#7A7A7A" 1.5
    $Graphics.FillPath($trackBrush, $trackPath)
    $Graphics.DrawPath($trackPen, $trackPath)
    for ($i = 1; $i -lt 6; $i++) {
        $line = Pen-Html "#464646" 1
        $yy = $Y + ($i * ($H / 6))
        $Graphics.DrawLine($line, $X + 3, $yy, $X + $W - 3, $yy)
        $line.Dispose()
    }
    Fill-RoundGradient $Graphics ($X - 2) ($Y + 4) ($W + 4) 84 12 $KnobTop $KnobBottom $KnobBorder $KnobBorder
    $trackBrush.Dispose()
    $trackPen.Dispose()
    $trackPath.Dispose()
}

$bmp = [System.Drawing.Bitmap]::new(1800, 950)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$g.Clear([System.Drawing.Color]::FromArgb(4, 4, 6))

# soft background balance
for ($i = 0; $i -lt 7; $i++) {
    $a = 12 - $i
    $b1 = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($a, 0, 170, 255))
    $g.FillEllipse($b1, 100 - ($i * 30), 80 - ($i * 18), 620 + ($i * 60), 420 + ($i * 34))
    $b1.Dispose()
    $b2 = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb($a, 255, 42, 114))
    $g.FillEllipse($b2, 1080 - ($i * 24), 90 - ($i * 18), 620 + ($i * 56), 410 + ($i * 32))
    $b2.Dispose()
}

$fontLogo = [System.Drawing.Font]::new("Segoe UI", 15, [System.Drawing.FontStyle]::Bold)
$fontTitle = [System.Drawing.Font]::new("Segoe UI", 27, [System.Drawing.FontStyle]::Bold)
$fontMeta = [System.Drawing.Font]::new("Segoe UI", 11, [System.Drawing.FontStyle]::Bold)
$fontSection = [System.Drawing.Font]::new("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
$fontButton = [System.Drawing.Font]::new("Segoe UI", 17, [System.Drawing.FontStyle]::Bold)
$fontMini = [System.Drawing.Font]::new("Segoe UI", 16, [System.Drawing.FontStyle]::Bold)
$fontPad = [System.Drawing.Font]::new("Segoe UI", 15, [System.Drawing.FontStyle]::Bold)
$fontNum = [System.Drawing.Font]::new("Segoe UI", 26, [System.Drawing.FontStyle]::Bold)
$fontZero = [System.Drawing.Font]::new("Segoe UI", 42, [System.Drawing.FontStyle]::Bold)

# top area
Draw-Button $g 36 18 158 46 "#FF4949" "#B80505" "#FF7272" "#FF3434" "VIRTUAL DJ" $fontLogo
$titleBrush = Brush-Html "#F0F3F7"
$g.DrawString("StemPad Clean Grid", $fontTitle, $titleBrush, 720, 24)
$titleBrush.Dispose()
$cyan = Brush-Html "#05D7FF"
$white = Brush-Html "#F0F3F7"
$bluePen = Pen-Html "#1B4968" 2
$redPen = Pen-Html "#632226" 2
$g.DrawLine($bluePen, 70, 108, 882, 108)
$g.DrawLine($redPen, 920, 108, 1730, 108)
$g.DrawString("Trascina un brano in questo lettore per caricarlo", $fontMeta, $cyan, 510, 48)
$g.DrawString("Trascina un brano in questo lettore per caricarlo", $fontMeta, $cyan, 990, 48)
$g.DrawString("120", $fontNum, $cyan, 548, 70)
$g.DrawString("BPM", $fontNum, $white, 618, 70)
$g.DrawString("BPM", $fontNum, $white, 1118, 70)
$g.DrawString("120", $fontNum, $cyan, 1190, 70)

# layout constants
$leftPadX = 36
$leftPadY = 128
$padGap = 14
$padSize = 92
$fxLeftX = 300
$fxTopY = 128
$centerX = 740
$rightFxX = 1204
$rightPadX = 1470

# section headers
$g.DrawString("stems FX", $fontSection, $white, 418, 118)
$g.DrawString("FX", $fontSection, $white, 530, 238)
$g.DrawString("cf curve", $fontSection, $white, 840, 118)
$g.DrawString("stems FX", $fontSection, $white, 1286, 118)
$g.DrawString("FX", $fontSection, $white, 1398, 238)

# left pads
$greenTop = "#93FF63"; $greenBottom = "#149C05"; $greenBorder = "#7FFF91"; $greenGlow = "#28FF1F"
$yellowTop = "#FFF07A"; $yellowBottom = "#B68D06"; $yellowBorder = "#FFE568"; $yellowGlow = "#FFD319"
$blueTop = "#8FDFFF"; $blueBottom = "#0C78CD"; $blueBorder = "#76CCFF"; $blueGlow = "#0CB1FF"
$pinkTop = "#FF92D0"; $pinkBottom = "#A8176A"; $pinkBorder = "#FF71C4"; $pinkGlow = "#FF2FA4"

for ($r = 0; $r -lt 7; $r++) {
    $y = $leftPadY + ($r * ($padSize + $padGap))
    Draw-Pad $g $leftPadX $y ($(if ($r -eq 1) { $pinkTop } else { $greenTop })) ($(if ($r -eq 1) { $pinkBottom } else { $greenBottom })) ($(if ($r -eq 1) { $pinkBorder } else { $greenBorder })) ($(if ($r -eq 1) { $pinkGlow } else { $greenGlow })) ($(if ($r -eq 6) { "mic" } else { "" })) $fontPad
    Draw-Pad $g ($leftPadX + 106) $y ($(if ($r -eq 1) { $pinkTop } else { $yellowTop })) ($(if ($r -eq 1) { $pinkBottom } else { $yellowBottom })) ($(if ($r -eq 1) { $pinkBorder } else { $yellowBorder })) ($(if ($r -eq 1) { $pinkGlow } else { $yellowGlow })) ($(if ($r -eq 6) { "keys" } else { "" })) $fontPad
    Draw-Pad $g ($leftPadX + 212) $y ($(if ($r -eq 1) { $pinkTop } else { $blueTop })) ($(if ($r -eq 1) { $pinkBottom } else { $blueBottom })) ($(if ($r -eq 1) { $pinkBorder } else { $blueBorder })) ($(if ($r -eq 1) { $pinkGlow } else { $blueGlow })) ($(if ($r -eq 6) { "drum" } else { "" })) $fontPad
}

# right pads
for ($r = 0; $r -lt 7; $r++) {
    $y = $leftPadY + ($r * ($padSize + $padGap))
    Draw-Pad $g $rightPadX $y ($(if ($r -eq 1) { $greenTop } else { $greenTop })) ($(if ($r -eq 1) { $greenBottom } else { $greenBottom })) $greenBorder $greenGlow ($(if ($r -eq 6) { "mic" } else { "" })) $fontPad
    Draw-Pad $g ($rightPadX + 106) $y ($(if ($r -eq 1) { $yellowTop } else { $yellowTop })) ($(if ($r -eq 1) { $yellowBottom } else { $yellowBottom })) $yellowBorder $yellowGlow ($(if ($r -eq 6) { "keys" } else { "" })) $fontPad
    Draw-Pad $g ($rightPadX + 212) $y ($(if ($r -eq 1) { $blueTop } else { $blueTop })) ($(if ($r -eq 1) { $blueBottom } else { $blueBottom })) $blueBorder $blueGlow ($(if ($r -eq 6) { "drum" } else { "" })) $fontPad
}
# overwrite second row on right to pink for all three
$rowPinkY = $leftPadY + (1 * ($padSize + $padGap))
Draw-Pad $g $rightPadX $rowPinkY $pinkTop $pinkBottom $pinkBorder $pinkGlow "" $fontPad
Draw-Pad $g ($rightPadX + 106) $rowPinkY $pinkTop $pinkBottom $pinkBorder $pinkGlow "" $fontPad
Draw-Pad $g ($rightPadX + 212) $rowPinkY $pinkTop $pinkBottom $pinkBorder $pinkGlow "" $fontPad

# stems buttons
Draw-Button $g 372 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "mic" $fontMini
Draw-Button $g 478 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "keys" $fontMini
Draw-Button $g 584 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "drum" $fontMini
Draw-Button $g 1270 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "mic" $fontMini
Draw-Button $g 1376 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "keys" $fontMini
Draw-Button $g 1482 156 92 76 "#D0C3FF" "#3A2764" "#9479FF" "#7851FF" "drum" $fontMini

# cf curve buttons
Draw-Button $g 796 156 100 76 "#FF95C7" "#4A1E34" "#E1528B" "#FF58A7" "smooth" $fontMini
Draw-Button $g 908 156 100 76 "#9EFF7A" "#21461B" "#55D85A" "#56FF64" "log" $fontMini
Draw-Button $g 1020 156 112 76 "#9EFF7A" "#21461B" "#55D85A" "#56FF64" "scratch" $fontMini

# FX buttons left
$leftFxButtons = @(
    @{ x = 372; y = 274; w = 150; h = 82; t = "ECHO 0.5" },
    @{ x = 536; y = 274; w = 150; h = 82; t = "HP 8" },
    @{ x = 372; y = 370; w = 150; h = 82; t = "ECHO 1" },
    @{ x = 536; y = 370; w = 150; h = 82; t = "JET" },
    @{ x = 372; y = 466; w = 150; h = 82; t = "ECHO 2" },
    @{ x = 372; y = 562; w = 150; h = 82; t = "CHOP" },
    @{ x = 372; y = 658; w = 150; h = 82; t = "ROOM" },
    @{ x = 372; y = 754; w = 150; h = 82; t = "LOOP OUT" }
)
foreach ($b in $leftFxButtons) {
    Draw-Button $g $b.x $b.y $b.w $b.h "#FFD9A6" "#4D296E" "#FF922E" "#A24EFF" $b.t $fontButton
}

# FX buttons right
$rightFxButtons = @(
    @{ x = 1270; y = 274; w = 150; h = 82; t = "HP 8" },
    @{ x = 1434; y = 274; w = 150; h = 82; t = "ECHO 0.5" },
    @{ x = 1270; y = 370; w = 150; h = 82; t = "FILTER" },
    @{ x = 1434; y = 370; w = 150; h = 82; t = "ECHO 1" },
    @{ x = 1434; y = 466; w = 150; h = 82; t = "ECHO 2" },
    @{ x = 1434; y = 562; w = 150; h = 82; t = "CHOP" },
    @{ x = 1434; y = 658; w = 150; h = 82; t = "ROOM" },
    @{ x = 1434; y = 754; w = 150; h = 82; t = "LOOP OUT" }
)
foreach ($b in $rightFxButtons) {
    Draw-Button $g $b.x $b.y $b.w $b.h "#FFD9A6" "#4D296E" "#FF922E" "#A24EFF" $b.t $fontButton
}

# center meters
Draw-LedColumn $g 690 128 10
Draw-LedColumn $g 736 128 10
Draw-Slider $g 782 128 40 708 "#98E5FF" "#086FC0" "#58BEFF"
Draw-Slider $g 978 128 40 708 "#FF97CD" "#A11C5E" "#FF68AF"
Draw-LedColumn $g 1034 128 10
Draw-LedColumn $g 1080 128 10

$g.DrawString("100", $fontNum, $cyan, 842, 430)
$g.DrawString("300", $fontNum, $cyan, 842, 490)
$g.DrawString("500 ms", $fontNum, $cyan, 842, 556)
$red = Brush-Html "#FF3838"
$g.DrawString("500 ms", $fontNum, $red, 932, 556)
$g.DrawString("0", $fontZero, $cyan, 846, 630)
$g.DrawString("0", $fontZero, $red, 952, 630)

$bluePen.Dispose()
$redPen.Dispose()
$cyan.Dispose()
$white.Dispose()
$red.Dispose()
$fontLogo.Dispose()
$fontTitle.Dispose()
$fontMeta.Dispose()
$fontSection.Dispose()
$fontButton.Dispose()
$fontMini.Dispose()
$fontPad.Dispose()
$fontNum.Dispose()
$fontZero.Dispose()

$bmp.Save($outFile, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose()
$bmp.Dispose()

Write-Output "Created $outFile"
