# PowerShell script to detect Windows display topology for second-monitor browser positioning
Add-Type -AssemblyName System.Windows.Forms

$screens = [System.Windows.Forms.Screen]::AllScreens
$screenList = @()

foreach ($s in $screens) {
    $screenList += [PSCustomObject]@{
        DeviceName = $s.DeviceName
        Primary    = $s.Primary
        X          = $s.Bounds.X
        Y          = $s.Bounds.Y
        Width      = $s.Bounds.Width
        Height     = $s.Bounds.Height
    }
}

# Determine target monitor (prefer secondary non-primary screen)
$target = $null
if ($screens.Length -gt 1) {
    $target = $screenList | Where-Object { -not $_.Primary } | Select-Object -First 1
}

if (-not $target) {
    $target = $screenList | Where-Object { $_.Primary } | Select-Object -First 1
}

if (-not $target -and $screenList.Length -gt 0) {
    $target = $screenList[0]
}

$winX = if ($target) { $target.X + 40 } else { 100 }
$winY = if ($target) { $target.Y + 40 } else { 100 }
$winW = if ($target -and $target.Width -ge 1440) { 1440 } else { 1280 }
$winH = if ($target -and $target.Height -ge 900) { 900 } else { 800 }

$output = [PSCustomObject]@{
    totalScreens      = $screens.Length
    screens           = $screenList
    targetScreen      = $target
    recommendedX      = $winX
    recommendedY      = $winY
    recommendedWidth  = $winW
    recommendedHeight = $winH
}

$output | ConvertTo-Json -Depth 4
