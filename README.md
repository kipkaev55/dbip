# DB-IP Reader #

## Description ##

This package provides information about the user's GEO, works with free [DB-IP](https://db-ip.com/).

## Install via Composer ##

We recommend installing this package with [Composer](http://getcomposer.org/).

### Download Composer ###

To download Composer, run in the root directory of your project:

```bash
curl -sS https://getcomposer.org/installer | php
```

You should now have the file `composer.phar` in your project directory.

### Install Dependencies ###

Run in your project root:

```
php composer.phar require kipkaev55/dbip:*
```

You should now have the files `composer.json` and `composer.lock` as well as
the directory `vendor` in your project directory. If you use a version control
system, `composer.json` should be added to it.

### Require Autoloader ###

After installing the dependencies, you need to require the Composer autoloader
from your code:

```php
require 'vendor/autoload.php';
```

## Usage ##

Straightforward:

```php
require_once __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload

use DbIpGeo\Reader;

$geo = new Reader(
  array(
    'type' => 'mysql',
    'host' => '127.0.0.1',
    'db' => array(                   // or use 'db' => 'dbip',
        'name' => 'dbip',            //
        'city' => 'dbip_lookup',     // Optional parameter
        'isp'  => 'dbip_isp',        // Optional parameter
    ),                               //
    'user' => 'root',
    'password' => '123456'
  )
);
var_dump($geo->getGeo('94.137.26.199'));
```

## Copyright and License ##

* This software is Copyright (c) 2017 by [Pro.Motion](http://prmotion.ru).
* This is free software, licensed under the MIT license
* DB-IP licensed under the GNU.
