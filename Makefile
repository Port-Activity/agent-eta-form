install:
	composer install

test:
	API_KEY=foo vendor/bin/phpunit --coverage-html coverage --whitelist src --bootstrap src/lib/init.php tests/

lint: phpcs

phpcs:
	vendor/bin/phpcs --standard=PSR2 --ignore=src/public/js src tests

fix:
	vendor/bin/phpcbf --standard=PSR2 --ignore=src/public/js src tests

version:
	git rev-list --count HEAD > ./version_build_id
	git rev-parse --short HEAD > ./version_hash
	./build_version.sh > src/lib/version


ci-install-dependencies:
	apt update
	apt install -y wget git zip unzip
	wget https://getcomposer.org/installer
	php installer
	php composer.phar install

ci: ci-install-dependencies lint version test
