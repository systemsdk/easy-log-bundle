ifndef INSIDE_DOCKER_CONTAINER
	INSIDE_DOCKER_CONTAINER = 0
endif
export COMPOSE_PROJECT_NAME=easy-log-bundle
HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
PHP_USER := -u www-data
PROJECT_NAME := -p ${COMPOSE_PROJECT_NAME}
INTERACTIVE := $(shell [ -t 0 ] && echo 1)
ERROR_ONLY_FOR_HOST = @printf "\033[33mThis command for host machine\033[39m\n"
ifneq ($(INTERACTIVE), 1)
	OPTION_T := -T
endif

build:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose -f compose.yaml build
else
	$(ERROR_ONLY_FOR_HOST)
endif

start:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose -f compose.yaml $(PROJECT_NAME) up -d
else
	$(ERROR_ONLY_FOR_HOST)
endif

stop:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose -f compose.yaml $(PROJECT_NAME) down
else
	$(ERROR_ONLY_FOR_HOST)
endif

restart: stop start

ssh:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec $(OPTION_T) $(PHP_USER) symfony bash
else
	$(ERROR_ONLY_FOR_HOST)
endif

ssh-root:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec $(OPTION_T) symfony bash
else
	$(ERROR_ONLY_FOR_HOST)
endif

ssh-nginx:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec nginx /bin/sh
else
	$(ERROR_ONLY_FOR_HOST)
endif

exec:
ifeq ($(INSIDE_DOCKER_CONTAINER), 1)
	@$$cmd
else
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec $(OPTION_T) $(PHP_USER) symfony $$cmd
endif

exec-bash:
ifeq ($(INSIDE_DOCKER_CONTAINER), 1)
	@bash -c "$(cmd)"
else
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec $(OPTION_T) $(PHP_USER) symfony bash -c "$(cmd)"
endif

exec-by-root:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose $(PROJECT_NAME) exec $(OPTION_T) symfony $$cmd
else
	$(ERROR_ONLY_FOR_HOST)
endif

test-using-symfony-6:
	@make clean
	@make exec-bash cmd="composer create-project symfony/skeleton:'6.4.x' ."
	@make exec-bash cmd="composer require webapp --no-interaction"
	@make transfer-monolog-config
	@make install-bundle
	@make cache-clear-warmup

test-using-symfony-7:
	@make clean
	@make exec-bash cmd="composer create-project symfony/skeleton:'7.2.x' ."
	@make exec-bash cmd="composer require webapp --no-interaction"
	@make transfer-monolog-config
	@make install-bundle
	@make cache-clear-warmup

clean:
	@make exec-by-root cmd="find . -delete"
	@make exec-by-root cmd="chown -R www-data:www-data /var/www/html"

transfer-monolog-config:
	@make exec-bash cmd="mkdir -p /var/www/html/config/packages/dev && cp --force /tmp/monolog.yaml /var/www/html/config/packages/dev/"

install-bundle:
	@make exec-bash cmd="composer config extra.symfony.allow-contrib true"
	@make exec-bash cmd="composer require --dev systemsdk/easy-log-bundle:*"

cache-clear-warmup:
	@make exec-bash cmd="bin/console cache:clear"
	@make exec-bash cmd="bin/console cache:warmup"

info:
	@make exec cmd="php --version"
	@make exec cmd="bin/console about"

logs:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@docker logs -f ${COMPOSE_PROJECT_NAME}-symfony
else
	$(ERROR_ONLY_FOR_HOST)
endif

logs-nginx:
ifeq ($(INSIDE_DOCKER_CONTAINER), 0)
	@docker logs -f ${COMPOSE_PROJECT_NAME}-nginx
else
	$(ERROR_ONLY_FOR_HOST)
endif
