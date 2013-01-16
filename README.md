Phubic
========

Phubic is a PHP helper library for [OVH Hubic Cloud storage service](https://app.hubic.me/, "hubic @ OVH").

Read before anything else
------------

*   This library is not supported by OVH.
*   As OVH does not provide official API (yet), i coded this library using reverse engineering on the web application.
*   For the moment the library is in development, all functions are not implemented yet. Be patient ;)
*   And the more important : YOU ARE RESPONSIBLE OF WHAT YOU DO !

Requirement
------------

Your PHP setup need [Curl](http://php.net/manual/en/book.curl.php "Curl for PHP")


Examples
------------

Here is a CLI example :

```php
<?
require_once'../src/phubic.php';

// Config (tempDir must be writable)
$config = array('login'=>'YOUR HUBIC LOGIN HERE ','passwd'=>'YOUR HUBIC PASSWD HERE', 'tempDir'=>'/tmp/');
$phubic=new Phubic($config);

/*
 * Get Settings
 */
$settings=$phubic->getSettings();
var_dump($settings);

/*
 * Upload
 */
$phubic->upload('/local/path/to/file','/hubic/path/');

/*
 * List Folder
 */
$l=$phubic->listFolder('/hubic/path/');
var_dump($l);
```
