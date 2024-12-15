SHELL := /bin/bash
.ONESHELL:
MAKEFLAGS += --no-print-directory
.SHELLFLAGS = -c -e
PHP_VER = $(shell cat .tmp/ver 2>/dev/null || echo 8.4)
SELECTED_TERM = $(shell cat .tmp/term 2>/dev/null || if [[ $$(which kitty) ]]; then echo kitty ; else echo xterm ; fi)
DOCKER_IMAGE_NAME = term-asteroids_$(PHP_VER)
DOCKER_CONTAINER_NAME = term-asteroids_$(PHP_VER)

.PHONY: help
help: ## Print this message
	@grep -h -Pi '^[a-z0-9_\.-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk '
			BEGIN {FS = ":.*?## "};
			{printf "\033[36m%-50s\033[0m %s\n", $$1, $$2}
		'

.PHONY: select._php
select._php:
	@echo $(_SELECTED_PHP_VER) > .tmp/ver

.PHONY: select.php8.0
select.php8.0: ## Use PHP 8.0
	@$(MAKE) -s select._php _SELECTED_PHP_VER=8.0

.PHONY: select.php8.1
select.php8.1: ## Use PHP 8.1
	@$(MAKE) -s select._php _SELECTED_PHP_VER=8.1

.PHONY: select.php8.2
select.php8.2: ## Use PHP 8.2
	@$(MAKE) -s select._php _SELECTED_PHP_VER=8.2

.PHONY: select.php8.3
select.php8.3: ## Use PHP 8.3
	@$(MAKE) -s select._php _SELECTED_PHP_VER=8.3

.PHONY: select.php8.4
select.php8.4: ## Use PHP 8.4
	@$(MAKE) -s select._php _SELECTED_PHP_VER=8.4

.PHONY: build
build:
	@case "$(PHP_VER)" in
		8.0)
			php_img_ver=8.0.30-bullseye
		;;

		8.1)
			php_img_ver=8.1.30-bullseye
		;;

		8.2)
			php_img_ver=8.2.24-bullseye
		;;

		8.3)
			php_img_ver=8.3.12-bullseye
		;;

		8.4)
			php_img_ver=8.4.1-bullseye
		;;

		*)
			echo "Unsupported PHP version: $(PHP_VER)" >&2
			exit 1
		;;
	esac

	docker build . -t $(DOCKER_IMAGE_NAME) --build-arg phpImageVer=$$php_img_ver

.PHONY: init
init:
	@if [[ ! $$(docker images | grep $(DOCKER_IMAGE_NAME)) ]]
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
			-p $$($(MAKE) -s _resolve_http_port):8000 \
			--name $(DOCKER_CONTAINER_NAME) $(DOCKER_IMAGE_NAME) \
			bash -c "sleep 3650d"
	fi

	if [[ ! -d vendor ]]
	then
		docker exec -it \
			$(DOCKER_CONTAINER_NAME) \
			bash -c 'cd /var/www/html && ./install-composer.sh'
	fi

.PHONY: select._term
select._term:
	@echo $(_NEW_SELECTED_TERM) > .tmp/term

.PHONY: select.term.xterm
select.term.xterm: ## Use xterm as terminal emulator (already installed in the container)
	@$(MAKE) -s select._term _NEW_SELECTED_TERM=xterm

.PHONY: select.term.rxvt
select.term.rxvt: ## Use rxvt as terminal emulator (will be installed in the container)
	@$(MAKE) -s select._term _NEW_SELECTED_TERM=rxvt

.PHONY: select.term.gnome-terminal
select.term.gnome-terminal: ## Use gnome-terminal as terminal emulator (must be installed on your computer)
	@if [[ ! $$(which gnome-terminal) ]]
	then
		echo "gnome-terminal is not installed on your computer" >&2
		exit 1
	fi
	$(MAKE) -s select._term _NEW_SELECTED_TERM=gnome-terminal

.PHONY: select.term.kitty
select.term.kitty: ## Use kitty as terminal emulator (must be installed on your computer)
	@if [[ ! $$(which kitty) ]]
	then
		echo "kitty is not installed on your computer" >&2
		exit 1
	fi
	$(MAKE) -s select._term _NEW_SELECTED_TERM=kitty

