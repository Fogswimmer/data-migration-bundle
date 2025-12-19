test:
	vendor/bin/phpunit -c tests/phpunit.xml

cs-fix:
	vendor/bin/php-cs-fixer --config=.php-cs-fixer.dist.php fix ./src/
cs:	
	vendor/bin/phpcs --report-full --standard=PSR2 src tests

php-stan:
	vendor/bin/phpstan analyse --level=4 src -c tests/phpstan.neon