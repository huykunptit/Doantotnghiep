#Requires -Version 5.1
# Cai dat va khoi dong Docker Desktop tren Windows 11 Home + WSL2.
# Double-click: scripts\install-docker-windows.cmd
# Neu Windows yeu cau restart, reboot roi chay LAI script nay.

[CmdletBinding()]
param(
    [switch]$SkipHelloWorld,
    [switch]$NoRebootPrompt
)

$ErrorActionPreference = "Stop"

$DockerInstallerUrl = "https://desktop.docker.com/win/main/amd64/Docker%20Desktop%20Installer.exe"
$InstallDir = "C:\Program Files\Docker\Docker"
$DesktopExe = Join-Path $InstallDir "Docker Desktop.exe"
$DockerCli = Join-Path $InstallDir "resources\bin\docker.exe"
$MarkerDir = Join-Path $env:ProgramData "Doantotnghiep"
$MarkerFile = Join-Path $MarkerDir "docker-install-pending-reboot"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Message)
    Write-Host "    OK  $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "    WARN  $Message" -ForegroundColor Yellow
}

function Write-Info {
    param([string]$Message)
    Write-Host "    $Message" -ForegroundColor Gray
}

function Test-IsAdmin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($id)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Request-Admin {
    if (Test-IsAdmin) { return }
    Write-Host "Can quyen Administrator. Dang mo lai script voi UAC..." -ForegroundColor Yellow
    $argList = @(
        "-NoProfile",
        "-ExecutionPolicy", "Bypass",
        "-File", $PSCommandPath
    )
    if ($SkipHelloWorld) { $argList += "-SkipHelloWorld" }
    if ($NoRebootPrompt) { $argList += "-NoRebootPrompt" }
    Start-Process -FilePath "powershell.exe" -Verb RunAs -ArgumentList $argList | Out-Null
    exit 0
}

function Get-InteractiveAccount {
    $cs = Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue
    if ($null -ne $cs -and $cs.UserName) { return [string]$cs.UserName }
    return "$env:USERDOMAIN\$env:USERNAME"
}