.PHONY: select.term.alacritty
select.term.alacritty: ## Use alacritty as terminal emulator (must be installed on your computer)
	@if [[ ! $$(which alacritty) ]]
	then
		echo "alacritty is not installed on your computer" >&2
		exit 1
	fi
	$(MAKE) -s select._term _NEW_SELECTED_TERM=alacritty

.PHONY: _exec
_exec: init
	@case "$(SELECTED_TERM)" in
		xterm)
			docker exec -it \
				$(DOCKER_CONTAINER_NAME) \
				/usr/bin/env LANG=C.UTF-8 /usr/bin/xterm -s -j -geometry 300x77 -fg white -bg black -T TermAsteroids@xterm \
				-e "$(_COMMAND)"
		;;

		rxvt)
			if [[ ! $$(docker exec -it $(DOCKER_CONTAINER_NAME) which rxvt) ]]
			then
				docker exec -it $(DOCKER_CONTAINER_NAME) /bin/bash -c 'sudo apt-get update && sudo apt-get install -y rxvt-unicode'
			fi

			docker exec -it \
				$(DOCKER_CONTAINER_NAME) \
				/usr/bin/env LANG=C.UTF-8 rxvt -ss -j -geometry 305x77 -fg white -bg black -T TermAsteroids@rxvt \
					-e sh -c "$(_COMMAND)"
		;;

		gnome-terminal)
			gnome-terminal -t TermAstetoids@gnome-terminal --maximize --zoom 0.695 --geometry 305x77 -- docker exec -it \
				$(DOCKER_CONTAINER_NAME) \
					sh -c "$(_COMMAND)"
		;;

		kitty)
			kitty -T TermAsteroids@kitty -o font_size=8 --start-as=normal docker exec -it \
				$(DOCKER_CONTAINER_NAME) \
					sh -c "TERM_ASTEROIDS_KITTY_KBP=1 $(_COMMAND)"
		;;

		alacritty)
			alacritty -T TermAsteroids@alacritty -o 'font.size=8' -o 'window.dimensions.columns=305' -o 'window.dimensions.lines=77' -o 'window.startup_mode="Maximized"' -e docker exec -it \
				$(DOCKER_CONTAINER_NAME) \
					sh -c "$(_COMMAND)"
		;;

		*)
			echo "Unsupported terminal emulator: $(SELECTED_TERM)" >&2
			exit 1
		;;
	esac

.PHONY: run
run: ## Run the game
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 index.php --use-native-renderer || sleep 20'

.PHONY: run.no_jit
run.no_jit: ## Run the game without JIT
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 -dopcache.jit=off index.php --use-native-renderer || sleep 20'

.PHONY: run.full_php
run.full_php: init ## Run the game with the PHP rendering backend
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 index.php || sleep 20'

.PHONY: run.full_php.no_jit
run.full_php.no_jit: init ## Run the game with the PHP rendering backend and without JIT
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 -dopcache.jit=off index.php || sleep 20'

.PHONY: run.dev
run.dev: init ## Run the game in dev mode
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 index.php --use-native-renderer --dev-mode || sleep 20'

.PHONY: run.dev.no_jit
run.dev.no_jit: init ## Run the game in dev mode without JIT
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 -dopcache.jit=off index.php --use-native-renderer --dev-mode || sleep 20'

.PHONY: run.dev.debug
run.dev.debug: init ## Run the game in dev mode without JIT and with assertions enabled
	$(MAKE) _exec _COMMAND='php -dzend.assertions=1 -dopcache.jit=off index.php --use-native-renderer --dev-mode || sleep 20'

.PHONY: run.dev.prof
run.dev.prof: init ## Run the game in dev mode without JIT and with the profiler enabled (tracing mode)
	$(MAKE) _exec _COMMAND='SPX_ENABLED=1 SPX_AUTO_START=0 SPX_REPORT=full SPX_SAMPLING_PERIOD=0 php -dzend.assertions=-1 -dopcache.jit=off index.php --use-native-renderer --dev-mode || sleep 20'

