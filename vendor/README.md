# AWS SDK for PHP version 3

This directory contains 4 PHP components:

- aws (AWS php sdk)
- composer
- symphony
- guzzle


Individual licenses apply.

The AWS device adaptor in `../aws` includes this sdk via `autoload.php`.


Licenses
--------

See files:

- aws/aws-sdk-php/LICENSE.md
- composer/LICENSE
- guzzle/guzzle/LICENSE
- symfony/event-dispatcher/Symfony/Component/EventDispatcher/LICENSE


Notes
-----

The AWS device adaptor (in ../aws) includes the aws php sdk as follows:

	aws_generic_connect.php:require 'autoload.php';


Some files in here have been auto-generated.
