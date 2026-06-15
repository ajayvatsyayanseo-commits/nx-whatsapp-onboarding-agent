param(
    [Parameter(Mandatory = $true)]
    [string] $Token,

    [string] $Owner = "ajayvatsyayanseo-commits",
    [string] $Repo = "nx-whatsapp-onboarding-agent",
    [string] $ConfigPath = ".\nx-whatsapp-onboarding-agent\.gitsecrate"
)

$ErrorActionPreference = "Stop"

function Invoke-GitHubJson {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Method,

        [Parameter(Mandatory = $true)]
        [string] $Uri,

        [object] $Body = $null
    )

    $headers = @{
        Authorization = "Bearer $Token"
        Accept = "application/vnd.github+json"
        "X-GitHub-Api-Version" = "2022-11-28"
    }

    if ($null -eq $Body) {
        return Invoke-RestMethod -Method $Method -Uri $Uri -Headers $headers
    }

    return Invoke-RestMethod -Method $Method -Uri $Uri -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 10)
}

function Get-VariablesFromSection {
    param(
        [string[]] $Lines,

        [Parameter(Mandatory = $true)]
        [string] $StartPattern,

        [string] $EndPattern = ""
    )

    $inSection = $false
    $variables = @{}

    foreach ($line in $Lines) {
        if ($line -match $StartPattern) {
            $inSection = $true
            continue
        }

        if ($inSection -and $EndPattern -ne "" -and $line -match $EndPattern) {
            break
        }

        if (-not $inSection) {
            continue
        }

        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#") -or -not $trimmed.Contains("=")) {
            continue
        }

        $parts = $trimmed.Split("=", 2)
        $key = $parts[0].Trim()
        $value = $parts[1]

        if ($key -eq "" -or $value -eq "") {
            continue
        }

        $variables[$key] = $value
    }

    return $variables
}

function Get-RepositoryVariables {
    param([string[]] $Lines)

    return Get-VariablesFromSection -Lines $Lines -StartPattern "^# 4\. Repository variables"
}

function Upsert-RepositoryVariable {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Name,

        [Parameter(Mandatory = $true)]
        [string] $Value
    )

    $base = "https://api.github.com/repos/$Owner/$Repo/actions/variables"

    try {
        Invoke-GitHubJson -Method "PATCH" -Uri "$base/$Name" -Body @{ name = $Name; value = $Value } | Out-Null
        Write-Host "Updated repository variable $Name"
    } catch {
        Invoke-GitHubJson -Method "POST" -Uri $base -Body @{ name = $Name; value = $Value } | Out-Null
        Write-Host "Created repository variable $Name"
    }
}

function Ensure-Environment {
    param([Parameter(Mandatory = $true)][string] $Environment)

    $uri = "https://api.github.com/repos/$Owner/$Repo/environments/$Environment"
    Invoke-GitHubJson -Method "PUT" -Uri $uri -Body @{} | Out-Null
    Write-Host "Ensured environment $Environment"
}

function Upsert-EnvironmentVariable {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Environment,

        [Parameter(Mandatory = $true)]
        [string] $Name,

        [Parameter(Mandatory = $true)]
        [string] $Value
    )

    $base = "https://api.github.com/repos/$Owner/$Repo/environments/$Environment/variables"

    try {
        Invoke-GitHubJson -Method "PATCH" -Uri "$base/$Name" -Body @{ name = $Name; value = $Value } | Out-Null
        Write-Host "Updated $Environment environment variable $Name"
    } catch {
        Invoke-GitHubJson -Method "POST" -Uri $base -Body @{ name = $Name; value = $Value } | Out-Null
        Write-Host "Created $Environment environment variable $Name"
    }
}

if (-not (Test-Path -LiteralPath $ConfigPath)) {
    throw "Config file not found: $ConfigPath"
}

$lines = Get-Content -LiteralPath $ConfigPath

$environmentSections = @{
    dev = @{ start = "^# dev Environment variables"; end = "^# staging Environment variables" }
    staging = @{ start = "^# staging Environment variables"; end = "^# prod Environment variables" }
    prod = @{ start = "^# prod Environment variables"; end = "^# 4\. Repository variables" }
}

foreach ($environment in $environmentSections.Keys) {
    Ensure-Environment -Environment $environment
    $vars = Get-VariablesFromSection -Lines $lines -StartPattern $environmentSections[$environment].start -EndPattern $environmentSections[$environment].end
    foreach ($key in ($vars.Keys | Sort-Object)) {
        Upsert-EnvironmentVariable -Environment $environment -Name $key -Value $vars[$key]
    }
}

$repoVars = Get-RepositoryVariables -Lines $lines
foreach ($key in ($repoVars.Keys | Sort-Object)) {
    Upsert-RepositoryVariable -Name $key -Value $repoVars[$key]
}

Write-Host ""
Write-Host "Done. Only GitHub variables were updated. Secrets were not changed."
Write-Host "Add Environment secrets manually in GitHub for APP_KEY, DB_*, and META_WHATSAPP_*."
