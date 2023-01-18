<?php
require "vendor/autoload.php";

// https://hollabrunn.umweltverbaende.at/?gem_nr=31009&jahr=2020&kat=32&portal=verband&vb=hl

$district_code     = param('vb', 'hl');
$community_id      = param('gem_nr', '31009');
$cat               = param('kat', '32');
$portal            = param('portal', 'verband');
$filename           = param('filename', "cal.ics");
$debug             = param('debug', "false") == "true";

$now     = new DateTime();
$version = '20230118T000000Z'; // modify this when you make changes in the code!
$year    = $now->format("Y");

$serviceUrl = "https://hollabrunn.umweltverbaende.at/"
	. "?gem_nr=" . $community_id 
	. "&jahr="   . $year  
	. "&kat="    . $cat 
	. "&portal=" . $portal
	. "&vb="     . $district_code;
$htmlFileName = $year . "-" . $community_id . "_" . $cat . "_" . $portal . "_" . $district_code . '.html';
	
// $html = '<h2>Mittergrabern</h2><div class="tunterlegt">DO &nbsp; 02.07.2020 &nbsp; Altpapier</div>';
$html = file_get_contents($htmlFileName);

if ($html == false) {
	$html = file_get_contents($serviceUrl);
}

if ($html !== false) {
	$htmlFile = fopen($htmlFileName, "w");
	fwrite($htmlFile, $html);
	fclose($htmlFile);
}

// $html = str_replace("ü", "&uuml;", $html);

// $html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");

$options  = array();
$entries  = html5qp($html, "div.tunterlegt", $options);
$location = html5qp($html, "h2")->toArray()[1]->nodeValue;

$out = generateCalDavHeader();

foreach ($entries as $entry) {
	$innerHtml = $entry->innerHTML();

	$data      = preg_split("(\s)", $innerHtml);
	$date      = DateTime::createFromFormat("d.m.Y", $data[9]);
	$wasteType = fixWasteTYoe($data[11]);
	$wasteIcon = getWasteIcon($wasteType);

	$description = " Abholung $wasteType";
	$summary       = $wasteIcon . $description;

	$out .= generateCalendarEntry($date, $summary, $description, $location, $version);
}

$out .= 'END:VCALENDAR';

if (!$debug) {
	header('Content-type: text/calendar; charset=utf-8');
	header('Content-Disposition: inline; filename=' . $filename);
}

echo $out;

function generateCalDavHeader() {
	$date    = date("Y-m-d");

	$out = "BEGIN:VCALENDAR\r\n";
	$out .= "PRODID:-//Permanent Solutions Ltd//Weather Calendar//EN\r\n";
	$out .= "VERSION:2.0\r\n";
	$out .= "CALSCALE:GREGORIAN\r\n";
	$out .= "METHOD:PUBLISH\r\n";
	$out .= "URL:https://github.com/mojo2012/hollabrunn-abfall-kalender\r\n";
	$out .= "X-WR-CALNAME:Abfallverband\r\n";
	$out .= "X-WR-CALDESC:Display waste timeplan for Hollabrunn.\r\n";
	$out .= "X-LOTUS-CHARSET:UTF-8\r\n";

	return $out;
}

function generateCalendarEntry($date, $summary, $description, $location, $version) {
	$out = "BEGIN:VEVENT\r\n";
	$out .= "DTSTART;VALUE=DATE:" . $date->format('Ymd')      . "\r\n";
	$out .= "DTEND;VALUE=DATE:"   . $date->format('Ymd')      . "\r\n";
	$out .= "DTSTAMP:"            . $date->format('Ymd\THis\Z') . "\r\n";
	$out .= "UID:Waste-Feed-"     . $date->format('Y-m-d')      . "-$version\r\n";
	$out .= "CLASS:PUBLIC\r\n";
	$out .= "CREATED:$version\r\n";
	$out .= "LOCATION:" . $location . "\r\n"; //@https://www.ietf.org/rfc/rfc2445.txt
	$out .= "LAST-MODIFIED:$version\r\n";
	$out .= "SEQUENCE:0\r\n";
	$out .= "STATUS:CONFIRMED\r\n";
	$out .= "TRANSP:TRANSPARENT\r\n";
	$out .= "X-MICROSOFT-CDO-BUSYSTATUS:FREE\r\n";
	$out .= "X-MICROSOFT-CDO-INTENDEDSTATUS:FREE\r\n";
	$out .= "X-MICROSOFT-CDO-ALLDAYEVENT:TRUE\r\n";
	$out .= "DESCRIPTION:" . $description . "\r\n";
	$out .= "SUMMARY:" . $summary . "\r\n";
	$out .= "END:VEVENT\r\n";

	return $out;
}


function fixWasteTYoe($wasteType) {
	switch($wasteType) {
		case "Altpapier":
			return "Altpapier";
		case "Biotonne":
			return "Biotonne";
		case "Gelber":
			return "Gelber Sack";
		case "Restmll":
			return "Restmüll";
		default:
			return "Unbekannt";
	}
}


// unfortunatelly the waste types encoded in UTF-8, during parsing some special characters are lost
function getWasteIcon($wasteType) {
	switch($wasteType) {
		case "Altpapier":
			return "🔴";
		case "Biotonne":
			return "🟤";
		case "Gelber Sack":
			return "🟡";
		case "Restmüll":
			return "⚫️";
		default:
			return "❓";
	}
}

/**
 * @param string $name
 * @param string $default
 * @return string
 * @desc GET an URL parameter
 */
function param($name, $default = '')
{
	if (isset($_GET[$name]) &&  !empty($_GET[$name])) {
		$out = filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING);
	} else {
		$out = $default;
	}
	
	return $out;
}

?>
