param (
    [Parameter(Mandatory=$false)]
    [ValidateSet('launch', 'foreground', 'inspect')]
    [string]$Action = 'inspect',

    [Parameter(Mandatory=$false)]
    [int]$Port = 9222,

    [Parameter(Mandatory=$false)]
    [string]$TargetUrl = 'http://localhost:8000/login',

    [Parameter(Mandatory=$false)]
    [switch]$KeepAlive = $false
)

Add-Type @'
using System;
using System.Text;
using System.Threading;
using System.Runtime.InteropServices;
using System.Diagnostics;
using System.Collections.Generic;

public class Win32DesktopManager {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct STARTUPINFO {
        public int cb;
        public string lpReserved;
        public string lpDesktop;
        public string lpTitle;
        public int dwX;
        public int dwY;
        public int dwXSize;
        public int dwYSize;
        public int dwXCountChars;
        public int dwYCountChars;
        public int dwFillAttribute;
        public int dwFlags;
        public short wShowWindow;
        public short cbReserved2;
        public IntPtr lpReserved2;
        public IntPtr hStdInput;
        public IntPtr hStdOutput;
        public IntPtr hStdError;
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct PROCESS_INFORMATION {
        public IntPtr hProcess;
        public IntPtr hThread;
        public int dwProcessId;
        public int dwThreadId;
    }

