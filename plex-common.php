<?php

/**
 * Plex Media Namer/Organizer
 * 
 * @author    Rob Frawley <robfrawley@gmail.com>
 * @copyright 2013-2014 Rob Frawley
 * @license   MIT License
 */

function handleQueue($queue)
{
	echo "\nHandling Queue:\n";
	foreach ($queue as $i => $q) {
		$orig = $q[0];
		$new  = $q[1];
		handleFileMove($orig, $new, $i+1, count($queue));
	}
	echo "\n";
}

function handleFileMove($orig, $new, $i = 1, $count = 1)
{
	echo "\n---------------------- [$i of $count] ----------------------\n";
	echo "\nShow : ".basename($new)."\nFrom : ".$orig."\nTo   : ".$new."\n";

	$dir = pathinfo($new, PATHINFO_DIRNAME);
	if (!is_dir($dir)) {
		mkdir($dir, 0775, true);
	}

	rename($orig, $new);
}

function cleanupTitle($t)
{
	$titleRemove = getRemoveList();

	$t = cleanup($t);

	foreach ($titleRemove as $r) {
		$t = preg_replace('#'.$r.'#', '', $t);
	}

	$t = cleanup($t);

	$t = trim($t);
	if (empty($t)) {
		$t = null;
	} else {
		$t = ucwords($t);
	}

	return $t;
}

function cleanup($s) 
{
	$s = str_replace('.', ' ', $s);
	$s = str_replace('-', ' ', $s);
	$s = preg_replace('[^a-z0-9 ]', '', $s);
	$s = preg_replace('[\s+]', ' ', $s);
	$s = trim($s);
	$s = ucwords($s);

	return $s;
}

function getRemoveList($file = 'remove-list.txt') 
{
	if (!is_readable($file)) {
		return [];
	}

	return file($file, FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES);
}

function addToRemoveList($item, $file = 'remove-list.txt') 
{
	if (!is_writable($file)) {
		echo "\nERROR: File not writable!\n";
		sleep(4);
	}

	file_put_contents($file, "\n".$item, FILE_APPEND);
}

function removeFromRemoveList($item, $file = 'remove-list.txt') 
{
	if (!is_writable($file)) {
		echo "\nERROR: File not writable!\n";
		sleep(4);
	}

	$removeList = getRemoveList($file);
	$removeIndex = array_search($item, $removeList, false);

	if ($removeIndex === false) {
		echo "ERROR: Cannot remove the selected item as it does not exist!";
		sleep(4);
	}

	unset($removeList[$removeIndex]);

	file_put_contents($file, implode("\n", $removeList));
}