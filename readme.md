# EasyLogBundle
Human-friendly log files for symfony framework. This is modified version of [EasyCorp/easy-log-handler](https://github.com/EasyCorp/easy-log-handler) due to EasyCorp/easy-log-handler is abandoned and no longer maintained.

[![MIT licensed](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Actions Status](https://github.com/systemsdk/easy-log-bundle/workflows/easy-log-bundle/badge.svg)](https://github.com/systemsdk/easy-log-bundle/actions)

[Source code](https://github.com/systemsdk/easy-log-bundle.git)

## Description
Symfony log files are formatted in the same way for all environments. This means that `dev.log` is optimized for machines instead of humans. The result is a log file bloated with useless information that makes you less productive.

This bundle is a new Monolog handler that creates human-friendly log files. It's optimized to display the log information in a clear and concise way. Use it in the development environment to become a much more productive developer.

## Requirements for EasyLogBundle version 2 or later
* PHP 8.1 or later
* Symfony 6.1 or later
* Monolog bundle 3.0 or later

## Requirements for EasyLogBundle version 1
* PHP 7.4 or later
* Symfony 4.4 or later
* Monolog bundle 1.6 or later up to 2.X

## Contents
1. [Features](#features)
2. [Installation](#installation)
3. [Configuration and Usage](#configuration-and-usage)

----

Features
--------

These are some of the best features of **EasyLogBundle** and how it compares itself with the default Symfony logs.

### Better Log Structure

Symfony log files are a huge stream of text. When you open them, you can't easily tell when a request started or finished and which log messages belong together:

| Symfony | EasyLogBundle
| ------- | --------------
| ![structure-overview-symfony-mini](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony1.png) | ![structure-overview-easylog-mini](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle1.png)

EasyLogBundle structures the log files in a different way:

![structure-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle2.png)

* It adds a large header and some new lines to separate each request logs;
* If the request is less significant (e.g. Assetic requests) the header is more compact and displays less information;
* Log messages are divided internally, so you can better understand their different parts (request, doctrine, security, etc.)

### Less Verbose Logs

First of all, EasyLogBundle doesn't display the timestamp in every log message. In the `dev` environment you shouldn't care about that, so the timestamp is only displayed once for each group of log messages.

| Symfony | EasyLogBundle
| ------- | --------------
| ![timestamps-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony2.png) | ![timestamps-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle3.png)

The `extra` information, which some log messages include to add more details about the log, is displayed only when it's different from the previous log. In contrast, Symfony always displays the `extra` for all logs, generating a lot of duplicated information:

| Symfony
| -------
| ![extra-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony3.png)

| EasyLogBundle
| -------
| ![extra-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle4.png)

It's becoming increasingly popular to use placeholders in log messages instead of the actual values (e.g. `Matched route "{route}".` instead of `Matched route "home".`) This is great for machines, because they can group similar messages that only vary in the placeholder values.

However, for humans this "feature" is disturbing. That's why EasyLogBundle automatically replaces any placeholder included in the log message:

| Symfony
| -------
| ![placeholders-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony4.png)

| EasyLogBundle
| --------------
| ![placeholder-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle5.png)

### Better Visual Hierarchy

Important elements, such as deprecations and security-related messages, must stand out in log files to help you spot them instantly. However, in Symfony all logs look exactly the same. How can you know which are the important ones?

| Symfony
| -------
| ![visual-hierarchy-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony5.png) <br> (all messages look exactly the same)

| EasyLogBundle
| --------------
| ![visual-hierarchy-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle6.png) <br> (deprecations, warnings, errors and security messages stand out)

### Dynamic Variable Inlining

Log messages usually contain related variables in their `context` and `extra` properties. Displaying the content of these variables in the log files is always a tough balance between readability and conciseness.

EasyLogBundle decides how to inline these variables dynamically depending on each log message. For example, Doctrine query parameters are always inlined but request parameters are inlined for unimportant requests and nested for important requests:

![dynamic-inline-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle7.png)

### Stack Traces

When log messages include error stack traces, you definitely want to take a look at them. However, Symfony displays stack traces inlined, making them impossible to inspect. EasyLogBundle displays them as proper stack traces:

| Symfony
| -------
| ![stack-trace-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony6.png)

| EasyLogBundle
| --------------
| ![stack-trace-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle8.png)

### Log Message Grouping

One of the most frustrating experiences when inspecting log files is having lots of repeated or similar consecutive messages. It leads to lack of information and it just distract you. EasyLogBundle process all log messages at once instead of one by one, so it's aware when there are similar consecutive logs.

For example, this is a Symfony log file displaying three consecutive missing translation messages:

![translation-group-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony7.png)

And this is how the same messages are displayed by EasyLogBundle:

![translation-group-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle9.png)

The difference is even more evident for "event notified" messages, which usually generate tens of consecutive messages:

| Symfony
| -------
| ![event-group-symfony](https://systemsdk.com/userfiles/image/easy-log-bundle/symfony8.png)

| EasyLogBundle
| --------------
| ![event-group-easylog](https://systemsdk.com/userfiles/image/easy-log-bundle/easy-log-bundle10.png)


Most log handlers treat each log message separately. In contrast, EasyLogBundle advanced log processing requires each log message to be aware of the other logs (for example to merge similar consecutive messages). This means that all the logs associated with the request must be captured and processed in batch.

Installation
------------

1. If you have installed `easycorp/easy-log-handler` just uninstall it:
```bash
$ composer remove easycorp/easy-log-handler
```
Note: Please remove configuration files `config/packages/easy_log_handler.yaml`, `config/packages/dev/easy_log_handler.yaml`, `config/packages/test/easy_log_handler.yaml`, etc...

2. Allow Flex to use contrib recipes and install the next symfony bundle:
```bash
$ composer config extra.symfony.allow-contrib true
$ composer require --dev systemsdk/easy-log-bundle:*
```

Configuration and Usage
-----------------------

You can change the default configuration in your application by editing the next config file:

```yaml
# config/packages/systemsdk_easy_log.yaml
when@dev:
    monolog:
        handlers:
            buffered:
                type: buffer
                handler: easylog
                level: debug
                channels: [ '!event' ]
            easylog:
                type: service
                id: easy_log.handler
    easy_log:
        log_path: '%kernel.logs_dir%/%kernel.environment%-readable.log'
        max_line_length: 120
        prefix_length: 4
        ignored_routes: [ '_wdt', '_profiler' ]

when@test:
    easy_log:
        log_path: '%kernel.logs_dir%/%kernel.environment%-readable.log'
        max_line_length: 120
        prefix_length: 4
        ignored_routes: [ '_wdt', '_profiler' ]
```
