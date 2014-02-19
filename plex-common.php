<?php

/**
 * Plex Media Namer/Organizer
 * 
 * @author    Rob Frawley <robfrawley@gmail.com>
 * @copyright 2013-2014 Rob Frawley
 * @license   MIT License
 */

function doTVEpisodeLookup($show, $season, $episode, $title)
{
	global $traktApiKey;

	$imdbID = doTVShowLookup($show);

	if ($show === null) {
		return [null, null, null];
	}

	$api = "http://api.trakt.tv/show/episode/summary.json/$traktApiKey/$imdbID/$season/$episode";

	$json = @file_get_contents($api);
	$info = @json_decode($json);

	return [
		@$info->show->title     ? $info->show->title     : $show,
		@$info->episode->season ? $info->episode->season : $season,
		@$info->episode->number ? $info->episode->number : $episode,
		@$info->episode->title  ? $info->episode->title  : $title,
		@$info->episode->title  ? true                   : false,
	];
}

function doTVShowLookup($show)
{
	global $traktApiKey;

	$api = "http://api.trakt.tv/search/shows.json/$traktApiKey?query=".urlencode($show);

	$json = @file_get_contents($api);
	$info = @json_decode($json);

	if (!count($info) > 0) {
		return null;
	}

	return @$info[0]->imdb_id;
}

function performInitialTVFilenameParse($filename, $pattern = '#(.*?) ?s?([0-9]{1,2}) ?[xe\.]([0-9]{1,2})#i') 
{
	$matches  = [];
	$basename = basename($filename);

	preg_match($pattern, $basename, $matches, PREG_OFFSET_CAPTURE);

	return [
		isset($matches[1][0]) ? $matches[1][0] : $basename,
		isset($matches[2][0]) ? $matches[2][0] : null,
		isset($matches[3][0]) ? $matches[3][0] : null,
		isset($matches[3][1]) ? substr($basename, $matches[3][1]) : $basename,
	];
}

function performInitialFilenameParse($filename, $pattern = '#(.*?) ?\(?\[?([0-9]{4})\]?\)?#i') 
{
	$matches  = [];
	$basename = basename($filename);

	preg_match($pattern, $basename, $matches, PREG_OFFSET_CAPTURE);

	return [
		isset($matches[2][0]) ? $matches[2][0] : null,
		isset($matches[1][0]) ? $matches[1][0] : $basename,
	];
}

function performScan($dir, $acceptedExts, $subDir = '.') 
{
	$items = [];

	foreach (scandir($dir) as $item) {

		if ($item == '.' || $item == '..' || substr($item, 0, 1) == '.') {
			continue;
		}

		if (is_dir($dir.$item)) {
			$items = array_merge($items, (array)performScan($dir.$item.DIRECTORY_SEPARATOR, $acceptedExts, $subDir.DIRECTORY_SEPARATOR.$item));
		} else {
			if (!in_array(pathinfo($item, PATHINFO_EXTENSION), $acceptedExts)) {
				continue;
			}
			if ($subDir === null) {
				$items[] = $item;
			} else {
				$items[] = $subDir.DIRECTORY_SEPARATOR.$item;
			}
		}
	}

	return $items;
}

function getFileInfo($path)
{
	return [
		pathinfo($path, PATHINFO_FILENAME),
		pathinfo($path, PATHINFO_EXTENSION),
	];
}

function showItemHeader($i, $count)
{
	echo "\n---------------------- [$i of $count] ----------------------\n";
}

function showWelcomeMessage($type) 
{
	echo "Plex Media Namer: $type\n";
	echo "by Rob Frawley\n";
}

function handleCleanup($paths = [])
{
	echo "Performing directory cleanup:\n";

	foreach ($paths as $p) {
		echo "  - $p\n";
		removeEmptyDirectories($p, true);
	}
}

function removeEmptyDirectories($root, $top = false)
{
	$empty = true;
	//echo $root."\n";
	foreach (scandir($root.DIRECTORY_SEPARATOR) as $path) {
		if ($path == '.' || $path == '..') {
			continue;
		}
		//echo $path."\n";
		if (is_dir($root.DIRECTORY_SEPARATOR.$path)) {
			if (removeEmptyDirectories($root.DIRECTORY_SEPARATOR.$path) === false) {
				$empty = false;
			}
		} elseif ($path != '.DS_Store') {
			$empty = false;
		} elseif ($path == '.DS_Store') {
			unlink($root.DIRECTORY_SEPARATOR.$path);
		}
	}

	if ($top === false && $empty === true && $root != '.' && $root != '..') {
		//echo 'YES'."\n";
		rmdir($root);
	}

	return $empty;
}

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
	$morePad = $morePadSplit = 0;
	if (strlen($orig) > 140 || strlen($new) > 140) {
		if (strlen($orig) > strlen($new)) {
			$morePad = strlen($orig)-140;
		} else {
			$morePad = strlen($new)-140;
		}
		$morePadSplit = ceil($morePad/2);
		$morePad = $morePadSplit*2;
	} else {
		$morePad = 56;
		$morePadSplit = ceil($morePad/2);
		$morePad = $morePadSplit*2;
	}

	echo "\n+---<" . str_pad($i, 3, '0', STR_PAD_LEFT) . '/' . str_pad($count, 3, '0', STR_PAD_LEFT) . ">".str_pad('', $morePad, '-')."----------------------------------------------------------------------------------------+";

	echo "\n".str_pad("| Name        : ".basename($new), (101+$morePad), ' ', STR_PAD_RIGHT)."|";
	echo "\n".str_pad("| Original    : ".$orig         , (101+$morePad), ' ', STR_PAD_RIGHT)."|";
	echo "\n".str_pad("| Destination : ".$new          , (101+$morePad), ' ', STR_PAD_RIGHT)."|";

	$dir = pathinfo($new, PATHINFO_DIRNAME);
	if (!is_dir($dir)) {
		mkdir($dir, 0775, true);
	}

	echo "\n+---------------------------------------------------------".str_pad('', $morePad, '-')."-------------------------------------------+\n";

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