function Refresh-ProcessPath {
    $machinePath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    $userPath = [System.Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machinePath;$userPath"
}

function Stop-DockerLeftovers {
    Write-Step "Dung tien trinh / service Docker cu"
    $names = @(
        "Docker Desktop",
        "com.docker.backend",
        "com.docker.service",
        "com.docker.build",
        "com.docker.dev-envs",
        "dockerd",
        "vpnkit",
        "qemu-system-x86_64"
    )
    foreach ($n in $names) {
        Get-Process -Name $n -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    }

    foreach ($svcName in @("com.docker.service", "docker")) {
        $svc = Get-Service -Name $svcName -ErrorAction SilentlyContinue
        if ($null -ne $svc) {
            if ($svc.Status -ne "Stopped") {
                Stop-Service -Name $svcName -Force -ErrorAction SilentlyContinue
            }
            sc.exe delete $svcName | Out-Null
            Start-Sleep -Seconds 1
        }
    }
    Write-Ok "Da don process/service"
}

function Uninstall-ExistingDocker {
    Write-Step "Go Docker Desktop neu con cai dat"

    $uninstallerCandidates = @(
        (Join-Path $env:LOCALAPPDATA "Programs\DockerDesktop\Docker Desktop Installer.exe"),
        (Join-Path $InstallDir "Docker Desktop Installer.exe")
    )
    foreach ($u in $uninstallerCandidates) {
        if (Test-Path $u) {
            Write-Info "Uninstall bang: $u"
            $proc = Start-Process -FilePath $u -ArgumentList @("uninstall", "--quiet") -Wait -PassThru
            Write-Info ("Uninstall exit code: " + $proc.ExitCode)
        }
    }

    try {
        winget uninstall --id Docker.DockerDesktop -e --silent --accept-source-agreements 2>$null | Out-Null
    } catch {
    }

    Write-Ok "Da thu go cai dat Docker Desktop"
}

function Remove-DockerLeftoverFiles {
    Write-Step "Xoa thu muc Docker leftover (giu nguyen WSL Ubuntu)"

    foreach ($distro in @("docker-desktop", "docker-desktop-data")) {
        Write-Info "Thu unregister WSL distro: $distro"
        cmd /c "wsl --unregister $distro >nul 2>&1" | Out-Null
    }

    $paths = @(
        (Join-Path $env:LOCALAPPDATA "Programs\DockerDesktop"),
        (Join-Path $env:LOCALAPPDATA "Docker"),
        (Join-Path $env:APPDATA "Docker"),
        (Join-Path $env:APPDATA "Docker Desktop"),
        (Join-Path $env:LOCALAPPDATA "Docker Desktop"),
        "C:\Program Files\Docker",
        "C:\ProgramData\Docker",
        "C:\ProgramData\DockerDesktop"
    )
    foreach ($p in $paths) {
        if (Test-Path $p) {
            Write-Info "Xoa $p"
            cmd /c "rmdir /s /q `"$p`"" | Out-Null
            if (Test-Path $p) {
                Write-Warn "Khong xoa duoc $p. Dong Docker/Explorer roi chay lai."
            }
        }
    }
    Write-Ok "Da don file leftover"
}

function Enable-WslFeatures {
    Write-Step "Bat tinh nang WSL2 (bat buoc tren Windows 11 Home)"

    $changed = $false
    foreach ($feature in @("Microsoft-Windows-Subsystem-Linux", "VirtualMachinePlatform")) {
        $state = Get-WindowsOptionalFeature -Online -FeatureName $feature -ErrorAction SilentlyContinue
        if ($null -eq $state) {
            Write-Warn "Khong doc duoc feature $feature"
            continue
        }
        if ($state.State -ne "Enabled") {
            Write-Info "Enable $feature"
            Enable-WindowsOptionalFeature -Online -FeatureName $feature -All -NoRestart | Out-Null
            $changed = $true
        } else {
            Write-Ok "$feature da bat"
        }
    }

    try {
        wsl --update | Out-Null
        wsl --set-default-version 2 | Out-Null
        Write-Ok "WSL default version = 2"
    } catch {
        Write-Warn ("wsl --update that bai: " + $_.Exception.Message)
    }

    if ($changed) {
        New-Item -ItemType Directory -Force -Path $MarkerDir | Out-Null
        Set-Content -Path $MarkerFile -Value (Get-Date).ToString("o")
        return $true
    }
    return $false
}

function Ensure-DockerUsersGroup {
    Write-Step "Cau hinh group docker-users"
    $account = Get-InteractiveAccount
    Write-Info "Tai khoan: $account"

    $group = Get-LocalGroup -Name "docker-users" -ErrorAction SilentlyContinue
    if ($null -eq $group) {
        New-LocalGroup -Name "docker-users" -Description "Users of Docker Desktop" | Out-Null
        Write-Ok "Da tao group docker-users"
    } else {
        Write-Ok "Group docker-users da ton tai"
    }

    foreach ($member in @($account, "Administrators")) {
        try {
            Add-LocalGroupMember -Group "docker-users" -Member $member -ErrorAction Stop
            Write-Ok "Da them $member vao docker-users"
        } catch {
            $msg = [string]$_.Exception.Message
            if ($msg -match "already a member|1378") {
                Write-Ok "$member da nam trong docker-users"
            } else {
                cmd /c "net localgroup docker-users `"$member`" /add" | Out-Null
                Write-Info "Da thu them $member bang net localgroup"
            }
        }
    }

    net localgroup docker-users
}

