EasyLogBundle
==========================
Human-friendly log files for symfony framework. Forked from EasyCorp/easy-log-handler
due to EasyCorp/easy-log-handler is abandoned and no longer maintained.

Description
------------
Symfony log files are formatted in the same way for all environments.
This means that ``dev.log`` is optimized for machines instead of humans.
The result is a log file bloated with useless information that makes you less productive.

This bundle is a new Monolog handler that creates human-friendly log files.
It's optimized to display the log information in a clear and concise way.
Use it in the development environment to become a much more productive developer.

See more details and description in readme.md

Installation
------------

1. If you have installed `easycorp/easy-log-handler`_ just uninstall it:

.. code-block:: bash

    $ composer remove easycorp/easy-log-handler

Note: Please remove configuration files ``config/packages/easy_log_handler.yaml``,
``config/packages/dev/easy_log_handler.yaml``, ``config/packages/test/easy_log_handler.yaml``, etc...

2. Edit your ``config/packages/dev/monolog.yaml`` and ``config/packages/test/monolog.yaml`` and put next configuration:

.. configuration-block::

    .. code-block:: yaml

        monolog:
            handlers:
                buffered:
                    type: buffer
                    handler: easylog
                    level: debug
                    channels: ['!event']
                easylog:
                    type: service
                    id: easy_log.handler

In the above configuration, the ``buffered`` handler saves all log messages and then passes them to the EasyLog bundle,
which processes all messages at once and writes the result in the log file.
Use the ``buffered`` handler to configure the channels logged/excluded and the level of the messages being logged.

3. Allow Flex to use contrib recipes and install next symfony bundle:

.. code-block:: bash
    $ composer config extra.symfony.allow-contrib true
    $ composer require --dev systemsdk/easy-log-bundle:*


Configuration and Usage
-----------------------

You can change default configuration in your application by editing next config file:

.. configuration-block::

    .. code-block:: yaml

        # config/packages/dev/systemsdk_easy_log.yaml
        easy_log:
            log_path: '%kernel.logs_dir%/%kernel.environment%-readable.log'
            max_line_length: 120
            prefix_length: 2
            ignored_routes: ['_wdt', '_profiler']

.. _`easycorp/easy-log-handler`: https://github.com/EasyCorp/easy-log-handler