.PHONY: run.dev.prof.sampling
run.dev.prof.sampling: init ## Run the game in dev mode without JIT and with the profiler enabled (sampling mode)
	$(MAKE) _exec _COMMAND='SPX_ENABLED=1 SPX_AUTO_START=0 SPX_REPORT=full SPX_SAMPLING_PERIOD=1 php -dzend.assertions=-1 -dopcache.jit=off index.php --use-native-renderer --dev-mode || sleep 20'

.PHONY: run.benchmark.all
run.benchmark.all: init
	$(MAKE) run.benchmark
	$(MAKE) run.benchmark.no_jit
	$(MAKE) run.benchmark.full_php
	$(MAKE) run.benchmark.full_php.no_jit

.PHONY: run.benchmark.all.matrix
run.benchmark.all.matrix:
	@$(eval _ITER_COUNT ?= 1)
	$(MAKE) -s select.term.kitty
	for ((i=1; i<=$(_ITER_COUNT); i++))
	do
		$(MAKE) -s select.php8.0
		$(MAKE) -s run.benchmark.all
		$(MAKE) -s select.php8.1
		$(MAKE) -s run.benchmark.all
		$(MAKE) -s select.php8.2
		$(MAKE) -s run.benchmark.all
		$(MAKE) -s select.php8.3
		$(MAKE) -s run.benchmark.all
		$(MAKE) -s select.php8.4
		$(MAKE) -s run.benchmark.all
	done

.PHONY: run.benchmark.all.matrix.iter.5
run.benchmark.all.matrix.iter.5:
	$(MAKE) -s run.benchmark.all.matrix _ITER_COUNT=5

.PHONY: run.benchmark.generate_report
run.benchmark.generate_report: init
	docker exec -it \
    		$(DOCKER_CONTAINER_NAME) \
    		php generateBenchmarkReport.php

.PHONY: run.benchmark
run.benchmark: init
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 index.php --benchmark-mode --use-native-renderer || sleep 20'

.PHONY: run.benchmark.no_jit
run.benchmark.no_jit: init
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 -dopcache.jit=off index.php --benchmark-mode --use-native-renderer || sleep 20'

.PHONY: run.benchmark.full_php
run.benchmark.full_php: init
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 index.php --benchmark-mode || sleep 20'

.PHONY: run.benchmark.full_php.no_jit
run.benchmark.full_php.no_jit: init
	$(MAKE) _exec _COMMAND='php -dzend.assertions=-1 -dopcache.jit=off index.php --benchmark-mode || sleep 20'

.PHONY: bash
bash: init
	$(MAKE) _exec _COMMAND='bash'

.PHONY: spx.ui
spx.ui: init
	echo "Go to http://localhost:$$($(MAKE) -s _resolve_http_port)/?SPX_KEY=dev&SPX_UI_URI=/"
	docker exec -it \
		$(DOCKER_CONTAINER_NAME) \
		php -S 0.0.0.0:8000

.PHONY: spx.clean
spx.clean: init
	docker exec -it $(DOCKER_CONTAINER_NAME) /bin/bash -c 'rm -rf /tmp/spx/*'

.PHONY: clean
clean: ## Clean everything
	docker ps --format '{{.Names}}' | grep -P '^term-asteroids' | xargs docker stop 2>/dev/null || true
	docker ps -a --format '{{.Names}}' | grep -P '^term-asteroids' | xargs docker rm 2>/dev/null || true
	docker image ls | cut -d ' ' -f1 | grep -P '^term-asteroids' | xargs docker rmi 2>/dev/null || true
	rm -rf $$(ls -d .tmp/* 2>/dev/null | grep -v .gitignore)
	rm -rf vendor composer.lock composer.phar


.PHONY: _resolve_http_port
_resolve_http_port:
	@case "$(PHP_VER)" in
		8.0)
			echo 8000
		;;

		8.1)
			echo 8001
		;;

		8.2)
			echo 8002
		;;

		8.3)
			echo 8003
		;;

		8.4)
			echo 8004
		;;

		*)
			echo "Unsupported PHP version: $(PHP_VER)" >&2
			exit 1
		;;
	esac
