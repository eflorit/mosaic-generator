Mosaic generator using PHP, GD, MySQL
=====================================

I initially wrote this class was for my personal use. It has been designed to process a large amount of pictures, and generate a mosaic of a specific photo. I have used this script to generate very high quality mosaics, that were printed out as posters. Processing time can take a while, but it's worth it!

A MySQL database is needed to store thumbnail information (especially if you have thousands of pictures).

This is what an outpout looks like:

![demo](https://github.com/eflorit/mosaic-generator/raw/master/examples/output-demo.jpg)

256 rows and 256 columns.
10.000 pictures were used



Installation
============

1. Make sure you have PHP 5, GD and MySQL installed.

2. Create the appropriate database setup

```sql
	CREATE DATABASE `mosaic`;

	USE `mosaic`;

	CREATE TABLE IF NOT EXISTS `thumbnails` (
	  `filename` varchar(255) NOT NULL,
	  `red` smallint(6) NOT NULL,
	  `green` smallint(6) NOT NULL,
	  `blue` smallint(6) NOT NULL
	);
```
3. Copy as many different pictures as you can to ./photos (at least a few hundreds)

4. Modify variables from Mosaic.php to your needs (especially database info)


Usage
=====

From command line:
```
$ ./cl-script.php --input {filename} --rows {int} --columns {int} [--thumbs]

--input                  path to the original picture that shall be recreated
--rows                   number of thumbnails to create per row
--columns                number of thumbnails to create per column
--no-thumbs (optional)   won't (re)generate thumbnails before creating mosaic.
```

...Directly from class:

```php
	$ouput_filename = new Mosaic(string $input_filename, int $rows, int $columns [, bool $gen_thumbs = true ] );
```

Troubleshoot
============

You may need to tweak your php config and increase `max_execution_time` and/or `memory_limit` if you're not using cl-script.php.