    [DllImport("kernel32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool CreateProcessW(
        string lpApplicationName,
        string lpCommandLine,
        IntPtr lpProcessAttributes,
        IntPtr lpThreadAttributes,
        bool bInheritHandles,
        uint dwCreationFlags,
        IntPtr lpEnvironment,
        string lpCurrentDirectory,
        ref STARTUPINFO lpStartupInfo,
        out PROCESS_INFORMATION lpProcessInformation
    );

    [DllImport("user32.dll", SetLastError = true)]
    public static extern IntPtr OpenDesktop(string lpszDesktop, int dwFlags, bool fInherit, uint dwDesiredAccess);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool SetThreadDesktop(IntPtr hDesktop);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool CloseDesktop(IntPtr hDesktop);

    public delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool EnumDesktopWindows(IntPtr hDesktop, EnumWindowsProc lpEnumFunc, IntPtr lParam);

    [DllImport("user32.dll")]
    public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

    [DllImport("user32.dll", CharSet = CharSet.Auto)]
    public static extern int GetWindowText(IntPtr hWnd, StringBuilder lpString, int nMaxCount);

    [DllImport("user32.dll", CharSet = CharSet.Auto)]
    public static extern int GetClassName(IntPtr hWnd, StringBuilder lpString, int nMaxCount);

    [DllImport("user32.dll")]
    public static extern IntPtr GetForegroundWindow();

    [DllImport("user32.dll")]
    public static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    public static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    public static extern bool BringWindowToTop(IntPtr hWnd);

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool IsWindowVisible(IntPtr hWnd);

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool IsIconic(IntPtr hWnd);

    [StructLayout(LayoutKind.Sequential)]
    public struct RECT {
        public int Left;
        public int Top;
        public int Right;
        public int Bottom;
    }

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool GetWindowRect(IntPtr hWnd, out RECT lpRect);

    [DllImport("kernel32.dll")]
    public static extern uint GetCurrentThreadId();

    [DllImport("user32.dll")]
    public static extern uint GetWindowThreadProcessId(IntPtr hWnd, IntPtr ProcessId);

    [DllImport("user32.dll")]
    public static extern bool AttachThreadInput(uint idAttach, uint idAttachTo, bool fAttach);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool SetWindowPos(IntPtr hWnd, IntPtr hWndInsertAfter, int X, int Y, int cx, int cy, uint uFlags);

    public class WindowDetails {
        public string HwndHex;
        public long Hwnd;
        public uint Pid;
        public string ProcessName;
        public string ExePath;
        public string Title;
        public string ClassName;
        public bool IsVisible;
        public bool IsIconic;
        public int Left;
        public int Top;
        public int Right;
        public int Bottom;
        public int Width;
        public int Height;
    }

    public static List<WindowDetails> DiscoveredWindows = new List<WindowDetails>();
    public static WindowDetails ForegroundWindow = null;
    public static WindowDetails QaChromeWindow = null;
    public static int SpawnedPid = 0;
    public static int LastSpawnError = 0;

    public static bool LaunchOnDefaultDesktop(string fullCmd) {
        bool ok = false;
        Thread t = new Thread(() => {
            IntPtr hDesktop = OpenDesktop("Default", 0, false, 0x01FF);
            if (hDesktop != IntPtr.Zero) {
                SetThreadDesktop(hDesktop);
            }

            STARTUPINFO si = new STARTUPINFO();
            si.cb = Marshal.SizeOf(si);
            si.lpDesktop = "Default";

            PROCESS_INFORMATION pi;
            ok = CreateProcessW(null, fullCmd, IntPtr.Zero, IntPtr.Zero, false, 0, IntPtr.Zero, null, ref si, out pi);
            if (!ok) {
                LastSpawnError = Marshal.GetLastWin32Error();
            } else {
                SpawnedPid = pi.dwProcessId;
            }

            if (hDesktop != IntPtr.Zero) CloseDesktop(hDesktop);
        });

        t.SetApartmentState(ApartmentState.STA);
        t.Start();
        t.Join();
        return ok;
    }

    public static void QueryDesktop(int qaChromePid) {
        DiscoveredWindows.Clear();
        ForegroundWindow = null;
        QaChromeWindow = null;

        Thread t = new Thread(() => {
            IntPtr hDesktop = OpenDesktop("Default", 0, false, 0x01FF);
            if (hDesktop == IntPtr.Zero) return;
            if (!SetThreadDesktop(hDesktop)) {
                CloseDesktop(hDesktop);
                return;
            }

            // 1. Get Foreground
            IntPtr fgHwnd = GetForegroundWindow();
            if (fgHwnd != IntPtr.Zero) {
                uint fgPid = 0;
                GetWindowThreadProcessId(fgHwnd, out fgPid);
                StringBuilder sbTitle = new StringBuilder(512);
                GetWindowText(fgHwnd, sbTitle, 512);
                StringBuilder sbClass = new StringBuilder(256);
                GetClassName(fgHwnd, sbClass, 256);

                RECT r;
                GetWindowRect(fgHwnd, out r);

                string fgExe = "";
                string fgProcName = "";
                try {
                    Process p = Process.GetProcessById((int)fgPid);
                    fgProcName = p.ProcessName;
                    fgExe = p.MainModule.FileName;
                } catch {}

                ForegroundWindow = new WindowDetails {
                    HwndHex = "0x" + fgHwnd.ToInt64().ToString("X"),
                    Hwnd = fgHwnd.ToInt64(),
                    Pid = fgPid,
                    ProcessName = fgProcName,
                    ExePath = fgExe,
                    Title = sbTitle.ToString(),
                    ClassName = sbClass.ToString(),
                    IsVisible = IsWindowVisible(fgHwnd),
                    IsIconic = IsIconic(fgHwnd),
                    Left = r.Left, Top = r.Top, Right = r.Right, Bottom = r.Bottom,
                    Width = r.Right - r.Left, Height = r.Bottom - r.Top
                };
            }

            // 2. Enumerate all windows on Default Desktop
            EnumDesktopWindows(hDesktop, (hwnd, lParam) => {
                uint pid = 0;
                GetWindowThreadProcessId(hwnd, out pid);
                if (pid == 0) return true;

                StringBuilder sbClass = new StringBuilder(256);
                GetClassName(hwnd, sbClass, 256);
                string cls = sbClass.ToString();

                StringBuilder sbTitle = new StringBuilder(512);
                GetWindowText(hwnd, sbTitle, 512);
                string title = sbTitle.ToString();

                RECT r;
                GetWindowRect(hwnd, out r);
                int w = r.Right - r.Left;
                int h = r.Bottom - r.Top;

                string procName = "";
                string exePath = "";
                try {
                    Process p = Process.GetProcessById((int)pid);
                    procName = p.ProcessName.ToLower();
                    try { exePath = p.MainModule.FileName; } catch {}
                } catch {}

                bool isBrowser = procName == "chrome" || procName == "brave" || procName == "msedge";
                bool isGoodSize = (w > 200 && h > 200) || IsIconic(hwnd);
                if (isBrowser && (IsWindowVisible(hwnd) || IsIconic(hwnd)) && isGoodSize) {
                    WindowDetails details = new WindowDetails {
                        HwndHex = "0x" + hwnd.ToInt64().ToString("X"),
                        Hwnd = hwnd.ToInt64(),
                        Pid = pid,
                        ProcessName = procName,
                        ExePath = exePath,
                        Title = title,
                        ClassName = cls,
                        IsVisible = IsWindowVisible(hwnd),
                        IsIconic = IsIconic(hwnd),
                        Left = r.Left, Top = r.Top, Right = r.Right, Bottom = r.Bottom,
                        Width = w, Height = h
                    };
                    DiscoveredWindows.Add(details);

                    // Must be official Google Chrome (never Brave or Edge)
                    bool isChromeProc = procName == "chrome" && (string.IsNullOrEmpty(exePath) || exePath.ToLower().Contains("google\\chrome"));
                    if (isChromeProc && cls == "Chrome_WidgetWin_1" && ((w > 400 && h > 300) || IsIconic(hwnd))) {
                        if (QaChromeWindow == null || pid == qaChromePid) {
                            QaChromeWindow = details;
                        }
                    }
                }
                return true;
            }, IntPtr.Zero);

            CloseDesktop(hDesktop);
        });

        t.SetApartmentState(ApartmentState.STA);
        t.Start();
        t.Join();
    }

    [DllImport("user32.dll")]
    public static extern void SwitchToThisWindow(IntPtr hWnd, bool fUnknown);

    [DllImport("user32.dll")]
    public static extern void keybd_event(byte bVk, byte bScan, uint dwFlags, int dwExtraInfo);

    public static bool ForegroundWindowByHwnd(long hwndVal) {
        bool ok = false;
        Thread t = new Thread(() => {
            IntPtr hDesktop = OpenDesktop("Default", 0, false, 0x01FF);
            if (hDesktop == IntPtr.Zero) return;
            if (!SetThreadDesktop(hDesktop)) {
                CloseDesktop(hDesktop);
                return;
            }

            IntPtr targetHwnd = new IntPtr(hwndVal);

            // 1. Release foreground lock via keybd_event (ALT key tap)
            keybd_event(0x12, 0, 0, 0); // ALT down
            keybd_event(0x12, 0, 2, 0); // ALT up (KEYEVENTF_KEYUP = 2)

            // 2. Restore if iconic
            if (IsIconic(targetHwnd)) {
                ShowWindow(targetHwnd, 9); // SW_RESTORE
            } else {
                ShowWindow(targetHwnd, 5); // SW_SHOW
            }

            // 3. Force to top of Z-order above all windows (including Brave)
            SetWindowPos(targetHwnd, new IntPtr(-1), 40, 40, 1400, 800, 0x0040); // HWND_TOPMOST
            SetWindowPos(targetHwnd, new IntPtr(-2), 40, 40, 1400, 800, 0x0040); // HWND_NOTOPMOST

            // 4. SwitchToThisWindow + SetForegroundWindow
            SwitchToThisWindow(targetHwnd, true);

            IntPtr curFg = GetForegroundWindow();
            uint curFgPid = 0;
            uint curFgThread = GetWindowThreadProcessId(curFg, out curFgPid);
            uint myThread = GetCurrentThreadId();

            if (curFgThread != 0 && curFgThread != myThread) {
                AttachThreadInput(myThread, curFgThread, true);
                BringWindowToTop(targetHwnd);
                ok = SetForegroundWindow(targetHwnd);
                AttachThreadInput(myThread, curFgThread, false);
            } else {
                BringWindowToTop(targetHwnd);
                ok = SetForegroundWindow(targetHwnd);
            }

            CloseDesktop(hDesktop);
        });

        t.SetApartmentState(ApartmentState.STA);
        t.Start();
        t.Join();
        return ok;
    }
}
'@

function Get-QaChromePid {
    param([int]$cdpPort)
    try {
        $net = Get-NetTCPConnection -LocalPort $cdpPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($net) { return $net.OwningProcess }
    } catch {}
    return 0
}

$exePath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$profileDir = "F:\Wholesale Distribution Management System\artifacts\browser\qa-profile"

$qaPid = Get-QaChromePid -cdpPort $Port

if ($Action -eq 'launch') {
    if ($qaPid -eq 0) {
        $cmd = "`"$exePath`" --remote-debugging-port=$Port --remote-debugging-address=127.0.0.1 `"--user-data-dir=$profileDir`" --no-first-run --no-default-browser-check --window-position=40,40 --window-size=1440,800 $TargetUrl"
        $launched = [Win32DesktopManager]::LaunchOnDefaultDesktop($cmd)
        if (-not $launched) {
            Write-Error "Failed to launch Chrome on Default desktop. Error: $([Win32DesktopManager]::LastSpawnError)"
            exit 1
        }
        $qaPid = [Win32DesktopManager]::SpawnedPid

        # Wait for CDP
        $ready = $false
        for ($i = 0; $i -lt 15; $i++) {
            Start-Sleep -Milliseconds 500
            try {
                $v = Invoke-RestMethod -Uri "http://127.0.0.1:$Port/json/version" -TimeoutSec 1
                if ($v.Browser -match "Chrome") {
                    $ready = $true
                    break
                }
            } catch {}
        }
        if (-not $ready) {
            Write-Error "CDP port $Port did not respond after launch"
            exit 1
        }
        # Update PID from port if needed
        $activePid = Get-QaChromePid -cdpPort $Port
        if ($activePid -gt 0) { $qaPid = $activePid }
    }

    # Locate and foreground window
    for ($attempt = 0; $attempt -lt 10; $attempt++) {
        [Win32DesktopManager]::QueryDesktop($qaPid)
        if ([Win32DesktopManager]::QaChromeWindow -ne $null) {
            [Win32DesktopManager]::ForegroundWindowByHwnd([Win32DesktopManager]::QaChromeWindow.Hwnd) | Out-Null
            Start-Sleep -Milliseconds 300
            [Win32DesktopManager]::QueryDesktop($qaPid)
            break
        }
        Start-Sleep -Milliseconds 500
    }
} elseif ($Action -eq 'foreground') {
    [Win32DesktopManager]::QueryDesktop($qaPid)
    $qaWin = [Win32DesktopManager]::QaChromeWindow
    if ($qaWin) {
        [Win32DesktopManager]::ForegroundWindowByHwnd($qaWin.Hwnd) | Out-Null
        Start-Sleep -Milliseconds 300
    }
    [Win32DesktopManager]::QueryDesktop($qaPid)
} elseif ($Action -eq 'inspect') {
    [Win32DesktopManager]::QueryDesktop($qaPid)
}

$report = [PSCustomObject]@{
    Action            = $Action
    QaChromePidOnPort = $qaPid
    QaChromeWindow    = [Win32DesktopManager]::QaChromeWindow
    ForegroundWindow  = [Win32DesktopManager]::ForegroundWindow
    AllBrowserWindows = [Win32DesktopManager]::DiscoveredWindows
}

$report | ConvertTo-Json -Depth 4

if ($KeepAlive) {
    while ($true) {
        Start-Sleep -Seconds 30
    }
}
