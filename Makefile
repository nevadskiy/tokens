install: build dependencies

build:
	docker build -t tokens .

dependencies:
	docker run --rm -v ${PWD}:/app tokens composer install

test:
	docker run --rm -it -v ${PWD}:/app tokens vendor/bin/phpunit
