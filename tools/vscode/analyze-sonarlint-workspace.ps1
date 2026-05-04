param(
    [string] $ReportPath = "var\reports\sonarlint-problems.json",
    [string] $TriggerPath = "var\reports\sonarlint-workspace.request.json",
    [int] $TimeoutSeconds = 180
)

$ErrorActionPreference = "Stop"

function Write-Trigger {
    param([string] $Path)

    $directory = Split-Path -Parent $Path

    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        New-Item -ItemType Directory -Force $directory | Out-Null
    }

    $request = [ordered] @{
        requested_at = (Get-Date).ToString("o")
        reason = "analyze-sonarlint-workspace"
    }

    $request |
        ConvertTo-Json -Depth 5 |
        Set-Content -Encoding UTF8 $Path
}

function Wait-Report {
    param(
        [string] $Path,
        [datetime] $StartedAt,
        [int] $Timeout
    )

    $deadline = (Get-Date).AddSeconds($Timeout)

    while ((Get-Date) -lt $deadline) {
        if (Test-Path $Path) {
            $report = Get-Item $Path

            if ($report.LastWriteTime -ge $StartedAt) {
                return $report
            }
        }

        Start-Sleep -Milliseconds 500
    }

    throw "No se genero $Path en $Timeout segundos. Recarga VSCode y confirma que la extension local esta activa."
}

$startedAt = Get-Date
Write-Trigger -Path $TriggerPath
$report = Wait-Report -Path $ReportPath -StartedAt $startedAt -Timeout $TimeoutSeconds

Write-Host "Analisis SonarLint workspace exportado: $($report.FullName)"
Get-Content $report.FullName -Raw
