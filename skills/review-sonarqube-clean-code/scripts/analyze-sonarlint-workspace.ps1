param(
    [string] $ReportPath = "var\reports\sonarlint-problems.json",
    [string] $TriggerPath = "var\reports\sonarlint-workspace.request.json",
    [string] $StatusPath = "var\reports\sonarlint-workspace.status.json",
    [string[]] $Files = @(),
    [int] $TimeoutSeconds = 180
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

function Wait-AnalysisResult {
    param(
        [string] $Report,
        [string] $Status,
        [datetime] $StartedAt,
        [int] $Timeout
    )

    $deadline = (Get-Date).AddSeconds($Timeout)

    while ((Get-Date) -lt $deadline) {
        if (Test-Path $Report) {
            $report = Get-Item $Report

            if ($report.LastWriteTime -ge $StartedAt) {
                return $report
            }
        }

        if (Test-Path $Status) {
            $statusFile = Get-Item $Status

            if ($statusFile.LastWriteTime -ge $StartedAt) {
                $status = Read-StatusFile -Path $Status

                if ($null -ne $status -and $status.state -eq "error") {
                    $message = if ([string]::IsNullOrWhiteSpace([string] $status.message)) {
                        "El analisis SonarLint se aborto sin mensaje adicional."
                    } else {
                        [string] $status.message
                    }

                    throw "El analisis SonarLint se aborto: $message"
                }

                if ($null -ne $status -and $status.state -eq "completed") {
                    throw "El analisis SonarLint finalizo sin generar un reporte actualizado."
                }
            }
        }

        Start-Sleep -Milliseconds 500
    }

    throw "No se genero $Report en $Timeout segundos. Recarga VSCode y confirma que la extension local esta activa."
}

$startedAt = Get-Date
Write-Trigger -Path $TriggerPath -SelectedFiles $Files
$report = Wait-AnalysisResult -Report $ReportPath -Status $StatusPath -StartedAt $startedAt -Timeout $TimeoutSeconds

Write-Host "Analisis SonarLint workspace exportado: $($report.FullName)"
Get-Content $report.FullName -Raw
