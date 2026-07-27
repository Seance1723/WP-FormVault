[CmdletBinding()]
param(
	[string] $TaskFile
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($TaskFile)) {
	$repositoryRoot = Split-Path -Path $PSScriptRoot -Parent
	$TaskFile = Join-Path -Path $repositoryRoot -ChildPath 'TASKS.md'
}

if (-not (Test-Path -LiteralPath $TaskFile -PathType Leaf)) {
	throw "Task register not found: $TaskFile"
}

$graph = @{}

foreach ($line in Get-Content -LiteralPath $TaskFile) {
	if (-not $line.StartsWith('|')) {
		continue
	}

	$columns = $line.Split('|')

	if ($columns.Count -lt 7) {
		continue
	}

	$taskId = $columns[1].Trim()

	if ($taskId -notmatch '^[A-Z]+-[0-9]{3}$') {
		continue
	}

	if ($graph.ContainsKey($taskId)) {
		throw "Duplicate task ID: $taskId"
	}

	$dependencies = @(
		[regex]::Matches($columns[4], '\b[A-Z]+-[0-9]{3}\b') |
			ForEach-Object { $_.Value } |
			Sort-Object -Unique
	)

	$graph[$taskId] = $dependencies
}

if (0 -eq $graph.Count) {
	throw "No tasks were parsed from: $TaskFile"
}

foreach ($taskId in $graph.Keys) {
	foreach ($dependency in $graph[$taskId]) {
		if (-not $graph.ContainsKey($dependency)) {
			throw "Task $taskId references missing dependency $dependency"
		}
	}
}

$visitState = @{}

function Visit-Task {
	param(
		[Parameter(Mandatory = $true)]
		[string] $TaskId,

		[Parameter(Mandatory = $true)]
		[AllowEmptyCollection()]
		[string[]] $Path
	)

	if ($visitState[$TaskId] -eq 1) {
		$cycleStart = [Array]::IndexOf($Path, $TaskId)
		$cycle = @($Path[$cycleStart..($Path.Count - 1)]) + $TaskId

		throw "Task dependency cycle detected: $($cycle -join ' -> ')"
	}

	if ($visitState[$TaskId] -eq 2) {
		return
	}

	$visitState[$TaskId] = 1
	$nextPath = @($Path) + $TaskId

	foreach ($dependency in $graph[$TaskId]) {
		Visit-Task -TaskId $dependency -Path $nextPath
	}

	$visitState[$TaskId] = 2
}

foreach ($taskId in $graph.Keys) {
	Visit-Task -TaskId $taskId -Path @()
}

$edgeCount = 0

foreach ($dependencies in $graph.Values) {
	$edgeCount += $dependencies.Count
}

Write-Output "Task graph verification passed: $($graph.Count) tasks, $edgeCount dependency edges, no missing references, no cycles."
