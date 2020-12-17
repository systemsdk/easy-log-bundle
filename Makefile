dir=${CURDIR}
export COMPOSE_PROJECT_NAME=easy_log_bundle
project=-p ${COMPOSE_PROJECT_NAME}
service=${COMPOSE_PROJECT_NAME}:latest
interactive:=$(shell [ -t 0 ] && echo 1)
ifneq ($(interactive),1)
	optionT=-T
endif

build:
	@docker-compose -f docker-compose.yml build

start:
	@docker-compose -f docker-compose.yml $(project) up -d

stop:
	@docker-compose -f docker-compose.yml $(project) down

restart: stop start

ssh:
	@docker-compose $(project) exec $(optionT) symfony bash

ssh-nginx:
	@docker-compose $(project) exec nginx /bin/sh

exec:
	@docker-compose $(project) exec $(optionT) symfony $$cmd

exec-bash:
	@docker-compose $(project) exec $(optionT) symfony bash -c "$(cmd)"

test-using-symfony-4:
	@make clean
	@make exec-bash cmd="composer create-project symfony/website-skeleton . ^4.4"
	@make transfer-monolog-config
	@make install-bundle
	@make cache-clear-warmup

test-using-symfony-5:
	@make clean
	@make exec-bash cmd="composer create-project symfony/website-skeleton . ^5.0"
	@make transfer-monolog-config
	@make install-bundle
	@make cache-clear-warmup

clean:
	@make exec-bash cmd="find . -delete"

transfer-monolog-config:
	@make exec-bash cmd="cp --force /tmp/monolog.yaml /var/www/html/config/packages/dev/"

install-bundle:
	@make exec-bash cmd="composer require --dev systemsdk/easy-log-bundle"

cache-clear-warmup:
	@make exec-bash cmd="bin/console cache:clear"
	@make exec-bash cmd="bin/console cache:warmup"

info:
	@make exec cmd="bin/console --version"
	@make exec cmd="php --version"

logs:
	@docker logs -f ${COMPOSE_PROJECT_NAME}_symfony

logs-nginx:
	@docker logs -f ${COMPOSE_PROJECT_NAME}_nginx
