[CmdletBinding()]
param(
	[switch] $UpdateLock
)

$ErrorActionPreference = 'Stop'

$repositoryRoot = ( Resolve-Path ( Join-Path $PSScriptRoot '..' ) ).Path
$dockerContext  = Join-Path $repositoryRoot 'docker\dependency-build'
$imageName      = 'wp-formvault-dependency-build:php8.1-composer2.10'
$mountArgument  = "type=bind,source=$repositoryRoot,target=/app"

function Invoke-DependencyContainer {
	param(
		[Parameter( Mandatory = $true )]
		[string[]] $ContainerCommand
	)

	$dockerArguments = @(
		'run',
		'--rm',
		'--mount',
		$mountArgument,
		'--workdir',
		'/app',
		$imageName
	) + $ContainerCommand

	& docker @dockerArguments

	if ( 0 -ne $LASTEXITCODE ) {
		throw "Dependency container command failed: $($ContainerCommand -join ' ')"
	}
}

& docker build --tag $imageName $dockerContext

if ( 0 -ne $LASTEXITCODE ) {
	throw 'Unable to build the WP FormVault dependency image.'
}

Invoke-DependencyContainer -ContainerCommand @(
	'php',
	'tools/verify-dependency-platform.php'
)

Invoke-DependencyContainer -ContainerCommand @(
	'composer',
	'--version'
)

if ( $UpdateLock ) {
	Invoke-DependencyContainer -ContainerCommand @(
		'composer',
		'update',
		'--with-all-dependencies',
		'--prefer-dist',
		'--no-interaction',
		'--no-progress'
	)
} elseif ( ! ( Test-Path ( Join-Path $repositoryRoot 'composer.lock' ) ) ) {
	throw 'composer.lock is missing. Run this script once with -UpdateLock after reviewing composer.json.'
} else {
	Invoke-DependencyContainer -ContainerCommand @(
		'composer',
		'install',
		'--prefer-dist',
		'--no-interaction',
		'--no-progress'
	)
}

Invoke-DependencyContainer -ContainerCommand @(
	'composer',
	'validate',
	'--strict'
)

Invoke-DependencyContainer -ContainerCommand @(
	'composer',
	'audit',
	'--locked',
	'--no-dev'
)

Invoke-DependencyContainer -ContainerCommand @(
	'composer',
	'check-platform-reqs',
	'--lock',
	'--no-dev'
)

Invoke-DependencyContainer -ContainerCommand @(
	'composer',
	'run',
	'build-dependencies'
)

Write-Output 'WP FormVault dependency build completed successfully.'
