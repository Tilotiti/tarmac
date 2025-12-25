# PowerShell script for Windows - Symfony server launcher
# Equivalent to bin/server (bash) for Mac/Linux

# --- Styling utilities -------------------------------------------------------
function Get-TimeStamp {
    return Get-Date -Format "HH:mm:ss"
}

function Write-LogInfo {
    param([string]$Message)
    Write-Host -NoNewline "$(Get-TimeStamp) "
    Write-Host -ForegroundColor Cyan -NoNewline "[INFO]"
    Write-Host " $Message"
}

function Write-LogWarn {
    param([string]$Message)
    Write-Host -NoNewline "$(Get-TimeStamp) "
    Write-Host -ForegroundColor Yellow -NoNewline "[WARN]"
    Write-Host " $Message"
}

function Write-LogError {
    param([string]$Message)
    Write-Host -NoNewline "$(Get-TimeStamp) "
    Write-Host -ForegroundColor Red -NoNewline "[ERROR]"
    Write-Host " $Message"
}

function Write-LogSuccess {
    param([string]$Message)
    Write-Host -NoNewline "$(Get-TimeStamp) "
    Write-Host -ForegroundColor Green -NoNewline "[OK]"
    Write-Host " $Message"
}

# --- Environment -------------------------------------------------------------
# Change to script directory (project root)
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptPath
Set-Location $projectRoot

# Global flag for graceful shutdown
$script:SHUTDOWN_REQUESTED = $false

# Load environment variables (if present)
function Load-EnvFile {
    param([string]$FilePath)
    if (Test-Path $FilePath) {
        Get-Content $FilePath | ForEach-Object {
            $line = $_.Trim()
            # Skip empty lines and comments
            if ($line -and -not $line.StartsWith('#')) {
                if ($line -match '^([^=]+)=(.*)$') {
                    $name = $matches[1].Trim()
                    $value = $matches[2].Trim()
                    # Remove quotes if present
                    if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                        $value = $value.Substring(1, $value.Length - 2)
                    } elseif ($value.StartsWith("'") -and $value.EndsWith("'")) {
                        $value = $value.Substring(1, $value.Length - 2)
                    }
                    [Environment]::SetEnvironmentVariable($name, $value, "Process")
                }
            }
        }
    }
}

if (Test-Path ".env") {
    Load-EnvFile ".env"
} else {
    Write-LogWarn ".env not found; continuing with current environment"
}

if (Test-Path ".env.local") {
    Load-EnvFile ".env.local"
}

$APP_ENV = if ($env:APP_ENV) { $env:APP_ENV } else { "dev" }
$DOMAIN = if ($env:DOMAIN) { $env:DOMAIN } else { "localhost" }

# Pre-flight checks
if (-not (Get-Command symfony -ErrorAction SilentlyContinue)) {
    Write-LogError "Symfony CLI is required but not found. Install from https://symfony.com/download"
    exit 1
}

# Display PHP version
$PHP_VERSION = symfony php -r "echo PHP_VERSION;" 2>&1
Write-Host -NoNewline "$(Get-TimeStamp) "
Write-Host -ForegroundColor Cyan -NoNewline "[INFO]"
Write-Host -NoNewline " Using PHP "
Write-Host -ForegroundColor White -NoNewline $PHP_VERSION
Write-Host ""

# Cleanup function
function Cleanup {
    $script:SHUTDOWN_REQUESTED = $true
    Write-LogInfo "Stopping Symfony server and proxy"
    symfony server:stop 2>&1 | Out-Null
    symfony proxy:stop 2>&1 | Out-Null
    Write-LogSuccess "Shutdown complete"
    exit 0
}

# Register cleanup handler for Ctrl+C
$null = [Console]::TreatControlCAsInput = $false

Write-Host -NoNewline "$(Get-TimeStamp) "
Write-Host -ForegroundColor Cyan -NoNewline "[INFO]"
Write-Host -NoNewline " Starting Transipal server on windows for "
Write-Host -ForegroundColor White -NoNewline $DOMAIN
Write-Host " (env: $APP_ENV)"

# In dev, ensure compiled assets are not forcing stale files
if ($APP_ENV -eq "dev") {
    if (Test-Path "public/assets") {
        Write-LogWarn "Dev mode: clearing compiled assets directory public/assets"
        Remove-Item -Path "public/assets" -Recurse -Force -ErrorAction SilentlyContinue
    } else {
        Write-LogInfo "Dev mode: no compiled assets found (good)"
    }
}

Write-LogInfo "Starting Symfony local proxy"
symfony proxy:start 2>&1 | Out-Null

# Attach domain to proxy
Write-Host -NoNewline "$(Get-TimeStamp) "
Write-Host -ForegroundColor Cyan -NoNewline "[INFO]"
Write-Host -NoNewline " Attaching domain "
Write-Host -ForegroundColor White -NoNewline $DOMAIN
Write-Host " to proxy"
# Remove .wip TLD for proxy attachment and add subdomain wildcard
$PROXY_DOMAIN = $DOMAIN -replace '\.wip$', ''
symfony proxy:domain:attach "*.$PROXY_DOMAIN"

# Configure Xdebug discovery for local debugging (non-breaking if Xdebug absent)
$env:XDEBUG_MODE = "debug"
$env:XDEBUG_CONFIG = "client_port=9005 client_host=127.0.0.1 discover_client_host=1"
Write-LogInfo "Xdebug configured (mode=$env:XDEBUG_MODE)"

# Open browser on the proxy URL
$url = 'https://www.' + $DOMAIN
Write-LogInfo "Opening $url in browser"
Start-Process $url -ErrorAction SilentlyContinue

Write-LogInfo "Starting Symfony server"
symfony serve

# Stream server logs in foreground to keep the script running
Write-LogInfo "Streaming Symfony server logs (press Ctrl+C to stop)"
try {
    symfony server:log
} catch {
    # Handle Ctrl+C gracefully
    Cleanup
}