function Install-DockerDesktop {
    Write-Step "Tai va cai Docker Desktop all-users + WSL2"

    if ((Test-Path $DesktopExe) -and (Test-Path $DockerCli)) {
        Write-Ok "Docker Desktop da co tai $InstallDir"
        return 0
    }

    $installer = Join-Path $env:TEMP "DockerDesktopInstaller.exe"
    Write-Info "Download: $DockerInstallerUrl"
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri $DockerInstallerUrl -OutFile $installer -UseBasicParsing

    if (-not (Test-Path $installer)) {
        throw "Tai Docker Desktop Installer that bai"
    }
    $bytes = (Get-Item $installer).Length
    if ($bytes -lt 1048576) {
        throw "Tai Docker Desktop Installer that bai (file qua nho)"
    }
    $sizeMb = [math]::Round(($bytes / 1MB), 1)
    Write-Ok ("Installer: " + $installer + " (" + $sizeMb + " MB)")

    $installerArgs = @(
        "install",
        "--quiet",
        "--accept-license",
        "--backend=wsl-2",
        "--always-run-service",
        "--installation-dir=$InstallDir"
    )
    Write-Info "Chay installer (mat vai phut)..."
    $proc = Start-Process -FilePath $installer -ArgumentList $installerArgs -Wait -PassThru
    Write-Info ("Installer exit code: " + $proc.ExitCode)

    if (@(0, 3010) -notcontains $proc.ExitCode) {
        throw ("Cai Docker Desktop that bai. Exit code: " + $proc.ExitCode)
    }

    if (-not (Test-Path $DesktopExe)) {
        throw "Khong thay Docker Desktop.exe sau khi cai. Kiem tra lai cai dat."
    }

    Write-Ok "Da cai Docker Desktop vao $InstallDir"
    return [int]$proc.ExitCode
}

