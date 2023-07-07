SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS = -c -e
DOCKER_IMAGE_NAME := term-asteroids
DOCKER_CONTAINER_NAME := term-asteroids

.PHONY: help
help: ## Print this message
	@grep -h -Pi '^[a-z0-9_\.-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk '
			BEGIN {FS = ":.*?## "};
			{printf "\033[36m%-50s\033[0m %s\n", $$1, $$2}
		'

.PHONY: build
build:
	docker build . -t $(DOCKER_IMAGE_NAME)

.PHONY: init
init:
	if [[ ! $$(docker images | grep $(DOCKER_IMAGE_NAME)) ]]
	then
		$(MAKE) build
	fi

	if [[ ! $$(docker ps | grep $(DOCKER_CONTAINER_NAME)) ]]
	then
		if [[ $$(docker ps -a | grep $(DOCKER_CONTAINER_NAME)) ]]
		then
			docker rm $(DOCKER_CONTAINER_NAME)
		fi

		docker run -d \
			-e DISPLAY=${DISPLAY} \
			-v /tmp/.X11-unix:/tmp/.X11-unix \
			-v $$(pwd):/var/www/html \
			-p 8000:8000 \
			--name $(DOCKER_CONTAINER_NAME) $(DOCKER_IMAGE_NAME) \
			bash -c "sleep 3650d"
	fi

	if [[ ! -d vendor ]]
	then
		docker exec -it \
			$(DOCKER_CONTAINER_NAME) \
			bash -c 'cd /var/www/html && ./install-composer.sh'
	fi

.PHONY: run
run: init ## Run the game
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 index.php --use-native-renderer || sleep 20'

.PHONY: run.no_jit
run.no_jit: init ## Run the game without JIT
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 -dopcache.jit=off index.php --use-native-renderer || sleep 20'

.PHONY: run.full_php
run.full_php: init ## Run the game with the PHP rendering backend
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 index.php || sleep 20'

.PHONY: run.full_php.no_jit
run.full_php.no_jit: init ## Run the game with the PHP rendering backend and without JIT
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 -dopcache.jit=off index.php || sleep 20'

.PHONY: run.benchmark.all
run.benchmark.all: init
	$(MAKE) run.benchmark
	$(MAKE) run.benchmark.no_jit
	$(MAKE) run.benchmark.full_php
	$(MAKE) run.benchmark.full_php.no_jit
	$(MAKE) run.benchmark.generate_report

.PHONY: run.benchmark.generate_report
run.benchmark.generate_report: init
	docker exec -it \
    		$(DOCKER_CONTAINER_NAME) \
    		php generateBenchmarkReport.php

.PHONY: run.benchmark
run.benchmark: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 index.php --benchmark-mode --use-native-renderer || sleep 20'

.PHONY: run.benchmark.no_jit
run.benchmark.no_jit: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 -dopcache.jit=off index.php --benchmark-mode --use-native-renderer || sleep 20'

.PHONY: run.benchmark.full_php
run.benchmark.full_php: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 index.php --benchmark-mode || sleep 20'

.PHONY: run.benchmark.full_php.no_jit
run.benchmark.full_php.no_jit: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 -dopcache.jit=off index.php --benchmark-mode || sleep 20'

.PHONY: run.dev
run.dev: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 index.php --dev-mode || sleep 20'

.PHONY: run.dev.no_jit
run.dev.no_jit: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=-1 -dopcache.jit=off index.php --dev-mode || sleep 20'

.PHONY: run.dev.debug
run.dev.debug: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'php -dzend.assertions=1 -dopcache.jit=off index.php --dev-mode || sleep 20'

.PHONY: run.dev.prof
run.dev.prof: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'SPX_ENABLED=1 SPX_AUTO_START=0 SPX_REPORT=full SPX_SAMPLING_PERIOD=0 php -dzend.assertions=-1 -dopcache.jit=off index.php --dev-mode || sleep 20'

.PHONY: run.dev.prof.sampling
run.dev.prof.sampling: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized -T TermAsteroids \
		-e 'SPX_ENABLED=1 SPX_AUTO_START=0 SPX_REPORT=full SPX_SAMPLING_PERIOD=10 php -dzend.assertions=-1 -dopcache.jit=off index.php --dev-mode || sleep 20'

.PHONY: xterm
xterm: init
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -maximized

.PHONY: spx_ui
spx_ui: init
	echo "Go to http://localhost:8000/?SPX_KEY=dev&SPX_UI_URI=/"
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		php -S 0.0.0.0:8000

.PHONY: clean
clean: ## Clean everything
	docker stop $(DOCKER_CONTAINER_NAME) || true
	docker rm $(DOCKER_CONTAINER_NAME) || true
	docker rmi $(DOCKER_IMAGE_NAME) || true
	rm -rf $$(ls -d .tmp/* | grep -v .gitignore)
	rm -rf vendor composer.lock composer.phar
