#!/usr/bin/env php
<?php

/**
 * Plex Media Namer/Organizer
 * 
 * @author    Rob Frawley <robfrawley@gmail.com>
 * @copyright 2013-2014 Rob Frawley
 * @license   MIT License
 */

$acceptedExts = ['mp4','mkv','avi','mov'];
$dirIn  	  = '/Users/rmf/Torrents/QueueMovies/';
$dirOut 	  = '/Volumes/External-2/Videos/Movies/';

require __DIR__.DIRECTORY_SEPARATOR.'plex-common.php';

$files  = performScan($dirIn, $acceptedExts);
$count  = count($files);
$i      = 1;
$queue  = [];

showWelcomeMessage('Movie');

foreach ($files as $f) {

	showFileHeader($i, $count);

	$filename = pathinfo($f, PATHINFO_FILENAME);
	$fileext  = pathinfo($f, PATHINFO_EXTENSION);

	list($yearMaybe, $showMaybe) = performInitialFilenameParse($filename);
	$movieInfos = doLookup($showMaybe, $yearMaybe);

	if (count($movieInfos) > 0) {
		$yearMaybe = $movieInfos[0]->Year;
		$showMaybe = $movieInfos[0]->Title;
		$idMaybe   = $movieInfos[0]->imdbID;
	}

	$loop      = true;
	$skip      = false;
	$skipAll   = false;

	while($loop) {

		echo "\nMovie         : $showMaybe\n";
		echo "Year          : ";
		if ($yearMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$yearMaybe\n";
		}

		echo "\n";
		echo "Original Path : $f\n";
		echo "New Path      : ".getNewName($showMaybe, $yearMaybe, $idMaybe, $fileext)."\n";
		echo "Filesize      : ".floor(filesize($dirIn.$f)/1024/1024)."MB\n";

		if (floor(filesize($dirIn.$f)/1024/1024) < 100) {
			echo "Note          : LIKELY A SAMPLE FILE!!!!\n";
		}

		echo "\nActions:".
		     "\n\tm. Edit movie name".
		     //"\n\tt. Edit extended title".
		     "\n\ty. Edit the year".
		     "\n\tc. Clear imdbID".
		     "\n\ti. Lookup by imdbID".
		     "\n\tn. Lookup by general search".
		     "\n\tp. Move to nrop".
		     "\n\tk. Skip this movie".
		     "\n\tK. Skip the rest".
             "\n\tD. Skip this movie and delete file".
		     "\n\tg. Perform operation...go!"
		;
		echo "\n\nLookups:";
		for ($j = 0; $j < count($movieInfos); $j++) {
			echo "\n\t".($j+1).". ".$movieInfos[$j]->Title." [".$movieInfos[$j]->Year."] http://www.imdb.com/title/".$movieInfos[$j]->imdbID;
		}
		echo "\n\nWhat would you like to do? [g]: ";

		$input = trim(fgets(STDIN));
		if (empty($input)) {
			$input = 'g';
		}

		switch ($input) {

			case 'c':
				$idMaybe = null;
				continue;

			case 'm':
				echo "Enter new movie name: ";
				$showMaybe = trim(fgets(STDIN));
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

            case 'n':
            	echo "Enter your IMDB search string: ";
				$search = trim(fgets(STDIN));
            	$movieInfos = doLookup($search, null);

				if (count($movieInfos) > 0) {
					$yearMaybe = $movieInfos[0]->Year;
					$showMaybe = $movieInfos[0]->Title;
					$idMaybe   = $movieInfos[0]->imdbID;
				}

				break;
            case 'i':
            	echo "Enter your IMDB ID: ";
				$search = trim(fgets(STDIN));
            	$movieInfos = doLookupId($search, null);

				if (count($movieInfos) > 0) {
					$yearMaybe = $movieInfos[0]->Year;
					$showMaybe = $movieInfos[0]->Title;
					$idMaybe   = $movieInfos[0]->imdbID;
				}

				break;

			case 'z':
				echo "\nCurrent items in remove list: \"".implode('", "', getRemoveList())."\"\n\n";
				echo "Enter the item you would like to remove: ";
				$removeListItem = trim(fgets(STDIN));
				if (!empty($removeListItem)) {
					removeFromRemoveList($removeListItem);
					list(, , , , , , , , $titleMaybe) = performInitialFilenameParse($filename);
				}
				continue;

			case 'K':
				$skipAll = true;
			case 'g':
				$show  = $showMaybe;
				$year  = $yearMaybe;
				$loop  = false;

				break;

			default:

				$lookupInt = (int)$input;
				if (array_key_exists($lookupInt-1, $movieInfos)) {
					$yearMaybe = $movieInfos[$lookupInt-1]->Year;
					$showMaybe = $movieInfos[$lookupInt-1]->Title;
					$idMaybe   = $movieInfos[0]->imdbID;
					break;
				}

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

		if ($skip === true || $skipAll === true) {
			break;
		}

	}

	if ($skip !== true) {
		$path = $dirOut.getNewName($show, $year, $idMaybe, $fileext);

		if ($path == $dirIn.$f) {
			echo "Not Queued: The in and out filepaths are the same...\n";
		} else {
			echo "Queued: $path\n";
			
			$queue[] = [
				$dirIn.$f,
				$path
			];
		}
	} else {
		echo "Skipping!\n";
		$skip = false;
	}

	if ($skipAll === true) {
		break;
	}

	$i++;

}

if (count($queue) > 0) {
	handleQueue($queue);
	handleCleanup([$dirIn, $dirOut]);
	echo "\n";
} else {
	echo "\nNo items found...\n\n";
	handleCleanup([$dirIn, $dirOut]);
	echo "\n";
}

echo "Complete!\n";

function doLookup($title, $year = null)
{
	$apiUrl = 'http://www.omdbapi.com/?s='.urlencode($title);
	if ($year !== null) {
		$apiUrl .= '&y='.urlencode($year);
	}
	//echo $apiUrl;
	$json = file_get_contents($apiUrl);
	$info = json_decode($json);
	
	$items = @$info->Search;

	if (!count($items) > 0) {
		return [];
	}

	$movies = [];
	for ($i = 0; $i < count($items); $i++) {
		if ($items[$i]->Type != 'movie') {
			continue;
		}
		$movies[] = $items[$i];
	}

	return $movies;
}

function doLookupId($id)
{
	$apiUrl = 'http://www.omdbapi.com/?i='.urlencode($id);
	$json = file_get_contents($apiUrl);
	$info = json_decode($json);
	
	return [$info];
}

function getNewName($show, $year, $id, $ext)
{
	$r = '.'.DIRECTORY_SEPARATOR."$show";

	if ($year != null) {
		$r = $r." [$year]";
	}

	$r = $r.DIRECTORY_SEPARATOR;

	$r = $r."$show";

	if ($year != null) {
		$r = $r." [$year]";
	}

	if ($id !== null) {
		$r = $r." [$id]";
	}

	$r = $r.".$ext";

	return $r;
}
