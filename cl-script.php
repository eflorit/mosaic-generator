#!/usr/bin/php
<?php

$options = getopt('', array('input:','rows:','columns:','no-thumbs::'));

if(!isset($options['input']) || !isset($options['rows']) || !isset($options['columns']))
{
	print("\n".'Usage: '.$argv[0].' --input {filename} --rows {int} --columns {int} [OPTION]');
	print("\n");
	print("\n".'Options:');
	print("\n".'--no-thumbs     Won\'t regenerate thumbnail database');
	print("\n");

	return 0;
}
else
{
	ini_set('max_execution_time', '20000');
	ini_set('memory_limit',       '1000M');

	require './Mosaic.php';
	$outputfilename = new Mosaic($options['input'], $options['rows'], $options['columns'], !isset($options['no-thumbs']));

	return 0;
}


/*

	::: MYSQL :::

	CREATE DATABASE `mosaic`;

	CREATE TABLE IF NOT EXISTS `thumbnails` (
	  `filename` varchar(255) NOT NULL,
	  `red` smallint(6) NOT NULL,
	  `green` smallint(6) NOT NULL,
	  `blue` smallint(6) NOT NULL
	);

	::: USAGE PHP :::

	$ouput_filename = new Mosaic(string $input_filename, int $rows, int $columns [, bool $gen_thumbs = false ] );

*/