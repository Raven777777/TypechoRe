$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $toolDir = Join-Path $root '.tools'
    $phpstan = Join-Path $toolDir 'phpstan/phpstan.phar'
    $venv = Join-Path $toolDir 'semgrep-venv'
    $windows = $env:OS -eq 'Windows_NT'
    $venvPython = Join-Path $venv $(if ($windows) { 'Scripts/python.exe' } else { 'bin/python' })
    $semgrep = Join-Path $venv $(if ($windows) { 'Scripts/semgrep.exe' } else { 'bin/semgrep' })

    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $phpstan) | Out-Null
    if (-not (Test-Path $phpstan)) {
        Invoke-WebRequest 'https://github.com/phpstan/phpstan/releases/download/2.3.0/phpstan.phar' -OutFile $phpstan
    }
    if (-not (Test-Path $venvPython)) {
        $python = Get-Command python, python3 -ErrorAction SilentlyContinue | Select-Object -First 1
        if (-not $python) { throw 'Python 3 is required to install Semgrep.' }
        & $python.Source -m venv $venv
        if ($LASTEXITCODE -ne 0) { throw 'Could not create the local Semgrep environment.' }
    }
    if (-not (Test-Path $semgrep)) {
        & $venvPython -m pip install --disable-pip-version-check 'semgrep==1.179.0'
        if ($LASTEXITCODE -ne 0) { throw 'Could not install Semgrep.' }
    }

    if ($env:PHP_EXE) {
        $php = $env:PHP_EXE
    } elseif ($windows -and (Test-Path (Join-Path $root 'php-8.5.10/php.exe'))) {
        $php = Join-Path $root 'php-8.5.10/php.exe'
    } else {
        $phpCommand = Get-Command php -ErrorAction SilentlyContinue
        if (-not $phpCommand) { throw 'PHP CLI was not found. Set PHP_EXE or add PHP to PATH.' }
        $php = $phpCommand.Source
    }

    $phpFiles = @(
        Get-ChildItem admin, install, var, usr, tests, tools -Recurse -File -Filter '*.php'
        Get-ChildItem -File -Filter '*.php'
    )
    foreach ($file in $phpFiles) {
        $lintOutput = & $php -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host $lintOutput
            throw "PHP syntax check failed: $($file.FullName)"
        }
    }
    Write-Host "PHP syntax: $($phpFiles.Count) files passed"

    & $php tests/smoke.php
    if ($LASTEXITCODE -ne 0) { throw 'Smoke tests failed.' }

    & $php tests/webauthn.php
    if ($LASTEXITCODE -ne 0) { throw 'WebAuthn ceremony tests failed.' }

    & $php tools/audit-override.php
    if ($LASTEXITCODE -ne 0) { throw 'PHP #[\Override] audit failed.' }

    # 数据库适配器集成测试: 默认只跑 SQLite; MySQL/PostgreSQL 需通过
    # TYPECHORE_TEST_ADAPTER / TYPECHORE_TEST_HOST 等环境变量指定
    & $php tests/integration.php
    if ($LASTEXITCODE -ne 0) { throw 'Database integration test failed.' }

    & $venvPython tests/build_release.py
    if ($LASTEXITCODE -ne 0) { throw 'Release package exclusion test failed.' }

    & $php $phpstan analyse --configuration=phpstan.neon --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'PHPStan failed.' }

    # 真实站点端到端回归: 用发布包 + PHP 内置服务器跑安装向导/后台/发文/评论/上传/升级
    & $php tools/e2e.php
    if ($LASTEXITCODE -ne 0) { throw 'End-to-end site test failed.' }

    $semgrepArgs = @(
        'scan',
        '--config=p/php',
        '--config=p/security-audit',
        '--config=p/owasp-top-ten',
        '--config=p/secrets',
        '--include=*.php',
        '--metrics=off',
        '--error',
        'admin', 'install', 'var', 'usr', 'tests', 'index.php', 'install.php'
    )

    # 捕获输出时临时关闭 Stop 偏好: 原生命令的 stderr 重定向会产生 ErrorRecord,
    # 在 $ErrorActionPreference='Stop' 下会直接中断脚本。
    $semgrepErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $semgrepOutput = & $semgrep @semgrepArgs 2>&1
    $semgrepExit = $LASTEXITCODE
    $ErrorActionPreference = $semgrepErrorAction

    $semgrepOutput | ForEach-Object { Write-Host $_ }

    if ($semgrepExit -ne 0) {
        # SEMGREP_APP_TOKEN 过期/无效时 semgrep.dev 返回 401, 规则无法下载;
        # 此时降级为公共规则重跑一次, 而不是让整个质量门禁误报失败。
        $registryRejected = $env:SEMGREP_APP_TOKEN -and (($semgrepOutput | Out-String) -match 'HTTP 40[13]|invalid configuration file found')
        if ($registryRejected) {
            Write-Warning 'Semgrep registry rejected SEMGREP_APP_TOKEN (expired or invalid); retrying with public rules only. Rotate the token to re-enable Pro rules.'
            Remove-Item Env:SEMGREP_APP_TOKEN -ErrorAction SilentlyContinue
            $ErrorActionPreference = 'Continue'
            & $semgrep @semgrepArgs
            $semgrepExit = $LASTEXITCODE
            $ErrorActionPreference = $semgrepErrorAction
            if ($semgrepExit -ne 0) { throw 'Semgrep failed or reported findings.' }
        } else {
            throw 'Semgrep failed or reported findings.'
        }
    }
} finally {
    Pop-Location
}
