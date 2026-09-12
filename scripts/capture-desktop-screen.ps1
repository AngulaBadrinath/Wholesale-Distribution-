param (
    [string]$OutputPath = "artifacts/browser/interactive/screenshots/browser-sync/physical_desktop_qa_chrome_visible.png",
    [switch]$ForegroundQaChrome = $true
)

Add-Type -ReferencedAssemblies System.Drawing @'
using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;
using System.Threading;

public class DesktopCapture {
    [DllImport("user32.dll", SetLastError = true)]
    public static extern IntPtr OpenDesktop(string lpszDesktop, int dwFlags, bool fInherit, uint dwDesiredAccess);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool SetThreadDesktop(IntPtr hDesktop);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool CloseDesktop(IntPtr hDesktop);

    [DllImport("user32.dll")]
    public static extern IntPtr GetDesktopWindow();

    [DllImport("user32.dll")]
    public static extern IntPtr GetDC(IntPtr hWnd);

    [DllImport("user32.dll")]
    public static extern int ReleaseDC(IntPtr hWnd, IntPtr hDC);

    [DllImport("gdi32.dll")]
    public static extern IntPtr CreateCompatibleDC(IntPtr hdc);

    [DllImport("gdi32.dll")]
    public static extern IntPtr CreateCompatibleBitmap(IntPtr hdc, int nWidth, int nHeight);

    [DllImport("gdi32.dll")]
    public static extern IntPtr SelectObject(IntPtr hdc, IntPtr hgdiobj);

    [DllImport("gdi32.dll")]
    public static extern bool BitBlt(IntPtr hdcDest, int nXDest, int nYDest, int nWidth, int nHeight, IntPtr hdcSrc, int nXSrc, int nYSrc, int dwRop);

    [DllImport("gdi32.dll")]
    public static extern bool DeleteDC(IntPtr hdc);

    [DllImport("gdi32.dll")]
    public static extern bool DeleteObject(IntPtr hObject);

    [DllImport("user32.dll")]
    public static extern int GetSystemMetrics(int nIndex);

    public static string LastError = "";

    public static bool Capture(string savePath) {
        bool ok = false;
        Thread t = new Thread(() => {
            IntPtr hDesktop = OpenDesktop("Default", 0, false, 0x01FF);
            if (hDesktop == IntPtr.Zero) {
                LastError = "Failed to open Default desktop: " + Marshal.GetLastWin32Error();
                return;
            }
            if (!SetThreadDesktop(hDesktop)) {
                LastError = "Failed to SetThreadDesktop: " + Marshal.GetLastWin32Error();
                CloseDesktop(hDesktop);
                return;
            }

            int w = GetSystemMetrics(0); // SM_CXSCREEN
            int h = GetSystemMetrics(1); // SM_CYSCREEN
            if (w <= 0) w = 1536;
            if (h <= 0) h = 864;

            IntPtr hDeskWnd = GetDesktopWindow();
            IntPtr hdcSrc = GetDC(hDeskWnd);
            IntPtr hdcDest = CreateCompatibleDC(hdcSrc);
            IntPtr hBitmap = CreateCompatibleBitmap(hdcSrc, w, h);
            IntPtr hOld = SelectObject(hdcDest, hBitmap);

            // SRCCOPY = 0x00CC0020
            BitBlt(hdcDest, 0, 0, w, h, hdcSrc, 0, 0, 0x00CC0020);

            SelectObject(hdcDest, hOld);
            DeleteDC(hdcDest);
            ReleaseDC(hDeskWnd, hdcSrc);

            using (Bitmap bmp = Image.FromHbitmap(hBitmap)) {
                bmp.Save(savePath, ImageFormat.Png);
            }

            DeleteObject(hBitmap);
            CloseDesktop(hDesktop);
            ok = true;
        });

        t.SetApartmentState(ApartmentState.STA);
        t.Start();
        t.Join();
        return ok;
    }
}
'@

$fullPath = [System.IO.Path]::GetFullPath($OutputPath)
$dir = [System.IO.Path]::GetDirectoryName($fullPath)
if (-not (Test-Path $dir)) {
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
}

if ($ForegroundQaChrome) {
    $mgmt = Join-Path $PSScriptRoot "manage-qa-chrome.ps1"
    if (Test-Path $mgmt) {
        & powershell -NoProfile -ExecutionPolicy Bypass -File $mgmt -Action foreground -Port 9222 | Out-Null
        Start-Sleep -Milliseconds 500
    }
}

$success = [DesktopCapture]::Capture($fullPath)
if ($success) {
    Write-Output "Physical screenshot captured successfully: $fullPath"
} else {
    Write-Error "Capture failed: $([DesktopCapture]::LastError)"
}