function Start-DockerEngine {
    Write-Step "Khoi dong Docker service + Docker Desktop"
    Refresh-ProcessPath

    $svc = Get-Service -Name "com.docker.service" -ErrorAction SilentlyContinue
    if ($null -eq $svc) {
        $svcBin = Join-Path $InstallDir "com.docker.service"
        if (-not (Test-Path $svcBin)) {
            throw "Khong thay com.docker.service. Cai dat Docker chua xong."
        }
        Write-Warn "Service chua dang ky. Dang tao lai..."
        sc.exe create com.docker.service binPath= "`"$svcBin`"" start= auto obj= LocalSystem DisplayName= "Docker Desktop Service" | Out-Null
        sc.exe description com.docker.service "Docker Desktop Service" | Out-Null
        $svc = Get-Service -Name "com.docker.service" -ErrorAction Stop
    }

    if ($svc.StartType -ne "Automatic") {
        Set-Service -Name "com.docker.service" -StartupType Automatic
    }
    if ($svc.Status -ne "Running") {
        Start-Service -Name "com.docker.service"
    }

    $deadline = (Get-Date).AddMinutes(1)
    do {
        $svc.Refresh()
        if ($svc.Status -eq "Running") { break }
        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)

    $svc.Refresh()
    if ($svc.Status -ne "Running") {
        throw ("com.docker.service khong start duoc. State=" + $svc.Status)
    }
    Write-Ok "com.docker.service = RUNNING"

    $desktopRunning = Get-Process -Name "Docker Desktop" -ErrorAction SilentlyContinue
    if ($null -eq $desktopRunning) {
        Write-Info "Mo Docker Desktop.exe"
        Start-Process -FilePath $DesktopExe | Out-Null
    } else {
        Write-Ok "Docker Desktop dang chay"
    }

    Write-Step "Cho Docker Linux engine san sang (toi da 4 phut)"
    if (Test-Path $DockerCli) {
        $dockerCmd = $DockerCli
    } else {
        $dockerCmd = "docker"
    }

    $readyDeadline = (Get-Date).AddMinutes(4)
    $ready = $false
    while ((Get-Date) -lt $readyDeadline) {
        & $dockerCmd info --format "{{.ServerVersion}}" 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
        Write-Host "." -NoNewline -ForegroundColor DarkGray
        Start-Sleep -Seconds 5
    }
    Write-Host ""

    if (-not $ready) {
        throw @"
Docker engine chua san sang.
Hay kiem tra:
  1) Icon Docker o khay he thong da xanh chua
  2) Settings -> General -> Use the WSL 2 based engine
  3) Sign out Windows roi dang nhap lai (group docker-users)
  4) Chay lai script nay
"@
    }

    Write-Ok "Docker engine da san sang"
    & $dockerCmd version
    Write-Host ""
    & $dockerCmd info --format "Server: {{.ServerVersion}}  OS: {{.OperatingSystem}}  Driver: {{.Driver}}"

    if (-not $SkipHelloWorld) {
        Write-Step "Test container: hello-world"
        & $dockerCmd run --rm hello-world
        if ($LASTEXITCODE -ne 0) {
            throw "docker run hello-world that bai"
        }
        Write-Ok "hello-world OK"
    }
}

Request-Admin

Write-Host ""
Write-Host "==============================================" -ForegroundColor White
Write-Host "  Cai dat Docker Desktop cho Doantotnghiep" -ForegroundColor White
Write-Host "  Windows 11 Home + WSL2 + all-users install" -ForegroundColor White
Write-Host "==============================================" -ForegroundColor White

try {
    $dockerAlreadyOk = $false
    if (Test-Path $DockerCli) {
        Refresh-ProcessPath
        & $DockerCli info --format "{{.ServerVersion}}" 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $dockerAlreadyOk = $true }
    }

    if ($dockerAlreadyOk -and -not (Test-Path $MarkerFile)) {
        Write-Ok "Docker da chay tot. Bo qua cai dat, chi verify."
        Start-DockerEngine
    } else {
        Stop-DockerLeftovers
        Uninstall-ExistingDocker
        Stop-DockerLeftovers
        Remove-DockerLeftoverFiles
        $needRebootForFeatures = Enable-WslFeatures
        Ensure-DockerUsersGroup

        if ($needRebootForFeatures -and -not $NoRebootPrompt) {
            Write-Host ""
            Write-Host "CAN RESTART WINDOWS de bat WSL2 xong roi cai Docker." -ForegroundColor Yellow
            Write-Host "Sau khi vao Windows, chay LAI script nay (Run as admin)." -ForegroundColor Yellow
            Write-Host ""
            $ans = Read-Host "Restart ngay bay gio? (Y/n)"
            if ($ans -notmatch "^[nN]") {
                Restart-Computer -Force
                exit 0
            }
            Write-Warn "Ban chon khong restart. Hay restart thu cong roi chay lai script."
            exit 2
        }

        if (Test-Path $MarkerFile) {
            Remove-Item $MarkerFile -Force -ErrorAction SilentlyContinue
        }

        $code = Install-DockerDesktop
        Ensure-DockerUsersGroup

        if ($code -eq 3010 -and -not $NoRebootPrompt) {
            New-Item -ItemType Directory -Force -Path $MarkerDir | Out-Null
            Set-Content -Path $MarkerFile -Value "installer-3010"
            Write-Host ""
            Write-Host "Installer yeu cau restart. Sau khi reboot hay chay lai script." -ForegroundColor Yellow
            $ans = Read-Host "Restart ngay bay gio? (Y/n)"
            if ($ans -notmatch "^[nN]") {
                Restart-Computer -Force
                exit 0
            }
            exit 2
        }

        Start-DockerEngine
    }

    Write-Host ""
    Write-Host "==============================================" -ForegroundColor Green
    Write-Host "  DOCKER SAN SANG" -ForegroundColor Green
    Write-Host "==============================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Neu day la lan dau them vao docker-users:" -ForegroundColor Yellow
    Write-Host "  Sign out Windows (hoac restart) 1 lan, roi mo terminal thuong va chay:" -ForegroundColor Yellow
    Write-Host "    cd D:\Doantotnghiep" -ForegroundColor White
    Write-Host "    docker compose build" -ForegroundColor White
    Write-Host ""
} catch {
    Write-Host ""
    Write-Host ("THAT BAI: " + $_.Exception.Message) -ForegroundColor Red
    if ($_.ScriptStackTrace) {
        Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    }
    exit 1
}
