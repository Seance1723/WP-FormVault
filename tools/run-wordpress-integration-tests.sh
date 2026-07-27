#!/usr/bin/env bash
set -euo pipefail

requested_version="${1:-6.5.0}"
test_suite="${2:-integration}"
test_workspace="$(mktemp -d -t wpfv-wordpress-tests-XXXXXX)"

case "$test_suite" in
	integration|functional|security|performance|required-minimum-mysql|required-current|required-multisite|required-trunk) ;;
	*)
		echo "Unsupported WordPress test suite: $test_suite" >&2
		exit 1
		;;
esac

cleanup() {
	case "$test_workspace" in
		/tmp/wpfv-wordpress-tests-*) rm -rf -- "$test_workspace" ;;
		*) echo "Refusing to clean unexpected test workspace: $test_workspace" >&2 ;;
	esac
}

trap cleanup EXIT

core_dir="$test_workspace/wordpress"
harness_project="$test_workspace/harness"
archive_path="$test_workspace/wordpress-download"

mkdir -p "$harness_project"

case "$requested_version" in
	latest-stable)
		archive_url="https://wordpress.org/latest.tar.gz"
		archive_type="tar"
		;;
	trunk)
		archive_url="https://wordpress.org/nightly-builds/wordpress-latest.zip"
		archive_type="zip"
		;;
	*)
		if [[ ! "$requested_version" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
			echo "Unsupported WordPress test version: $requested_version" >&2
			exit 1
		fi

		download_version="${requested_version%.0}"
		archive_url="https://wordpress.org/wordpress-$download_version.tar.gz"
		archive_type="tar"
		;;
esac

echo "Downloading WordPress test runtime from $archive_url"
curl --fail --location --retry 3 --show-error --silent "$archive_url" --output "$archive_path"

if [[ "$archive_type" == "zip" ]]; then
	unzip -q "$archive_path" -d "$test_workspace"
else
	tar -xzf "$archive_path" -C "$test_workspace"
fi

if [[ ! -f "$core_dir/wp-includes/version.php" ]]; then
	echo "Downloaded WordPress runtime is incomplete." >&2
	exit 1
fi

resolved_version="$(
	php -r 'require $argv[1]; echo $wp_version;' "$core_dir/wp-includes/version.php"
)"

if [[ "$requested_version" == "trunk" ]]; then
	harness_version="dev-master"
else
	harness_version="$resolved_version"

	if [[ "$harness_version" =~ ^[0-9]+\.[0-9]+$ ]]; then
		harness_version="$harness_version.0"
	fi
fi

echo "Resolved WordPress runtime: $resolved_version"
echo "Resolving WordPress PHPUnit library: $harness_version"

composer require \
	--working-dir="$harness_project" \
	--no-interaction \
	--no-progress \
	--prefer-dist \
	"wp-phpunit/wp-phpunit:$harness_version"

export WPFV_WP_CORE_DIR="$core_dir"
export WPFV_WP_TESTS_DIR="$harness_project/vendor/wp-phpunit/wp-phpunit"

php tests/Support/Integration/wait-for-database.php
php vendor/bin/phpunit \
	--configuration=phpunit.integration.xml.dist \
	"--testsuite=$test_suite"
