param(
    [string] $ReportPath = "var\reports\sonarlint-problems.json",
    [string] $TriggerPath = "var\reports\sonarlint-workspace.request.json",
    [string] $StatusPath = "var\reports\sonarlint-workspace.status.json",
    [string[]] $Files = @(),
    [int] $TimeoutSeconds = 300
)

$ErrorActionPreference = "Stop"

function Write-Trigger {
    param(
        [string] $Path,
        [string[]] $SelectedFiles
    )

    $directory = Split-Path -Parent $Path

    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        New-Item -ItemType Directory -Force $directory | Out-Null
    }

    $request = [ordered] @{
        requested_at = (Get-Date).ToString("o")
        reason = "analyze-sonarlint-workspace"
    }

    if ($SelectedFiles.Count -gt 0) {
        $request.files = @($SelectedFiles)
    }

    $request |
        ConvertTo-Json -Depth 5 |
        Set-Content -Encoding UTF8 $Path
}

function Read-StatusFile {
    param([string] $Path)

    try {
        return Get-Content $Path -Raw | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Write-CompletionReport {
    param(
        [string] $ReportPath,
        [string] $StatusPath,
        [string] $Message
    )

    $directory = Split-Path -Parent $ReportPath

    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        New-Item -ItemType Directory -Force $directory | Out-Null
    }

    $payload = [ordered]@{
        generated_at = (Get-Date).ToString("o")
        exporter = 'skills/review-sonarqube-clean-code/scripts/analyze-sonarlint-workspace.ps1'
        exporter_build = 'workspace-analysis-v3'
        total = 0
        issues = @()
        fallback = $Message
    }

    $payload | ConvertTo-Json -Depth 10 | Set-Content -Encoding UTF8 $ReportPath

    $statusPayload = [ordered]@{
        requested_at = (Get-Date).ToString("o")
        finished_at = (Get-Date).ToString("o")
        state = 'error'
        message = $Message
    }

    $statusPayload | ConvertTo-Json -Depth 10 | Set-Content -Encoding UTF8 $StatusPath
}

function Wait-AnalysisResult {
    param(
        [string] $ReportPath,
        [string] $StatusPath,
        [datetime] $StartedAt,
        [int] $Timeout
    )

    $deadline = (Get-Date).AddSeconds($Timeout)

    while ((Get-Date) -lt $deadline) {
        if (Test-Path $ReportPath) {
            $report = Get-Item $ReportPath

            if ($report.LastWriteTime -ge $StartedAt) {
                try {
                    $parsed = Get-Content $ReportPath -Raw | ConvertFrom-Json
                    if ($null -ne $parsed -and [int]$parsed.total -ge 0) {
                        return $report
                    }
                } catch {
                }
            }
        }

        if (Test-Path $StatusPath) {
            $statusFile = Get-Item $StatusPath

            if ($statusFile.LastWriteTime -ge $StartedAt) {
                $statusData = Read-StatusFile -Path $StatusPath

                if ($null -ne $statusData -and $statusData.state -eq "error") {
                    $message = if ([string]::IsNullOrWhiteSpace([string] $statusData.message)) {
                        "El analisis SonarLint se aborto sin mensaje adicional."
                    } else {
                        [string] $statusData.message
                    }

                    Write-CompletionReport -ReportPath $ReportPath -StatusPath $StatusPath -Message $message
                    throw "El analisis SonarLint se aborto: $message"
                }

                if ($null -ne $statusData -and $statusData.state -eq "completed") {
                    return (Get-Item $ReportPath)
                }
            }
        }

        Start-Sleep -Milliseconds 500
    }

    $message = "No se genero $ReportPath en $Timeout segundos. Recarga VSCode y confirma que la extension local esta activa."
    Write-CompletionReport -ReportPath $ReportPath -StatusPath $StatusPath -Message $message
    throw $message
}

$startedAt = Get-Date
Write-Trigger -Path $TriggerPath -SelectedFiles $Files

try {
    $report = Wait-AnalysisResult -ReportPath $ReportPath -StatusPath $StatusPath -StartedAt $startedAt -Timeout $TimeoutSeconds
    Write-Host "Analisis SonarLint workspace exportado: $($report.FullName)"
    Get-Content $report.FullName -Raw
} catch {
    $message = $_.Exception.Message
    Write-CompletionReport -ReportPath $ReportPath -StatusPath $StatusPath -Message $message
    Write-Host $message
    Write-Host "Se ha escrito un reporte de estado en $ReportPath"
    Get-Content $ReportPath -Raw
}
