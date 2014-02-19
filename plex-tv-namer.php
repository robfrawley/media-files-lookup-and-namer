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
$dirIn  	  = '/Users/rmf/Torrents/Queue/';
$dirOut 	  = '/Volumes/External-2/Videos/TV/';
$format 	  = '%SHOW%/Season %SEASONFOLDER%/%SHOW% - %SEASON%%EPISODE%%TITLE%.%EXT%';

require __DIR__.DIRECTORY_SEPARATOR.'plex-common.php';
require __DIR__.DIRECTORY_SEPARATOR.'plex-common-apikeys.php';

$files  = performScan($dirIn, $acceptedExts);
$count  = count($files);
$i      = 1;
$queue  = [];

showWelcomeMessage('TV');

foreach ($files as $f) {

	showItemHeader($i, $count);

	list($filename, $fileext) = 
		getFileInfo($f)
	;
	list($showMaybe, $seasonMaybe, $episodeMaybe, $titleMaybe) = 
		performInitialTVFilenameParse($filename)
	;
	list($showMaybe, $seasonMaybe, $episodeMaybe, $titleMaybe, $lookupSuccess) = 
		doTVEpisodeLookup($showMaybe, $seasonMaybe, $episodeMaybe, $titleMaybe)
	;

	$episodeEndMaybe = null;
	$loop      = true;
	$skip      = false;
	$dateBased = false;

	while($loop) {

		echo "\nShow          : $showMaybe\n";
		echo "Season        : ";
		if ($seasonMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$seasonMaybe\n";
		}
		echo "Episode       : ";
		if ($episodeMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$episodeMaybe\n";
		}
		echo "Episode End   : ";
		if ($episodeEndMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$episodeEndMaybe\n";
		}
		echo "Title         : ";
		if ($titleMaybe === null) {
			echo "[null]\n";
		} else {
			echo "$titleMaybe\n";
		}
		echo "Lookup        : ";
		if ($lookupSuccess === true) {
			echo "Success\n";
		} else {
			echo "Failure\n";
		}
		echo "\n";
		echo "Original Path : $f\n";
		echo "New Path      : ".getNewName($showMaybe, $seasonMaybe, $episodeMaybe, $episodeEndMaybe, $fileext, $titleMaybe, $dateBased)."\n";
		echo "Filesize      : ".floor(filesize($dirIn.$f)/1024/1024)."MB\n";
		
		echo "\nActions:".
		     "\n\ts. Edit season number".
		     "\n\te. Edit episode start number".
		     "\n\tp. Edit episode end number".
		     "\n\th. Edit show name".
		     "\n\tt. Edit episode title".
		     "\n\td. Re-parse as date-based episode".
		     "\n\tl. Re-parse as spelled out season/episode".
			 "\n\tc. Re-parse as special episode".
			 "\n\t3. Re-parse as 3 digit season/episode".
		     "\n\ty. Remove year from show title".
		     "\n\tx. Add item to the remove list".
		     "\n\ta. Add current title to the remove list".
		     "\n\tz. Remove item from the remove list".
		     "\n\tk. Skip this episode".
                     "\n\tD. Skip this episode and delete file".
		     "\n\tg. Perform operation...go!".
		     "\n\nWhat would you like to do? [g]: ";
		$input = trim(fgets(STDIN));
		if (empty($input)) {
			$input = 'g';
		}

		switch ($input) {
			case 's':
				echo "Enter new season number: ";
				$seasonMaybe = trim(fgets(STDIN));
				if (empty($seasonMaybe)) {
					$seasonMaybe = null;
				}
				continue;

			case 'e':
				echo "Enter new episode number: ";
				$episodeMaybe = trim(fgets(STDIN));
				if (empty($episodeMaybe)) {
					$episodeMaybe = null;
				}
				continue;

			case 'p':
				echo "Enter new episode end number: ";
				$episodeEndMaybe = trim(fgets(STDIN));
				continue;

			case 'h':
				echo "Enter new show name: ";
				$showMaybe = trim(fgets(STDIN));
				continue;

			case 't':
				echo "Enter new title: ";
				$titleMaybe = cleanupTitle(trim(fgets(STDIN)));
				continue;

			case 'k':
				$skip = true;
				break;

                        case 'D':
                                $skip = true;
                                unlink($dirIn.$f);
                                break;

			case 'c':
				$seasonMaybe = '00';
				$episodeMaybe = '00';
				$episodeEndMaybe = null;
				$titleMaybe = cleanupTitle(trim('Special '.$titleMaybe));
				continue;

			case 'y':
				$showMaybe = cleanupTitle(trim(preg_replace('#[0-9]{4}#', '', $showMaybe)));
				continue;

			case 'l':
				list($matches, $match, $offset, $offsetEnd, $episodeMaybe, $episodeEndMaybe, $seasonMaybe, $showMaybe, $titleMaybe) = 
					performInitialFilenameParse($filename, '#season ?([0-9]{1,2}) ?episode ?([0-9]{1,2})#i')
				;
				continue;

			case '3':
				list($matches, $match, $offset, $offsetEnd, $episodeMaybe, $episodeEndMaybe, $seasonMaybe, $showMaybe, $titleMaybe) = 
					performInitialFilenameParse($filename, '#\b([0-9]{1})([0-9]{2})\b#i')
				;
				continue;

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

			case 'd':
				$pattern = '#s?([0-9]{4})[.]{1}([0-9]{2})[.]{1}([0-9]{2})#i';
				$matches = [];
				preg_match($pattern, $filename, $matches, PREG_OFFSET_CAPTURE);

				$match           = $matches[0][0];
				$offset          = $matches[0][1];
				$offsetEnd       = $matches[0][1]+strlen($matches[0][0]);
				$episodeMaybe    = $matches[1][0].'-'.$matches[2][0].'-'.$matches[3][0];
				$episodeEndMaybe = null;
				$seasonMaybe     = null;
				$showMaybe       = cleanup(substr($filename, 0, $offset));
				$titleMaybe      = cleanupTitle(substr($filename, $offsetEnd));
				$dateBased       = true;
				continue;

			case 'g':
				$show    = $showMaybe;
				if ($dateBased === true) {
					$episode = $episodeMaybe;
				} else {
					$episode = 'E'.$episodeMaybe;
				}
				if ($dateBased === true) {
					$season = '';
				} else {
					$season = 'S'.$seasonMaybe;
				}
				$episode    = $episodeMaybe;
				$season     = $seasonMaybe;
				$title      = $titleMaybe;
				$episodeEnd = $episodeEndMaybe;
				$loop       = false;

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
		$path = $dirOut.getNewName($show, $season, $episode, $episodeEnd, $fileext, $title, $dateBased);

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
	handleCleanup([$dirIn, $dirOut]);
	echo "\n";
} else {
	echo "\nNo items found...\n\n";
	handleCleanup([$dirIn, $dirOut]);
	echo "\n";
}

echo "Complete!\n";

function getNewName($show, $season, $episode, $episodeEnd, $ext, $title, $dateBased)
{
	global $format;

	$r = str_replace('%SHOW%',    $show,                                   $format);
	if ($season !== null) {
		$r = str_replace('%SEASON%',  'S'.str_pad($season,  2, '0', STR_PAD_LEFT), $r);
	} else {
		$r = str_replace('%SEASON%', '', $r);
	}
	$r = str_replace('%SEASONFOLDER%', str_pad($season,  2, '0', STR_PAD_LEFT), $r);
	if ($episode === null) {
		$r = str_replace('%EPISODE%', '', $r);
	} elseif ($dateBased !== true) {
		if ($episodeEnd !== null) {
			$episode = 'E'.str_pad($episode, 2, '0', STR_PAD_LEFT).'-E'.str_pad($episodeEnd, 2, '0', STR_PAD_LEFT);
		} else {
			$episode = 'E'.str_pad($episode, 2, '0', STR_PAD_LEFT);
		}
		$r = str_replace('%EPISODE%', $episode, $r);
	} else {
		$r = str_replace('%EPISODE%', $episode, $r);
	}
	$r = str_replace('%EXT%',     $ext,                                    $r);
	if ($title !== null) {
		if (($season !== null && $episode !== null) || $dateBased === true) {
			$title = ' - '.$title;
		}
	} else {
		$title = '';
	}
	$r = str_replace('%TITLE%', $title, $r);

	return $r;
}