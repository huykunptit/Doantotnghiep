#Requires -Version 5.1
# Cai dat Flutter SDK (stable) tren Windows, khong can quyen Administrator.
# Double-click: scripts\install-flutter-windows.cmd
# Sau khi cai xong, mo terminal MOI (de nhan PATH) va chay: flutter doctor

[CmdletBinding()]
param(
    [string]$InstallDir = (Join-Path $env:LOCALAPPDATA "flutter"),
    [string]$Channel = "stable",
    [switch]$SkipDoctor,
    [switch]$SkipPubGet
)

$ErrorActionPreference = "Stop"

$RepoRoot = Split-Path -Parent $PSScriptRoot
$MobileDir = Join-Path $RepoRoot "mobile"
$ManifestUrl = "https://storage.googleapis.com/flutter_infra_release/releases/releases_windows.json"

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

function Refresh-ProcessPath {
    $machinePath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    $userPath = [System.Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machinePath;$userPath"
}

function Get-FlutterBat {
    Join-Path (Join-Path $InstallDir "bin") "flutter.bat"
}

function Test-FlutterInstalled {
    Test-Path (Get-FlutterBat)
}

function Get-RemoteFileSize {
    param([string]$Url)
    try {
        $resp = Invoke-WebRequest -Uri $Url -Method Head -UseBasicParsing
        return [int64]$resp.Headers["Content-Length"]
    }
    catch {
        return 0
    }
}

function Get-FileWithProgress {
    param(
        [string]$Url,
        [string]$OutFile
    )

    $expectedSize = Get-RemoteFileSize -Url $Url
    if ((Test-Path $OutFile)) {
        $existingSize = (Get-Item $OutFile).Length
        if ($expectedSize -gt 0 -and $existingSize -ne $expectedSize) {
            Write-Warn "File tam thoi bi do dang/hong (co san $([math]::Round($existingSize/1MB,1))MB, can $([math]::Round($expectedSize/1MB,1))MB). Xoa va tai lai."
            Remove-Item -Force $OutFile
        }
        elseif ($existingSize -gt 0) {
            Write-Ok "Da co san file tai truoc do, bo qua tai lai: $OutFile"
            return
        }
    }

    # Start-BitsTransfer dung engine BITS cua Windows: nhanh, cho resume, khong
    # dinh loi progress-bar cua Invoke-WebRequest (PowerShell 5.1 render progress
    # bar cho download lon cuc ky cham, nhin giong nhu bi treo).
    $bitsOk = $false
    try {
        Import-Module BitsTransfer -ErrorAction Stop
        Write-Info "Tai bang BITS (nhan Ctrl+C de huy neu can)..."
        $job = Start-BitsTransfer -Source $Url -Destination $OutFile -Asynchronous -DisplayName "flutter-sdk"
        $lastPercent = -1
        while ($job.JobState -in @("Connecting", "Transferring", "TransientError")) {
            Start-Sleep -Seconds 2
            $job = Get-BitsTransfer -JobId $job.JobId
            if ($job.BytesTotal -gt 0) {
                $percent = [math]::Floor(($job.BytesTransferred / $job.BytesTotal) * 100)
                if ($percent -ne $lastPercent) {
                    $mbDone = [math]::Round($job.BytesTransferred / 1MB, 1)
                    $mbTotal = [math]::Round($job.BytesTotal / 1MB, 1)
                    Write-Host "`r    Dang tai: $percent% ($mbDone MB / $mbTotal MB)   " -NoNewline -ForegroundColor Gray
                    $lastPercent = $percent
                }
            }
        }
        Write-Host ""

        if ($job.JobState -eq "Transferred") {
            Complete-BitsTransfer -BitsJob $job
            $bitsOk = $true
        }
        else {
            Write-Warn "BITS ket thuc voi trang thai: $($job.JobState). Chuyen sang cach tai du phong."
            Remove-BitsTransfer -BitsJob $job -ErrorAction SilentlyContinue
        }
    }
    catch {
        Write-Warn "BITS khong dung duoc ($($_.Exception.Message)). Chuyen sang cach tai du phong."
    }

    if ($bitsOk) { return }
    if (Test-Path $OutFile) { Remove-Item -Force $OutFile -ErrorAction SilentlyContinue }

    # Du phong: WebClient voi progress event tu viet (khong dung Invoke-WebRequest
    # mac dinh vi thanh progress bar cua no lam toc do tai cham di rat nhieu).
    Write-Info "Tai bang WebClient (du phong)..."
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $client = New-Object System.Net.WebClient
    $lastPercent = -1
    Register-ObjectEvent -InputObject $client -EventName DownloadProgressChanged -SourceIdentifier FlutterDownloadProgress -Action {
        $percent = $EventArgs.ProgressPercentage
        if ($percent -ne $script:lastPercent) {
            $mbDone = [math]::Round($EventArgs.BytesReceived / 1MB, 1)
            $mbTotal = [math]::Round($EventArgs.TotalBytesToReceive / 1MB, 1)
            Write-Host "`r    Dang tai: $percent% ($mbDone MB / $mbTotal MB)   " -NoNewline -ForegroundColor Gray
            $script:lastPercent = $percent
        }
    } | Out-Null

    try {
        $client.DownloadFileAsync([Uri]$Url, $OutFile)
        while ($client.IsBusy) { Start-Sleep -Milliseconds 500 }
        Write-Host ""
    }
    finally {
        Unregister-Event -SourceIdentifier FlutterDownloadProgress -ErrorAction SilentlyContinue
        $client.Dispose()
    }
}

function Expand-FlutterZip {
    param(
        [string]$ZipPath,
        [string]$DestinationDir
    )

    # Flutter SDK chua vai duong dan test-fixture rat sau (vd:
    # engine\src\flutter\testing\ios_scenario_app\...\xcshareddata\...plist),
    # de dang vuot qua gioi han 260 ky tu (MAX_PATH) cua Windows. .NET
    # ZipFile.ExtractToDirectory mac dinh bao loi "Could not find a part of
    # the path" trong truong hop nay. Dung tar.exe (co san tu Windows 10
    # 1803+) truoc vi no xu ly duong dan dai dung cach.
    $tar = Get-Command tar.exe -ErrorAction SilentlyContinue
    if ($tar) {
        Write-Info "Dang giai nen bang tar.exe..."
        & $tar.Source -xf $ZipPath -C $DestinationDir
        if ($LASTEXITCODE -eq 0) {
            return
        }
        Write-Warn "tar.exe that bai (exit code $LASTEXITCODE). Thu lai bang .NET ZipFile (long path)."
    }
    else {
        Write-Warn "Khong thay tar.exe. Dung .NET ZipFile (long path)."
    }

    # Du phong: .NET ZipFile voi tien to \\?\ de bo qua gioi han MAX_PATH.
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $longZipPath = if ($ZipPath.StartsWith("\\?\")) { $ZipPath } else { "\\?\$ZipPath" }
    $longDestDir = if ($DestinationDir.StartsWith("\\?\")) { $DestinationDir } else { "\\?\$DestinationDir" }
    [System.IO.Compression.ZipFile]::ExtractToDirectory($longZipPath, $longDestDir)
}

function Get-ReleaseInfo {
    Write-Step "Tra cuu ban Flutter $Channel moi nhat cho Windows"
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $manifest = Invoke-RestMethod -Uri $ManifestUrl -UseBasicParsing

    $hash = $manifest.current_release.$Channel
    if (-not $hash) {
        throw "Khong tim thay current_release cho channel '$Channel' trong manifest."
    }

    $release = $manifest.releases | Where-Object { $_.hash -eq $hash -and $_.channel -eq $Channel } | Select-Object -First 1
    if (-not $release) {
        throw "Khong tim thay release chi tiet cho hash $hash."
    }

    $archiveUrl = "$($manifest.base_url)/$($release.archive)"
    Write-Ok "Ban moi nhat: $($release.version) ($Channel)"
    Write-Info "Archive: $archiveUrl"

    return [pscustomobject]@{
        Version    = $release.version
        ArchiveUrl = $archiveUrl
    }
}

function Install-Flutter {
    if (Test-FlutterInstalled) {
        Write-Ok "Flutter da co tai $InstallDir"
        return
    }

    $release = Get-ReleaseInfo

    Write-Step "Tai Flutter SDK ($($release.Version))"
    $zipPath = Join-Path $env:TEMP "flutter_windows_$($release.Version).zip"
    Get-FileWithProgress -Url $release.ArchiveUrl -OutFile $zipPath
    $sizeMb = [math]::Round(((Get-Item $zipPath).Length / 1MB), 1)
    Write-Ok "Da tai: $zipPath ($sizeMb MB)"

    Write-Step "Giai nen vao $InstallDir (Flutter SDK co hang chuc nghin file nho, co the mat vai phut)"
    $stagingDir = Join-Path $env:TEMP "flutter_extract_$([guid]::NewGuid().ToString('N'))"
    New-Item -ItemType Directory -Force -Path $stagingDir | Out-Null
    try {
        # Khong dung Expand-Archive: rat cham voi archive nhieu file nho
        # (progress bar per-file), co the mat rat lau va nhin giong nhu bi treo.
        Write-Info "Dang giai nen..."
        Expand-FlutterZip -ZipPath $zipPath -DestinationDir $stagingDir
        Write-Ok "Da giai nen"

        $extractedFlutter = Join-Path $stagingDir "flutter"
        if (-not (Test-Path $extractedFlutter)) {
            throw "Khong thay thu muc 'flutter' sau khi giai nen. Kiem tra lai file zip."
        }

        $parent = Split-Path -Parent $InstallDir
        if ($parent -and -not (Test-Path $parent)) {
            New-Item -ItemType Directory -Force -Path $parent | Out-Null
        }
        if (Test-Path $InstallDir) {
            Write-Warn "$InstallDir da ton tai nhung thieu bin\flutter.bat. Xoa va cai lai."
            Remove-Item -Recurse -Force $InstallDir
        }
        Move-Item -Path $extractedFlutter -Destination $InstallDir
    }
    finally {
        Remove-Item -Recurse -Force $stagingDir -ErrorAction SilentlyContinue
    }

    if (-not (Test-FlutterInstalled)) {
        throw "Cai Flutter that bai: khong thay $(Get-FlutterBat)"
    }
    Write-Ok "Da cai Flutter vao $InstallDir"
}

function Add-FlutterToUserPath {
    Write-Step "Them Flutter vao PATH (User)"
    $binDir = Join-Path $InstallDir "bin"
    $userPath = [System.Environment]::GetEnvironmentVariable("Path", "User")
    if ($null -eq $userPath) { $userPath = "" }

    $alreadyThere = $userPath.Split(";") | Where-Object { $_.TrimEnd("\") -ieq $binDir.TrimEnd("\") }
    if ($alreadyThere) {
        Write-Ok "PATH da co $binDir"
    } else {
        $newPath = if ($userPath.Trim().Length -gt 0) { "$userPath;$binDir" } else { $binDir }
        [System.Environment]::SetEnvironmentVariable("Path", $newPath, "User")
        Write-Ok "Da them $binDir vao PATH (User)"
        Write-Warn "Mo terminal MOI de PATH co hieu luc (hoac dung terminal hien tai, script da nap tam thoi)."
    }

    Refresh-ProcessPath
    if ($env:Path.Split(";") -notcontains $binDir) {
        $env:Path = "$env:Path;$binDir"
    }
}

function Invoke-FlutterSetup {
    Write-Step "Kiem tra Flutter"
    & flutter --version
    if ($LASTEXITCODE -ne 0) {
        throw "Chay 'flutter --version' that bai."
    }

    Write-Step "Tat analytics (khong bat buoc, chi de chay khong tuong tac)"
    & flutter config --no-analytics --no-cli-animations | Out-Null
    Write-Ok "Da cau hinh flutter config"

    if (-not $SkipDoctor) {
        Write-Step "flutter doctor (kiem tra Android SDK / Visual Studio / Chrome ...)"
        & flutter doctor -v
        Write-Info "Cac dong [x] hoac [!] o tren la thanh phan can cai them (Android Studio, VS Desktop C++, Chrome)."
    }

    if (-not $SkipPubGet -and (Test-Path (Join-Path $MobileDir "pubspec.yaml"))) {
        Write-Step "flutter pub get trong mobile/"
        Push-Location $MobileDir
        try {
            & flutter pub get
            if ($LASTEXITCODE -ne 0) {
                Write-Warn "flutter pub get that bai. Chay lai thu cong sau: cd mobile; flutter pub get"
            } else {
                Write-Ok "Da tai dependencies cho project mobile/"
            }
        }
        finally {
            Pop-Location
        }
    }
}

Write-Host ""
Write-Host "==============================================" -ForegroundColor White
Write-Host "  Cai dat Flutter SDK cho Doantotnghiep" -ForegroundColor White
Write-Host "  Windows, khong can Administrator" -ForegroundColor White
Write-Host "==============================================" -ForegroundColor White

try {
    Install-Flutter
    Add-FlutterToUserPath
    Invoke-FlutterSetup

    Write-Host ""
    Write-Host "==============================================" -ForegroundColor Green
    Write-Host "  FLUTTER SAN SANG" -ForegroundColor Green
    Write-Host "==============================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Thu muc cai dat : $InstallDir" -ForegroundColor White
    Write-Host "Kiem tra lai    : flutter doctor" -ForegroundColor White
    Write-Host "Chay app mobile : cd mobile && flutter run" -ForegroundColor White
    Write-Host ""
    Write-Host "Neu day la terminal cu (mo truoc khi cai), hay mo terminal MOI de nhan PATH." -ForegroundColor Yellow
    Write-Host ""
}
catch {
    Write-Host ""
    Write-Host ("THAT BAI: " + $_.Exception.Message) -ForegroundColor Red
    if ($_.ScriptStackTrace) {
        Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    }
    exit 1
}
