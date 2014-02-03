#!/usr/bin/env php
<?php

/**
 * Plex Media Namer/Organizer
 * 
 * @author    Rob Frawley <robfrawley@gmail.com>
 * @copyright 2013-2014 Rob Frawley
 * @license   MIT License
 */

$dirIn  = '/Users/rmf/Torrents/QueueMovies/';
$dirOut = '/Volumes/External-2/Videos/Movies/';

$files  = scandir($dirIn);
$count  = count($files);
$i      = 1;
$queue  = [];

require __DIR__.DIRECTORY_SEPARATOR.'plex-common.php';

echo "Plex Media Namer: Movies\n";
echo "by Rob Frawley\n";

foreach ($files as $f) {

	if ($f == '.' || $f == '..' || substr($f, 0, 1) == '.') { 
		continue; 
	}

	echo "\n---------------------- [$i of $count] ----------------------\n";

	$filename = pathinfo($f, PATHINFO_FILENAME);
	$fileext  = pathinfo($f, PATHINFO_EXTENSION);

	list($yearMaybe, $showMaybe, $titleMaybe) = performInitialFilenameParse($filename);

	$loop      = true;
	$skip      = false;

	while($loop) {

		echo "\nMovie         : $showMaybe\n";
		/*echo "Title         : ";
		if ($titleMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$titleMaybe\n";
		}*/
		echo "Year          : ";
		if ($yearMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$yearMaybe\n";
		}

		echo "\n";
		echo "Original Path : $f\n";
		echo "New Path      : ".getNewName($showMaybe, $titleMaybe, $yearMaybe, $fileext)."\n";
		echo "Filesize      : ".floor(filesize($dirIn.$f)/1024/1024)."MB\n";

		if (floor(filesize($dirIn.$f)/1024/1024) < 100) {
			echo "Note          : LIKELY A SAMPLE FILE!!!!\n";
		}

		echo "\nActions:".
		     "\n\tm. Edit movie name".
		     //"\n\tt. Edit extended title".
		     "\n\ty. Edit the year".
		     "\n\tp. Move to nrop".
		     "\n\tx. Add item to the remove list".
		     "\n\ta. Add current title to the remove list".
		     "\n\tz. Remove item from the remove list".
		     "\n\tk. Skip this movie".
             "\n\tD. Skip this movie and delete file".
		     "\n\tg. Perform operation...go!".
		     "\n\nWhat would you like to do? [g]: ";

		$input = trim(fgets(STDIN));
		if (empty($input)) {
			$input = 'g';
		}

		switch ($input) {

			case 'm':
				echo "Enter new movie name: ";
				$showMaybe = trim(fgets(STDIN));
				continue;

			case 't':
				echo "Enter new extended title: ";
				$titleMaybe = cleanupTitle(trim(fgets(STDIN)));
				if (empty($titleMaybe)) {
					$titleMaybe = null;
				}
				continue;

			case 'p':
				$dirOut = '/Volumes/External-2/Videos/Nrop/';
				echo "Will not save to $dirOut...";
				$showMaybe = $filename;
				$yearMaybe = null;
				continue;

			case 'y':
				echo "Enter new year: ";
				$yearMaybe = trim(fgets(STDIN));
				continue;

			case 'k':
				$skip = true;
				break;

            case 'D':
                    $skip = true;
                    unlink($dirIn.$f);
                    break;

			case 'x':
				echo "\nCurrent items in remove list: \"".implode('", "', getRemoveList())."\"\n\n";
				echo "Enter the regex you would like to add: ";
				$removeListItem = trim(fgets(STDIN));
				if (!empty($removeListItem)) {
					addToRemoveList($removeListItem);
					$titleMaybe = cleanupTitle($titleMaybe);
				}
				continue;

			case 'a':
				if (!empty($titleMaybe)) {
					addToRemoveList($titleMaybe);
					$titleMaybe = cleanupTitle($titleMaybe);
				}
				continue;

			case 'z':
				echo "\nCurrent items in remove list: \"".implode('", "', getRemoveList())."\"\n\n";
				echo "Enter the item you would like to remove: ";
				$removeListItem = trim(fgets(STDIN));
				if (!empty($removeListItem)) {
					removeFromRemoveList($removeListItem);
					list(, , , , , , , , $titleMaybe) = performInitialFilenameParse($filename);
				}
				continue;

			case 'g':
				$show  = $showMaybe;
				$title = $titleMaybe;
				$year  = $yearMaybe;
				$loop  = false;

				break;

			default:

				echo "\nError:\n\tAn invalid action option was provided.\n\tPlease try again";
				sleep(1);
				echo ".";
				sleep(1);
				echo ".";
				sleep(1);
				echo ".";
				sleep(1);
				echo "\n";
				break;
		}

		echo "\n";

		if ($skip === true) {
			break;
		}

	}

	if ($skip !== true) {
		$path = $dirOut.getNewName($show, $title, $year, $fileext);

		echo "Queued: $path\n";

		$queue[] = [
			$dirIn.$f,
			$path
		];
	} else {
		echo "Skipping!\n";
		$skip = false;
	}

	$i++;

}

if (count($queue) > 0) {
	handleQueue($queue);
} else {
	echo "\nNo items found...\n\n";
}

echo "Complete!\n";

function performInitialFilenameParse($filename, $pattern = '#([0-9]{4})#i') 
{
	$matches       = [];
	$filenameClean = preg_replace('#[^0-9a-z ]#i', ' ', $filename);
	preg_match($pattern, $filenameClean, $matches, PREG_OFFSET_CAPTURE);

	$year            = isset($matches[0][0]) ? $matches[0][0] : '';
	$offset          = isset($matches[0][1]) ? $matches[0][1] : 0;
	$offsetEnd       = isset($matches[2][1]) ? $matches[2][1]+strlen($matches[2][0]) : 0;
	$showMaybe       = cleanup(substr($filenameClean, 0, $offset));
	$titleMaybe      = cleanupTitle(substr($filenameClean, $offsetEnd));

	return [
		$year,
		$showMaybe,
		$titleMaybe
	];
}

function getNewName($show, $title, $year, $ext)
{
	$r = "$show";

	if ($year != null) {
		$r = $r." [$year]";
	}

	$r = $r.DIRECTORY_SEPARATOR;

	$r = $r."$show";

	if ($year != null) {
		$r = $r." [$year]";
	}

	if ($title != null || !empty($title)) {
		//$r = $r." ($title)";
	}

	$r = $r.".$ext";

	return $r;
}