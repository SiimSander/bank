#!/usr/bin/env sh
set -e

cd "$(dirname "$0")/.."

if [ ! -f vendor/bin/phpunit ]; then
	if [ ! -f composer.phar ]; then
		echo "Installing Composer locally..."
		curl -sS https://getcomposer.org/installer | php
	fi
	php composer.phar install --no-interaction
fi

php vendor/bin/phpunit "$@"
