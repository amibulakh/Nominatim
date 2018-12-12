<?php

require_once(CONST_BasePath . '/lib/init-website.php');
require_once(CONST_BasePath . '/lib/log.php');
require_once(CONST_BasePath . '/lib/PlaceLookup.php');
require_once(CONST_BasePath . '/lib/output.php');
ini_set('memory_limit', '200M');

$oParams = new Nominatim\ParameterParser();

// Format for output
$sOutputFormat = $oParams->getSet('format', array('xml', 'json', 'geojson'), 'xml');
set_exception_handler_by_format($sOutputFormat);

// Preferred language
$aLangPrefOrder = $oParams->getPreferredLanguages();

$oDB =& getDB();

$hLog = logStart($oDB, 'place', $_SERVER['QUERY_STRING'], $aLangPrefOrder);

$aSearchResults = array();
$aCleanedQueryParts = array();

$oPlaceLookup = new Nominatim\PlaceLookup($oDB);
$oPlaceLookup->loadParamArray($oParams);
$oPlaceLookup->setIncludeAddressDetails($oParams->getBool('addressdetails', true));

$aOsmId = $oParams->getString('osm_id', '');
$aOsmJMPolyIds = $oPlaceLookup->lookupJMPolyOSMIDs($aOsmId);

foreach ($aOsmJMPolyIds as $sItem) {
    // Skip empty sItem

    $iId = (int)$sItem['osm_id'];

    $aCleanedQueryParts[] = "W" . $iId;
    $oPlace = $oPlaceLookup->lookupOSMID("W", $iId);
    if ($oPlace) {
        // we want to use the search-* output templates, so we need to fill
        // $aSearchResults and slightly change the (reverse search) oPlace
        // key names
        $oResult = $oPlace;
        unset($oResult['aAddress']);
        if (isset($oPlace['aAddress'])) $oResult['address'] = $oPlace['aAddress'];
        unset($oResult['langaddress']);
        $oResult['name'] = $oPlace['langaddress'];
        $aSearchResults[] = $oResult;
    }
}


if (CONST_Debug) exit;

$sXmlRootTag = 'lookupresults';
$sQuery = join(',', $aCleanedQueryParts);
// we initialize these to avoid warnings in our logfile
$sViewBox = '';
$bShowPolygons = '';
$aExcludePlaceIDs = array();
$sMoreURL = '';

$sOutputTemplate = ($sOutputFormat == 'jsonv2') ? 'json' : $sOutputFormat;
include(CONST_BasePath . '/lib/template/search-' . $sOutputTemplate . '.php');
