SHELL := /usr/bin/env bash

VERSION := $(shell awk -F'"' '/"version"/ {print $$4; exit}' src/manifest.json)
ARTIFACT := build/EspoDental-$(VERSION).zip

.PHONY: build clean lint stan test deploy-local

build: $(ARTIFACT)

$(ARTIFACT):
	@bin/build

clean:
	rm -rf build

lint:
	@if [ -x vendor/bin/phpcs ]; then \
		vendor/bin/phpcs --standard=PSR12 src/files/custom; \
	else \
		echo "phpcs not installed; run: composer install"; \
	fi

stan:
	@if [ -x vendor/bin/phpstan ]; then \
		vendor/bin/phpstan analyse src/files/custom --level=7; \
	else \
		echo "phpstan not installed; run: composer install"; \
	fi

test:
	@if [ -x vendor/bin/phpunit ]; then \
		vendor/bin/phpunit tests; \
	else \
		echo "phpunit not installed; run: composer install"; \
	fi

# Sync sources into the live module path on a Synology host.
# Override TARGET=/volume1/espomodule if needed.
TARGET ?= /volume1/espomodule
deploy-local:
	rsync -a --delete src/ "$(TARGET)/src/"
