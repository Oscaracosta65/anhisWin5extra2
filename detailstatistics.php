<?php
/**
 * @version    CVS: 1.0.3
 * @package    Com_Lotterydb
 * @author     FULLSTACK DEV <admin@fullstackdev.us>
 * @copyright  2022 FULLSTACK DEV default as of 04 23 2025  0314 pm
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 **/

/** *********************************** MAIN PAGE LOTTERY LIST ********************************************** **/

/** Output main state lotteries page  h1wrapper  **/

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

/** GRAB THE CURRENT URL (safe string) **/
$c_Url = Uri::getInstance();
$currentUrl = $c_Url->toString();

/** Current state slug from path **/
$path = trim($c_Url->getPath(), '/');
$parts = $path ? explode('/', $path) : [];
$c_state = $parts ? end($parts) : '';




/** CONVERTING STATE NAME AND ABREVIATION FROM THE URL PATH **/
/**
 * Phase 2 ? Step 1: Whitelist state slug ? {name, abbr}
 * Security/maintainability: only recognized slugs are allowed to set state values.
 */
$stateMap = [
    // US states / territories / DC
    'arkansas'       => ['name' => 'Arkansas',       'abbr' => 'ar'],
    'arizona'        => ['name' => 'Arizona',        'abbr' => 'az'],
    'california'     => ['name' => 'California',     'abbr' => 'ca'],
    'colorado'       => ['name' => 'Colorado',       'abbr' => 'co'],
    'connecticut'    => ['name' => 'Connecticut',    'abbr' => 'ct'],
    'dc'             => ['name' => 'DC',             'abbr' => 'dc'],
    'delaware'       => ['name' => 'Delaware',       'abbr' => 'de'],
    'florida'        => ['name' => 'Florida',        'abbr' => 'fl'],
    'georgia'        => ['name' => 'Georgia',        'abbr' => 'ga'],
    'iowa'           => ['name' => 'Iowa',           'abbr' => 'ia'],
    'idaho'          => ['name' => 'Idaho',          'abbr' => 'id'],
    'illinois'       => ['name' => 'Illinois',       'abbr' => 'il'],
    'indiana'        => ['name' => 'Indiana',        'abbr' => 'in'],
    'kansas'         => ['name' => 'Kansas',         'abbr' => 'ks'],
    'kentucky'       => ['name' => 'Kentucky',       'abbr' => 'ky'],
    'louisiana'      => ['name' => 'Louisiana',      'abbr' => 'la'],
    'massachusetts'  => ['name' => 'Massachusetts',  'abbr' => 'ma'],
    'maryland'       => ['name' => 'Maryland',       'abbr' => 'md'],
    'maine'          => ['name' => 'Maine',          'abbr' => 'me'],
    'michigan'       => ['name' => 'Michigan',       'abbr' => 'mi'],
    'minnesota'      => ['name' => 'Minnesota',      'abbr' => 'mn'],
    'mississippi'    => ['name' => 'Mississippi',    'abbr' => 'ms'],
    'missouri'       => ['name' => 'Missouri',       'abbr' => 'mo'],
    'montana'        => ['name' => 'Montana',        'abbr' => 'mt'],
    'north-carolina' => ['name' => 'North Carolina', 'abbr' => 'nc'],
    'north-dakota'   => ['name' => 'North Dakota',   'abbr' => 'nd'],
    'nebraska'       => ['name' => 'Nebraska',       'abbr' => 'ne'],
    'new-hampshire'  => ['name' => 'New Hampshire',  'abbr' => 'nh'],
    'new-jersey'     => ['name' => 'New Jersey',     'abbr' => 'nj'],
    'new-mexico'     => ['name' => 'New Mexico',     'abbr' => 'nm'],
    'new-york'       => ['name' => 'New York',       'abbr' => 'ny'],
    'ohio'           => ['name' => 'Ohio',           'abbr' => 'oh'],
    'oklahoma'       => ['name' => 'Oklahoma',       'abbr' => 'ok'],
    'oregon'         => ['name' => 'Oregon',         'abbr' => 'or'],
    'pennsylvania'   => ['name' => 'Pennsylvania',   'abbr' => 'pa'],
    'puerto-rico'    => ['name' => 'Puerto Rico',    'abbr' => 'pr'],
    'rhode-island'   => ['name' => 'Rhode Island',   'abbr' => 'ri'],
    'south-carolina' => ['name' => 'South Carolina', 'abbr' => 'sc'],
    'south-dakota'   => ['name' => 'South Dakota',   'abbr' => 'sd'],
    'tennessee'      => ['name' => 'Tennessee',      'abbr' => 'tn'],
    'texas'          => ['name' => 'Texas',          'abbr' => 'tx'],
    'virginia'       => ['name' => 'Virginia',       'abbr' => 'va'],
    'vermont'        => ['name' => 'Vermont',        'abbr' => 'vt'],
    'washington'     => ['name' => 'Washington',     'abbr' => 'wa'],
    'wisconsin'      => ['name' => 'Wisconsin',      'abbr' => 'wi'],
    'west-virginia'  => ['name' => 'West Virginia',  'abbr' => 'wv'],
    'wyoming'        => ['name' => 'Wyoming',        'abbr' => 'wy'],

    // International (your file already supports these)
    'uk-national'    => ['name' => 'UK National',    'abbr' => 'uk'],
    'ireland'        => ['name' => 'Ireland',        'abbr' => 'ie'],
];

// Normalize slug to a strict format (defensive)
$slug = strtolower(trim((string) $c_state));
$slug = preg_replace('/[^a-z\-]/', '', $slug);

// Apply whitelist mapping
if (isset($stateMap[$slug])) {
    $stName  = $stateMap[$slug]['name'];
    $stAbrev = $stateMap[$slug]['abbr'];
}
/** SET DEFAULT STATE **/
if(empty($stName)){
    $stName = 'Florida';
    $stAbrev = 'fl';
}
$doc = Factory::getDocument();

// PHASE 1 ? STEP 7A: keep RAW state values for SQL/URLs; escape only on output
$stNameRaw  = (string) $stName;
$stAbrevRaw = (string) $stAbrev;

// SQL/URL-safe raw values used for DB lookups and query builder binds
// (Never use HTML-escaped strings for SQL comparisons.)
$stNameSql  = $stNameRaw;
$stAbrevSql = $stAbrevRaw;

// Normalized state label for DB comparisons only.
// Keeps feed values intact while allowing West-Virginia and West Virginia to match.
$stNameNormalized = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', (string) $stNameRaw))));

// Safe, display-ready values (use these only when echoing text)
$stNameEsc  = htmlspecialchars($stNameRaw, ENT_QUOTES, 'UTF-8');
$stAbrevEsc = htmlspecialchars($stAbrevRaw, ENT_QUOTES, 'UTF-8');

// Ensure URL is always a string before validating (prevents null/object edge cases)
$c_UrlStr = (string) ($c_Url ?? '');
$c_Url = filter_var($c_UrlStr, FILTER_VALIDATE_URL) ? $c_UrlStr : Uri::base();
$imageUrl = 'https://lottoexpert.net/images/lottoexpert_logo-stacked.jpg';

// Escaped copies for meta tag attribute contexts
$stNameMeta  = htmlspecialchars((string) $stName, ENT_QUOTES, 'UTF-8');
$stAbrevMeta = htmlspecialchars(strtoupper((string) $stAbrev), ENT_QUOTES, 'UTF-8');

// Twitter Meta Tags
$doc->addCustomTag('<meta name="twitter:title" content="'.$stNameMeta.' AI Lottery Prediction and Analysis - LottoExpert.net">');
$doc->addCustomTag('<meta name="twitter:description" content="All '.$stNameMeta.' AI lottery predictions, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators">');
$doc->addCustomTag('<meta name="twitter:image" content="'.$imageUrl.'">');

// Open Graph Meta Tags
$doc->addCustomTag('<meta property="og:site_name" content="LottoExpert.net">');
$doc->addCustomTag('<meta property="og:title" content="'.$stNameMeta.' AI lottery prediction, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators">');
$doc->addCustomTag('<meta property="og:description" content="'.$stNameMeta.' '.$stAbrevMeta.' AI lottery prediction, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators for '.$stName.' lottery games.">');
$doc->addCustomTag('<meta property="og:url" content="'.htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8').'">');
$doc->addCustomTag('<meta property="og:type" content="article">');
$doc->addCustomTag('<meta property="og:image" content="'.$imageUrl.'">');
$doc->addCustomTag('<meta property="og:image:width" content="312">');
$doc->addCustomTag('<meta property="og:image:height" content="96">');



/** DECLARE DB TABLE TO QUERY descriptiontext**/
/**
 * PHASE 1 ? STEP 5A: Safe DB table name
 * Use a strict 2-letter state code for DB identifiers (NOT the HTML-escaped version).
 */
$stAbrevDb = strtolower(preg_replace('/[^a-z]/i', '', (string) $stAbrev));
if ($stAbrevDb === '') { $stAbrevDb = 'fl'; } // safety fallback
$dbCol = '#__lotterydb_' . $stAbrevDb;


if (!function_exists('leRenderBallSpans')) {
    function leRenderBallSpans(array $values, $class = 'circles')
    {
        $out = array();

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value === '' || strtolower($value) === 'null') {
                continue;
            }

            $out[] = '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return implode('', $out);
    }
}

/**
 * Manual retired-game blocklist.
 * Add provider game IDs here when a lottery is discontinued but still appears in the feed.
 */
$retiredGameIds = [
    '132', // Cash4Life
    '134', // Lucky For Life
    '114', // 2 by 2 
    'CA3', // Daily Derby California 
    'GA1', // Jumbo Bucks 
    'MA2B', // Megabucks Doubler 
    'OR4', // Lucky Lines
];

$hiddenTileGameIds = [
    '101D','FLDF','FLBF','ILI','ILJ','CTCW','CTDW','INDF','INCF','MSCF','MSDF','NJDF','NJCF','NCCF','NCDF',
    'PADW','PACW','SCDF','SCCF','TNDW','TNBW','TNFW','TXBF','TXMF','TXLF','TXDF','VACF','VADF','CTAW','CTBW',
    'FLAF','FLCF','ILH','ILG','INBF','INAF','MSAF','MSBF','NCBF','NCAF','NJBF','NJAF','PABW','PAAW','SCBF',
    'SCAF','TNCW','TNAW','TNEW','TXCF','TXKF','TXJF','TXAF','VAAF','VABF','PAFW','PAEW','FLGF','FLHF','FLFF',
    'FLEF','PAGW','PAHW','NJG','VAEF','VAFF'
];

$hiddenTileGameNames = [
    'Evening 3 Double',
    'Pick 4 Day Wild'
];

if (!function_exists('leNormalizeStateName')) {
    function leNormalizeStateName($stateName)
    {
        $stateName = str_replace('-', ' ', (string) $stateName);
        $stateName = preg_replace('/\s+/', ' ', $stateName);

        return strtolower(trim((string) $stateName));
    }
}

if (!function_exists('leGetNormalizedStateSql')) {
    function leGetNormalizedStateSql($db, $columnName)
    {
        return sprintf("TRIM(LOWER(REPLACE(%s, '-', ' ')))", $db->quoteName($columnName));
    }
}

if (!function_exists('leIsRetiredGame')) {
    function leIsRetiredGame($gameId, array $retiredGameIds)
    {
        return in_array((string) $gameId, $retiredGameIds, true);
    }
}

if (!function_exists('leIsHiddenTileGame')) {
    function leIsHiddenTileGame($gameId, $gameName, array $hiddenTileGameIds, array $hiddenTileGameNames)
    {
        return in_array((string) $gameId, $hiddenTileGameIds, true) || in_array((string) $gameName, $hiddenTileGameNames, true);
    }
}

if (!function_exists('leBuildGameHref')) {
    function leBuildGameHref($stName, $stAbrev, $gameId, $gameName)
    {
        if ((string) $gameId === '101') {
            return '/powerball-winning-numbers-analysis-tools?stn=' . rawurlencode((string) $stName);
        }

        if ((string) $gameId === '113') {
            return '/megamillions-winning-numbers-analysis-tools?stn=' . rawurlencode((string) $stName);
        }

        return '/all-us-lotteries/results-analysis?st=' . rawurlencode((string) $stAbrev)
            . '&stn=' . rawurlencode((string) $stName)
            . '&gm=' . rawurlencode((string) $gameName)
            . '&gmCode=' . rawurlencode((string) $gameId);
    }
}

if (!function_exists('leBuildGameLogoPath')) {
    function leBuildGameLogoPath($stAbrev, $gameName)
    {
        return '/images/lottodb/us/' . (string) $stAbrev . '/' . str_replace(' ', '-', strtolower((string) $gameName)) . '.png';
    }
}


//set page and browser title
// --- SEO: page title + meta description ---
$document = Factory::getDocument();

$browserbar = $stName . ' Lottery Results, Winning Numbers, Jackpots and Analysis Tools | LottoExpert';
$document->setTitle($browserbar);

$m_description = 'Latest ' . $stName . ' lottery results, winning numbers, next draw dates, jackpot updates, archives, frequency insights, hot and cold number context, and advanced analysis tools for every available game in ' . $stName . '.';
$document->setDescription(strip_tags($m_description));
?>

<!-- Phase 5: Sticky quick-link (mobile) -->
<button type="button" id="leQuickIndexBtn" aria-label="Jump to game index">Games</button>

<script>
(function () {
  'use strict';

  // Filter the Game Index (no DB calls)
  var input = document.getElementById('leGameFilter');
  var list  = document.getElementById('leGameIndex');

  if (input && list) {
    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase().trim();
      var items = list.querySelectorAll('li');
      for (var i = 0; i < items.length; i++) {
        var t = (items[i].textContent || '').toLowerCase();
        items[i].style.display = (q === '' || t.indexOf(q) !== -1) ? '' : 'none';
      }
    });
  }

  // Sticky "Games" button (mobile)
  var btn = document.getElementById('leQuickIndexBtn');
  if (btn && list) {
    btn.addEventListener('click', function () {
      list.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (input) { input.focus({ preventScroll: true }); }
    });
  } else if (btn) {
    btn.style.display = 'none';
  }
})();
</script>

<style type="text/css">
#sp-main-body {
    padding: 0 0 100px;
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
}

.leftSidebarInner {
    padding: 15px;
}

.ftextwrapper,
.le-state-reference {
    display: block !important;
    width: 100% !important;
    max-width: 1180px !important;
    margin: 48px auto 0 !important;
    clear: both !important;
    float: none !important;
    grid-column: auto !important;
    grid-row: auto !important;
    position: relative;
    left: auto;
    right: auto;
}

.ftextwrapper > * {
    width: 100% !important;
    max-width: 100% !important;
}

.le-footer-reset,
.le-footer-reset > div,
.le-footer-reset > section,
.le-footer-reset > article {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    clear: both !important;
    column-count: 1 !important;
    column-gap: 0 !important;
}

.le-footer-reset [class*="col"],
.le-footer-reset [class*="span_"],
.le-footer-reset .column,
.le-footer-reset .columns {
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    display: block !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.le-state-reference {
    padding: 28px 26px;
}

span.pplay span.circlesPb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.le-skiplink {
    position: absolute;
    left: -9999px;
    top: auto;
    width: 1px;
    height: 1px;
    overflow: hidden;
}

.le-skiplink:focus {
    left: 16px;
    top: 16px;
    width: auto;
    height: auto;
    z-index: 9999;
    padding: 12px 14px;
    background: #0A1A33;
    color: #FFFFFF;
    border-radius: 12px;
    outline: 3px solid rgba(28, 102, 255, 0.35);
    outline-offset: 2px;
}

.le-state-shell {
    max-width: 1180px;
    margin: 0 auto;
    padding: 28px 16px 0;
}

.le-state-hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: 34px 28px;
    margin: 0 auto 22px;
    background: linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
    box-shadow: 0 24px 60px rgba(10, 26, 51, 0.24);
}

.le-state-hero:before {
    content: '';
    position: absolute;
    top: -120px;
    right: -120px;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}

.le-state-hero:after {
    content: '';
    position: absolute;
    bottom: -100px;
    left: -100px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
}

.le-state-hero__inner {
    position: relative;
    z-index: 2;
}

.le-state-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: #FFFFFF;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin: 0 0 18px;
}

.le-state-title {
    margin: 0 0 14px;
    color: #FFFFFF;
    font-size: 2.7rem;
    line-height: 1.06;
    font-weight: 900;
    letter-spacing: -0.03em;
    max-width: 860px;
}

.le-state-summary {
    margin: 0;
    max-width: 780px;
    color: rgba(255, 255, 255, 0.92);
    font-size: 1.05rem;
    line-height: 1.65;
}

.le-state-statbar {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 24px 0 0;
}

.le-state-stat {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 18px;
    padding: 16px 16px 14px;
}

.le-state-stat__label {
    display: block;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.le-state-stat__value {
    display: block;
    color: #FFFFFF;
    font-size: 1rem;
    line-height: 1.45;
    font-weight: 700;
}

.le-state-intel {
    display: grid;
    grid-template-columns: 1.45fr 0.95fr;
    gap: 18px;
    margin: 0 auto 22px;
}

.le-state-panel {
    background: #FFFFFF;
    border: 1px solid rgba(10, 26, 51, 0.08);
    border-radius: 24px;
    padding: 24px 22px;
    box-shadow: 0 16px 42px rgba(10, 26, 51, 0.08);
}

.le-state-panel--soft {
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
}

.le-state-panel__title {
    margin: 0 0 10px;
    color: #0A1A33;
    font-size: 1.26rem;
    font-weight: 900;
    line-height: 1.2;
}

.le-state-panel__text {
    margin: 0;
    color: #42506A;
    font-size: 0.98rem;
    line-height: 1.68;
}

.le-state-panel__list {
    list-style: none;
    padding: 0;
    margin: 14px 0 0;
}

.le-state-panel__list li {
    position: relative;
    padding-left: 18px;
    margin: 0 0 10px;
    color: #334155;
    line-height: 1.58;
}

.le-state-panel__list li:before {
    content: '';
    position: absolute;
    top: 10px;
    left: 0;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #1C66FF;
}

.state-hub-header {
    max-width: 1180px;
    margin: 0 auto 18px;
}

.state-hub-h1 {
    font-size: 2.1rem;
    line-height: 1.1;
    margin: 0 0 10px;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: #0A1A33;
}

.state-hub-intro {
    margin: 0;
    color: #42506A;
    font-size: 1rem;
    line-height: 1.65;
}

.skai-breadcrumb {
    max-width: 1180px;
    margin: 0 auto 18px;
    padding: 0 4px;
}

.skai-breadcrumb__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    color: #7F8DAA;
    font-weight: 700;
    font-size: 0.92rem;
}

.skai-breadcrumb__item {
    margin: 0;
}

.skai-breadcrumb__sep {
    opacity: 0.72;
    font-weight: 900;
    color: #7F8DAA;
}

.skai-breadcrumb__link {
    text-decoration: none;
    color: #1C66FF;
    padding: 7px 11px;
    border-radius: 999px;
    transition: background 0.18s ease, transform 0.18s ease;
}

.skai-breadcrumb__link:hover,
.skai-breadcrumb__link:focus-visible {
    background: rgba(28, 102, 255, 0.08);
    transform: translateY(-1px);
    outline: 2px solid rgba(28, 102, 255, 0.28);
    outline-offset: 2px;
}

.skai-breadcrumb__item--current {
    color: #0A1A33;
    font-weight: 900;
}

.skai-faq {
    max-width: 1180px;
    margin: 0 auto 24px;
    padding: 22px;
    background: #FFFFFF;
    border-radius: 24px;
    border: 1px solid rgba(10, 26, 51, 0.08);
    box-shadow: 0 16px 42px rgba(10, 26, 51, 0.08);
}

.skai-faq__head {
    margin-bottom: 12px;
}

.skai-faq__title {
    margin: 0 0 8px;
    font-size: 1.34rem;
    font-weight: 900;
    color: #0A1A33;
}

.skai-faq__sub {
    margin: 0;
    color: #56657E;
    font-size: 0.98rem;
    line-height: 1.6;
}

.skai-faq__items {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.skai-faq__item {
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
    border: 1px solid rgba(28, 102, 255, 0.14);
    border-radius: 18px;
    padding: 12px 14px;
}

.skai-faq__q {
    cursor: pointer;
    font-weight: 900;
    color: #0A1A33;
    list-style: none;
    outline: none;
    line-height: 1.45;
}

.skai-faq__q::-webkit-details-marker {
    display: none;
}

.skai-faq__a {
    margin-top: 10px;
    color: #42506A;
    line-height: 1.62;
    font-size: 0.95rem;
}

.skai-faq__item[open] {
    background: #FFFFFF;
    border-color: rgba(28, 102, 255, 0.26);
    box-shadow: 0 10px 24px rgba(10, 26, 51, 0.08);
}

.skai-faq__item:focus-within {
    outline: 2px solid rgba(28, 102, 255, 0.28);
    outline-offset: 2px;
}

.skai-gameindex {
    max-width: 1180px;
    margin: 18px auto 24px;
    padding: 22px;
    background: #FFFFFF;
    border-radius: 24px;
    border: 1px solid rgba(10, 26, 51, 0.08);
    box-shadow: 0 16px 42px rgba(10, 26, 51, 0.08);
}

.skai-gameindex__head {
    margin-bottom: 12px;
}

.skai-gameindex__title {
    margin: 0 0 8px;
    font-size: 1.34rem;
    font-weight: 900;
    color: #0A1A33;
    letter-spacing: -0.01em;
}

.skai-gameindex__sub {
    margin: 0;
    color: #56657E;
    font-size: 0.98rem;
    line-height: 1.6;
}

.skai-gameindex__filter {
    margin: 18px 0 0;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.skai-gameindex__filterlabel {
    font-weight: 800;
    font-size: 0.92rem;
    color: #0A1A33;
}

.skai-gameindex__filterinput {
    flex: 1;
    min-width: 220px;
    padding: 12px 14px;
    border: 1px solid rgba(10, 26, 51, 0.12);
    border-radius: 14px;
    background: #FFFFFF;
    color: #0A1A33;
    font-size: 0.95rem;
}

.skai-gameindex__filterinput:focus {
    outline: 3px solid rgba(28, 102, 255, 0.20);
    outline-offset: 2px;
    border-color: rgba(28, 102, 255, 0.34);
}

.skai-gameindex__list {
    list-style: none;
    padding: 0;
    margin: 16px 0 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.skai-gameindex__item {
    margin: 0;
}

.skai-gameindex__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 48px;
    padding: 10px 12px;
    text-decoration: none;
    font-weight: 800;
    font-size: 0.92rem;
    color: #0A1A33;
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
    border: 1px solid rgba(28, 102, 255, 0.14);
    border-radius: 999px;
    transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, background 0.18s ease-out, border-color 0.18s ease-out, color 0.18s ease-out;
}

.skai-gameindex__link:hover,
.skai-gameindex__link:focus-visible {
    background: linear-gradient(135deg, #1C66FF 0%, #7F8DAA 100%);
    color: #FFFFFF;
    border-color: rgba(28, 102, 255, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(10, 26, 51, 0.16);
    outline: 2px solid rgba(28, 102, 255, 0.28);
    outline-offset: 2px;
}

.lotResultWrap {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
    gap: 14px;
    max-width: 1120px;
    margin: 0 auto;
}

.lotResultWrap .resultWrap {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 22px 18px 18px;
    box-sizing: border-box;
    text-align: center;
    min-height: 0;
    height: auto !important;
    overflow: hidden;
    background: #FFFFFF;
    color: #0A1A33;
    border-radius: 24px;
    border: 1px solid rgba(10, 26, 51, 0.08);
    box-shadow: 0 18px 44px rgba(10, 26, 51, 0.10);
    cursor: pointer;
    transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, border-color 0.18s ease-out, background-color 0.18s ease-out;
}

.lotResultWrap .resultWrap:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 56px rgba(10, 26, 51, 0.16);
    border-color: rgba(28, 102, 255, 0.20);
    background-color: #FFFFFF;
}

.lotResultWrap .resultWrap h2 {
    margin: 0 0 10px;
    min-height: 2.5em;
    font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    font-size: 1.34rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.18;
    color: #0A1A33;
}

.lotResultWrap .resultWrap img {
    display: block;
    width: 180px;
    max-width: 100%;
    height: 60px;
    margin: 0 auto 14px;
    object-fit: contain;
}

.lotResultWrap .resultWrap .lotto-logo {
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
    border-radius: 12px;
}

.lotResultWrap .lstResult {
    font-size: 0.92rem;
    line-height: 1.6;
    color: #42506A;
    margin: 6px 0;
}

.lotResultWrap .lstResult + .lstResult {
    margin-top: 4px;
}

.lotResultWrap span.pplay {
    display: block;
    text-align: center;
    margin-top: 18px;
    margin-bottom: 12px;
    line-height: 1.35;
    font-size: 0.92rem;
    font-weight: 600;
    color: #334155;
}

.lotResultWrap span.pplay .circlesFb,
.lotResultWrap span.pplay .circlesPb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
}

.lotResultWrap .nDraw {
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7F8DAA;
    margin: 12px 0 4px;
    font-weight: 800;
}

.lotResultWrap .lstResult + .nDraw {
    margin-top: 22px;
}

.lotResultWrap .resultWrap p.lstResult {
    height: auto !important;
    margin-bottom: 6px;
    width: 100%;
}

.lotResultWrap .resultWrap p.lstResult br + .circles,
.lotResultWrap .resultWrap p.lstResult br + .circlesPb,
.lotResultWrap .resultWrap p.lstResult br + .circlesFb {
    margin-top: 0;
}

.lotResultWrap .resultWrap p.nDraw {
    margin-top: 18px !important;
    margin-bottom: 4px;
}

.lotResultWrap .nDrawDate {
    font-size: 1.02rem;
    font-weight: 800;
    color: #0A1A33;
    margin: 0 0 4px;
}

.lotResultWrap .nJackpot {
    font-size: 1.54rem;
    font-weight: 900;
    color: #1C66FF;
    line-height: 1.1;
    margin: 2px 0 4px;
}

.lotResultWrap .circles,
.lotResultWrap .circlesPb,
.lotResultWrap .circlesFb {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin: 0 3px 8px;
    border-radius: 50%;
    vertical-align: middle;
}

.lotResultWrap .circles--compact {
    width: 26px;
    height: 26px;
    margin: 0 1px 6px;
    font-size: 0.74rem;
}

.lotResultWrap .lotto-actions {
    position: static !important;
    left: auto !important;
    right: auto !important;
    bottom: auto !important;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 14px !important;
    padding-top: 0 !important;
    clear: both;
    z-index: 1;
}

.lotResultWrap .resultWrap > .lotto-actions:last-child {
    margin-top: 16px !important;
}

.lotResultWrap .rnaBtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
    padding: 11px 18px;
    min-width: 200px;
    min-height: 46px;
    box-sizing: border-box;
    font-size: 0.88rem;
    font-weight: 800;
    line-height: 1.35;
    text-align: center;
    text-decoration: none;
    color: #FFFFFF;
    background: linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
    border-radius: 999px;
    border: none;
    box-shadow: 0 10px 22px rgba(10, 26, 51, 0.18);
    cursor: pointer;
    transition: background 0.18s ease-out, box-shadow 0.18s ease-out, transform 0.18s ease-out, opacity 0.18s ease-out;
}

.lotResultWrap .rnaBtn:hover,
.lotResultWrap .rnaBtn:focus-visible {
    background: linear-gradient(135deg, #1C66FF 0%, #7F8DAA 100%);
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(10, 26, 51, 0.22);
    opacity: 0.99;
    outline: 2px solid rgba(28, 102, 255, 0.26);
    outline-offset: 2px;
}

.lotResultWrap .rnaBtn.pbHistoryBtn {
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    margin: 0 auto !important;
    max-width: 100%;
    width: auto;
    min-width: 180px;
    transform: none !important;
    clear: both;
    z-index: 1;
}

.lotResultWrap .rnaBtn.pbHistoryBtn:hover,
.lotResultWrap .rnaBtn.pbHistoryBtn:focus-visible {
    transform: translateY(-1px);
}

.le-state-reference {
    max-width: 1180px;
    margin: 32px auto 0;
    padding: 24px 22px;
    background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
    border-radius: 24px;
    border: 1px solid rgba(10, 26, 51, 0.08);
    box-shadow: 0 16px 42px rgba(10, 26, 51, 0.06);
}

.le-state-reference__title {
    margin: 0 0 10px;
    color: #0A1A33;
    font-size: 1.3rem;
    font-weight: 900;
}

.le-state-reference__text {
    margin: 0;
    color: #42506A;
    line-height: 1.68;
    font-size: 0.98rem;
}

.le-anchor-nav,
.le-context-grid,
.le-faq-intent,
.le-ad-zone {
    max-width: 1180px;
    margin: 0 auto 22px;
}

.le-anchor-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.le-anchor-nav__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 10px 14px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 800;
    font-size: 0.92rem;
    color: #0A1A33;
    background: #FFFFFF;
    border: 1px solid rgba(10, 26, 51, 0.10);
    box-shadow: 0 10px 24px rgba(10, 26, 51, 0.06);
}

.le-anchor-nav__link:hover,
.le-anchor-nav__link:focus-visible {
    color: #FFFFFF;
    background: linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
    outline: 2px solid rgba(28, 102, 255, 0.28);
    outline-offset: 2px;
}

.le-context-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.le-context-card {
    background: #FFFFFF;
    border: 1px solid rgba(10, 26, 51, 0.08);
    border-radius: 22px;
    padding: 22px 20px;
    box-shadow: 0 14px 34px rgba(10, 26, 51, 0.08);
}

.le-context-card__title {
    margin: 0 0 10px;
    color: #0A1A33;
    font-size: 1.08rem;
    font-weight: 900;
}

.le-context-card__text {
    margin: 0;
    color: #42506A;
    line-height: 1.66;
    font-size: 0.96rem;
}

.le-ad-zone {
    border: 1px dashed rgba(10, 26, 51, 0.16);
    border-radius: 20px;
    padding: 16px 18px;
    background: linear-gradient(180deg, rgba(239, 239, 245, 0.85) 0%, rgba(255, 255, 255, 0.95) 100%);
}

.le-ad-zone--premium {
    padding: 18px 20px;
}

.le-ad-zone__label {
    display: block;
    margin: 0 0 6px;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #62728E;
}

.le-ad-zone__text {
    margin: 0;
    color: #42506A;
    line-height: 1.6;
    font-size: 0.94rem;
}

.le-intelligence-shell {
    max-width: 1180px;
    margin: 0 auto 24px;
    padding: 24px 22px;
    background: #FFFFFF;
    border-radius: 24px;
    border: 1px solid rgba(10, 26, 51, 0.08);
    box-shadow: 0 16px 42px rgba(10, 26, 51, 0.08);
}

.le-intelligence-shell__title {
    margin: 0 0 10px;
    font-size: 1.38rem;
    line-height: 1.2;
    font-weight: 900;
    color: #0A1A33;
}

.le-intelligence-shell__text {
    margin: 0;
    color: #42506A;
    line-height: 1.7;
    font-size: 0.98rem;
}

.le-intelligence-shell__list {
    margin: 14px 0 0;
    padding-left: 18px;
    color: #334155;
}

.le-intelligence-shell__list li {
    margin: 0 0 8px;
    line-height: 1.58;
}

.le-results-intro {
    max-width: 1120px;
    margin: 0 auto 18px;
    color: #42506A;
    line-height: 1.66;
    font-size: 0.98rem;
}

.le-visually-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

#leQuickIndexBtn {
    position: fixed;
    right: 14px;
    bottom: 14px;
    z-index: 9998;
    border: none;
    border-radius: 999px;
    padding: 12px 14px;
    background: #0A1A33;
    color: #FFFFFF;
    font-weight: 800;
    box-shadow: 0 16px 30px rgba(10, 26, 51, 0.24);
    cursor: pointer;
}

#leQuickIndexBtn:focus {
    outline: 3px solid rgba(28, 102, 255, 0.26);
    outline-offset: 2px;
}

@media only screen and (max-width: 1024px) {
    .le-state-title {
        font-size: 2.2rem;
    }

    .le-state-intel,
    .le-context-grid {
        grid-template-columns: 1fr;
    }
}

@media only screen and (max-width: 1024px) {
    .lotResultWrap {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media only screen and (max-width: 768px) {
    #sp-main-body {
        padding-bottom: 88px;
    }

    .le-state-shell {
        padding: 18px 12px 0;
    }

    .le-state-hero {
        border-radius: 22px;
        padding: 24px 18px;
    }

    .le-state-title {
        font-size: 1.9rem;
        line-height: 1.1;
    }

    .le-state-summary {
        font-size: 0.98rem;
    }

    .skai-gameindex,
    .skai-faq,
    .le-state-panel,
    .le-state-reference {
        border-radius: 20px;
        padding: 18px 16px;
    }

    .skai-gameindex__list,
    .skai-faq__items {
        grid-template-columns: 1fr;
    }

    .le-context-grid {
        grid-template-columns: 1fr;
    }

    .le-anchor-nav {
        display: grid;
        grid-template-columns: 1fr;
    }

    .lotResultWrap {
        gap: 16px;
        row-gap: 20px;
    }

    .lotResultWrap .resultWrap {
        width: 100% !important;
        margin: 0;
        padding: 20px 14px 22px;
        border-radius: 20px;
    }

    img.lottoMan {
        max-width: 32px;
    }

    h1.lotteryHeading {
        color: #fff;
        font-size: 18px;
    }

    .border {
        height: 69px;
    }
}

@media only screen and (min-width: 960px) {
    #leQuickIndexBtn {
        display: none;
    }
}
</style>

<?php

/** DISPLAY TOP BUTTONS **/
echo JHtml::_('content.prepare', '{loadposition topLEHeader}');

/** INJECT DESCRIPTION TEXT (query builder + prepared output) **/
if (!empty($stAbrevSql)) {
    $qState = strtoupper($stAbrevSql);

    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select($db->quoteName('descriptiontext'))
        ->from($db->quoteName('#__lottostates_words'))
        ->where($db->quoteName('statename') . ' = :statename')
        ->where($db->quoteName('state') . ' = 1')
        ->bind(':statename', $qState);

    $db->setQuery($query);
    $dtext = (string) $db->loadResult();

    if ($dtext !== '') {
        
    }
}

/** START THE COLUMN SYSTEM **/
echo '<div class="le-state-shell">';
echo '<div class="section group">';

/** LEFT SIDEBAR COLUMN ? fully commented out for now to avoid mismatched tags **/
// echo '<div class="col span_1_of_4">';
// echo '<div class="leftSidebarInner">';
// echo JHtml::_('content.prepare', '{loadposition usstates}');
// echo JHtml::_('content.prepare', '{loadposition STATELOTTERYLIST}');
// echo '</div>';  // .leftSidebarInner
// echo '</div>';  // .col span_1_of_4

/** MAIN RIGHT COLUMN **/
echo '<div class="col span_4_of_4">';

/** Premium SKAI hero + page framing **/
echo '<a class="le-skiplink" href="#leGameIndex">Skip to game index</a>';

echo '<section class="le-state-hero" aria-labelledby="leStateTitle">';
echo '<div class="le-state-hero__inner">';
echo '<div class="le-state-eyebrow">State Lottery Reference</div>';
echo '<h1 id="leStateTitle" class="le-state-title">'.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' Lottery Results &amp; Analysis Tools</h1>';
echo '<p class="le-state-summary">A clear state hub for '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery games. Review the latest winning numbers, next draw dates, posted jackpots, and direct access to result archives, frequency views, hot and cold number context, and deeper analysis tools.</p>';
echo '<div class="le-state-statbar">';
echo '<div class="le-state-stat"><span class="le-state-stat__label">What this page shows</span><span class="le-state-stat__value">Current results, next draws, jackpots, and direct links to each game&rsquo;s analysis page.</span></div>';
echo '<div class="le-state-stat"><span class="le-state-stat__label">Why it matters</span><span class="le-state-stat__value">It brings every major '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' game into one organized state hub.</span></div>';
echo '<div class="le-state-stat"><span class="le-state-stat__label">How to use it</span><span class="le-state-stat__value">Start with a game card, then open its analysis tools for history, frequency, recency, and number behavior.</span></div>';
echo '</div>';
echo '</div>';
echo '</section>';

echo '<nav class="le-anchor-nav" aria-label="Quick page navigation">';
echo '<a class="le-anchor-nav__link" href="#leGameIndex">Browse '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery games</a>';
echo '<a class="le-anchor-nav__link" href="#leResultsCards">Review latest winning numbers and jackpots</a>';
echo '<a class="le-anchor-nav__link" href="#leStateFAQ">Read '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery FAQ</a>';
echo '<a class="le-anchor-nav__link" href="#leInterpretationGuide">Learn how to interpret results and analysis</a>';
echo '</nav>';

echo '<div class="le-ad-zone le-ad-zone--premium" aria-label="Page support zone">';
echo '<span class="le-ad-zone__label">Planning zone</span>';
echo '<p class="le-ad-zone__text">This page is designed to support both quick result checks and deeper research. That pause between sections also creates a clean placement zone for non-intrusive monetization without interrupting the results workflow.</p>';
echo '</div>';

echo '<section class="le-state-intel" aria-label="Page guidance">';
echo '<article class="le-state-panel">';
echo '<h2 class="le-state-panel__title">'.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery results, draw history insights, and analysis access</h2>';
echo '<p class="le-state-panel__text">This page is designed to be easy to scan and easy to interpret. Each game card shows the most recent available result, the next draw date, and a jackpot when published. The linked analysis pages go deeper with result archives, number frequency views, hot and cold numbers, skip and hit context, and broader historical reference tools.</p>';
echo '<ul class="le-state-panel__list">';
echo '<li><strong>Results analysis</strong> shows the latest published winning numbers for each game.</li>';
echo '<li><strong>Frequency</strong> helps explain how often a number has appeared over a selected history window.</li>';
echo '<li><strong>Hot and cold numbers</strong> are historical descriptions, not guarantees of future outcomes.</li>';
echo '<li><strong>Draw history insights</strong> help users compare recency, repetition, and longer-range number behavior.</li>';
echo '</ul>';
echo '</article>';
echo '<aside class="le-state-panel le-state-panel--soft">';
echo '<h2 class="le-state-panel__title">How to read the page</h2>';
echo '<p class="le-state-panel__text">Use this state hub as the starting point. Choose a lottery to review the latest result first, then move into the deeper analysis page when you want more historical context. This keeps the state page clean while still giving direct access to advanced tools.</p>';
echo '</aside>';
echo '</section>';

echo '<header class="state-hub-header">';
echo '<h2 class="state-hub-h1">'.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery games</h2>';
echo '<p class="state-hub-intro">Browse each available game below. Every card is structured to show the latest result, the next scheduled draw, the currently posted jackpot when available, and a direct path into deeper number analysis.</p>';
echo '</header>';

echo '<section class="le-context-grid" aria-label="Results intelligence overview">';
echo '<article class="le-context-card">';
echo '<h2 class="le-context-card__title">What this state page shows</h2>';
echo '<p class="le-context-card__text">This page works as a live reference layer for '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery results. It brings together winning numbers, draw recency, jackpot context, and entry points into LottoExpert analysis tools from a single state hub.</p>';
echo '</article>';
echo '<article class="le-context-card">';
echo '<h2 class="le-context-card__title">Why the analysis links matter</h2>';
echo '<p class="le-context-card__text">The tiles are the starting point, not the whole analysis. Each linked game page can extend the view into archives, frequency trends, hot and cold number context, and deeper SKAI or AI-oriented research where available.</p>';
echo '</article>';
echo '<article class="le-context-card">';
echo '<h2 class="le-context-card__title">How to interpret the information</h2>';
echo '<p class="le-context-card__text">Treat the results as a reference and a navigation layer. A recent result tells you what happened. Historical tools help you study patterns, intervals, and recency. They do not guarantee future outcomes, but they do improve decision context.</p>';
echo '</article>';
echo '</section>';

if (!empty($dtext)) {
    echo JHtml::_('content.prepare', $dtext);
}

/* =========================================================
   PHASE 1 ? STEP 2: Breadcrumbs (Visible + JSON-LD)
   Production-safe: additive only, no routing changes
   ========================================================= */

// --- Build breadcrumb URLs ---
$homeUrl  = '/';
$hubUrl   = '/all-us-lotteries';
$stateUrl = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');

// --- Visible breadcrumb ---
echo '<nav class="skai-breadcrumb" aria-label="Breadcrumb">';
echo '<ol class="skai-breadcrumb__list">';
echo '<li class="skai-breadcrumb__item"><a class="skai-breadcrumb__link" href="'.$homeUrl.'">Home</a></li>';
echo '<li class="skai-breadcrumb__sep" aria-hidden="true">&rsaquo;</li>';
echo '<li class="skai-breadcrumb__item"><a class="skai-breadcrumb__link" href="'.$hubUrl.'">All US Lotteries</a></li>';
echo '<li class="skai-breadcrumb__sep" aria-hidden="true">&rsaquo;</li>';
echo '<li class="skai-breadcrumb__item skai-breadcrumb__item--current" aria-current="page">'
    . htmlspecialchars($stName, ENT_QUOTES, 'UTF-8') . ' Results</li>';
echo '</ol>';
echo '</nav>';

// --- JSON-LD BreadcrumbList ---
$breadcrumbJson = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Home',
            'item'     => rtrim(Uri::base(), '/') . '/'
        ],
        [
            '@type'    => 'ListItem',
            'position' => 2,
            'name'     => 'All US Lotteries',
            'item'     => rtrim(Uri::base(), '/') . $hubUrl
        ],
        [
            '@type'    => 'ListItem',
            'position' => 3,
            'name'     => $stName . ' Results',
            'item'     => $stateUrl
        ],
    ],
];

Factory::getDocument()->addCustomTag(
    '<script type="application/ld+json">'
    . json_encode($breadcrumbJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . '</script>'
);



/** GET LIST OF AVAILABLE LOTTERIES FOR THIS STATE **/
$db = Factory::getDbo();
$sqlCheck = "SELECT DISTINCT `game_id` FROM `$dbCol`";
$db->setQuery($sqlCheck);
$db->execute();

$resultList = $db->loadObjectList();

if (!empty($resultList)) {

    // ============================
    // Phase 3: Performance batching + caching (safe, no HTML changes)
    // ============================
    $latestByGameId = [];

    try {
        // Joomla 5+ cache controller (callback)
        $cacheFactory = Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class);
        $cache        = $cacheFactory->createCacheController('callback', ['defaultgroup' => 'lottoexpert_statehub']);
        $cache->setLifeTime(1800); // 30 min

        $cacheId = 'latestRows_' . strtolower($stAbrevSql);

        $latestRows = $cache->get($cacheId, function () use ($stNameSql, $stNameNormalized, $dbCol) {
            $db = Factory::getDbo();

            // Latest draw per game_id for this state (single query)
            $sub = $db->getQuery(true)
                ->select([
                    $db->quoteName('game_id'),
                    'MAX(' . $db->quoteName('draw_date') . ') AS ' . $db->quoteName('max_draw')
                ])
                ->from($db->quoteName($dbCol))
                ->where(
                    leGetNormalizedStateSql($db, 'stateprov_name') . ' = ' . $db->quote($stNameNormalized)
                )
                ->group($db->quoteName('game_id'));

            $query = $db->getQuery(true)
                ->select('t.*')
                ->from($db->quoteName($dbCol, 't'))
                ->join('INNER', '(' . $sub . ') AS m ON m.game_id = t.game_id AND m.max_draw = t.draw_date')
                ->where(
                leGetNormalizedStateSql($db, 't.stateprov_name') . ' = :state2'
            )
                ->bind(':state2', $stNameNormalized);

            $db->setQuery($query);

            return (array) $db->loadObjectList();
        });

    } catch (\Throwable $e) {
        $latestRows = null;
    }

    if (!is_array($latestRows) || empty($latestRows)) {
        // Fallback (no cache): run the same batched query directly
        $db = Factory::getDbo();

        $sub = $db->getQuery(true)
            ->select([
                $db->quoteName('game_id'),
                'MAX(' . $db->quoteName('draw_date') . ') AS ' . $db->quoteName('max_draw')
            ])
            ->from($db->quoteName($dbCol))
            ->where(
                    leGetNormalizedStateSql($db, 'stateprov_name') . ' = ' . $db->quote($stNameNormalized)
                )
            ->group($db->quoteName('game_id'));

        $query = $db->getQuery(true)
            ->select('t.*')
            ->from($db->quoteName($dbCol, 't'))
            ->join('INNER', '(' . $sub . ') AS m ON m.game_id = t.game_id AND m.max_draw = t.draw_date')
            ->where(
            'TRIM(LOWER(REPLACE(' . $db->quoteName('t.stateprov_name') . ", '-', ' '))) = :state2"
        )
            ->bind(':state2', $stNameNormalized);

        $db->setQuery($query);
        $latestRows = (array) $db->loadObjectList();
    }

    foreach ($latestRows as $row) {
        if (!empty($row->game_id)) {
            $latestByGameId[(string) $row->game_id] = $row;
        }
    }


    /**
     * SKAI / LottoExpert: Crawl-friendly game index (above cards)
     * Production-safe: single query, additive HTML only.
     */
    $qStateName = $db->quote($stNameNormalized);

$sqlGames = "SELECT DISTINCT `game_id`, `game_name`
             FROM `$dbCol`
             WHERE " . leGetNormalizedStateSql($db, 'stateprov_name') . " = $qStateName
               AND `game_name` <> ''
             ORDER BY
               CASE
                 WHEN `game_id` = '101' THEN 0
                 WHEN `game_id` = '113' THEN 1
                 ELSE 2
               END,
               `game_name` ASC";
$db->setQuery($sqlGames);
$gameList = (array) $db->loadObjectList();
$gameCount = count($gameList);

$webPageJson = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $stName . ' Lottery Results, Winning Numbers and Analysis Tools',
    'description' => strip_tags($m_description),
    'url' => $currentUrl,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => 'LottoExpert.net',
        'url' => rtrim(Uri::base(), '/') . '/'
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => $stName . ' lottery results and number analysis'
    ]
];

$datasetJson = [
    '@context' => 'https://schema.org',
    '@type' => 'Dataset',
    'name' => $stName . ' Lottery Results and Analysis Index',
    'description' => 'State index for ' . $stName . ' lottery games including winning numbers, next draw dates, jackpot references, and access to advanced number analysis.',
    'url' => $currentUrl,
    'keywords' => [$stName . ' lottery', $stName . ' winning numbers', $stName . ' lottery results', $stName . ' jackpot analysis'],
    'creator' => [
        '@type' => 'Organization',
        'name' => 'LottoExpert.net'
    ]
];

Factory::getDocument()->addCustomTag(
    '<script type="application/ld+json">'
    . json_encode($webPageJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . '</script>'
);

Factory::getDocument()->addCustomTag(
    '<script type="application/ld+json">'
    . json_encode($datasetJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . '</script>'
);

    if (!empty($gameList)) {
        echo '<section class="le-intelligence-shell" id="leInterpretationGuide" aria-label="How to use the state results page">';
        echo '<h2 class="le-intelligence-shell__title">How to use this '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' results and analysis page</h2>';
        echo '<p class="le-intelligence-shell__text">This state hub is built for two kinds of users: people who want a fast result check, and people who want to study number behavior more carefully. Start with the game index if you already know the lottery you want. Use the result cards if you want the latest draw snapshot first. Then move into each game&rsquo;s advanced analysis page when you want archives, frequency views, or historical context.</p>';
        echo '<ul class="le-intelligence-shell__list">';
        echo '<li>This page currently indexes <strong>' . number_format((int) $gameCount) . '</strong> '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery games or game variants found in the state feed.</li>';
        echo '<li>Winning numbers answer what happened in the latest stored draw.</li>';
        echo '<li>Next draw and jackpot fields answer what is currently scheduled or posted, when that information is available.</li>';
        echo '<li>Advanced analysis links answer broader questions such as recency, repetition, and long-term number activity.</li>';
        echo '</ul>';
        echo '</section>';

        echo '<nav class="skai-gameindex" aria-label="Games in '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').'">';
        echo '<div class="skai-gameindex__head">';
        echo '<h2 class="skai-gameindex__title">Games in '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').'</h2>';
        echo '<p class="skai-gameindex__sub">Quick links to each game&rsquo;s results analysis page, including archives, frequency context, and deeper historical tools.</p>';
        echo '</div>';

        // Phase 5: client-side filter (no DB calls)
        echo '<div class="skai-gameindex__filter">';
        echo '  <label class="skai-gameindex__filterlabel" for="leGameFilter">Filter games</label>';
        echo '  <input id="leGameFilter" class="skai-gameindex__filterinput" type="search" inputmode="search" autocomplete="off" placeholder="Type a game name (e.g., Powerball)">';
        echo '</div>';

        echo '<ul id="leGameIndex" class="skai-gameindex__list">';

foreach ($gameList as $g) {
    $gameId2   = (string) $g->game_id;
    $gameName2 = (string) $g->game_name;

    if (leIsRetiredGame($gameId2, $retiredGameIds) || $gameName2 === '' || leIsHiddenTileGame($gameId2, $gameName2, $hiddenTileGameIds, $hiddenTileGameNames)) {
        continue;
    }

    $href = leBuildGameHref($stName, $stAbrev, $gameId2, $gameName2);
    $label = ($gameId2 === '101') ? 'Powerball' : (($gameId2 === '113') ? 'Mega Millions' : $gameName2);
    $anchorText = $stName . ' ' . $label . ' Results';

            echo '<li class="skai-gameindex__item">';
            echo '<a class="skai-gameindex__link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
               . htmlspecialchars($anchorText, ENT_QUOTES, 'UTF-8')
               . '</a>';
            echo '</li>';
        }

        echo '</ul>';
echo '</nav>';

/* =========================================================
   PHASE 1 ? STEP 3: Quick FAQ (Visible + JSON-LD FAQPage)
   Production-safe: additive only
   ========================================================= */

$faqStateName = htmlspecialchars($stName, ENT_QUOTES, 'UTF-8');
$faqStateAbbr = htmlspecialchars(strtoupper($stAbrev), ENT_QUOTES, 'UTF-8');

// Visible FAQ block (above tiles)
echo '<div class="le-ad-zone le-ad-zone--high" aria-label="Research support zone"><span class="le-ad-zone__label">Research break</span><p class="le-ad-zone__text">Pages that answer result, jackpot, odds, payout, and strategy-adjacent questions tend to attract longer sessions. This is a natural place for a non-intrusive monetization zone because the user has already engaged with the results index.</p></div>';
echo '<section class="skai-faq" id="leStateFAQ" aria-label="Quick FAQ">';
echo '<div class="skai-faq__head">';
echo '<h2 class="skai-faq__title">'.$faqStateName.' Lottery FAQ</h2>';
echo '<p class="skai-faq__sub">Clear answers about what the page shows, why it matters, and how to use the deeper analysis tools.</p>';
echo '</div>';

echo '<div class="skai-faq__items">';

// Q1
echo '<details class="skai-faq__item">';
echo '<summary class="skai-faq__q">Where can I find the latest '.$faqStateName.' lottery results?</summary>';
echo '<div class="skai-faq__a"><p>Use the game cards below to view the most recent winning numbers, the next draw date, and any posted jackpot. Select <strong>View Analysis Tools</strong> for a game-by-game results archive and deeper historical analysis.</p></div>';
echo '</details>';

// Q2
echo '<details class="skai-faq__item">';
echo '<summary class="skai-faq__q">Are the jackpots and next draw dates updated automatically?</summary>';
echo '<div class="skai-faq__a"><p>Yes, each game tile shows the latest stored draw results, plus the next draw date and jackpot when available. If a lottery does not publish a jackpot for a game, the jackpot field may be blank.</p></div>';
echo '</details>';

// Q3
echo '<details class="skai-faq__item">';
echo '<summary class="skai-faq__q">What do the analysis tools include?</summary>';
echo '<div class="skai-faq__a"><p>Tools typically include results archives, number frequency insights, skip/hit patterns, heatmaps, and wheel generators. Availability can vary by game.</p></div>';
echo '</details>';

// Q4
echo '<details class="skai-faq__item">';
echo '<summary class="skai-faq__q">Why do some games show an extra ball (Power Play, Megaplier, Fireball, Wild Ball)?</summary>';
echo '<div class="skai-faq__a"><p>Some lotteries add a multiplier or bonus feature. When that data is provided, LottoExpert displays it beneath the winning numbers for quick reference.</p></div>';
echo '</details>';

echo '<details class="skai-faq__item">';
echo '<summary class="skai-faq__q">Does this page tell me the odds, ticket cost, or payout structure for each game?</summary>';
echo '<div class="skai-faq__a"><p>This page focuses on results intelligence first: winning numbers, next draw dates, jackpots, and analysis access. Ticket cost, odds, and prize structure can differ by game, so those details should be confirmed on the official game page or the relevant state lottery source when needed.</p></div>';
echo '</details>';

echo '</div>'; // .skai-faq__items
echo '</section>';

// JSON-LD FAQPage
$faqJson = [
  '@context' => 'https://schema.org',
  '@type'    => 'FAQPage',
  'mainEntity' => [
    [
      '@type' => 'Question',
      'name'  => 'Where can I find the latest ' . $stName . ' lottery results?',
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text'  => 'Use the game cards on this page to view the latest winning numbers, next draw date, and jackpot when available. Select the analysis tools link on any game card to open results archives and deeper historical views for that game.'
      ]
    ],
    [
      '@type' => 'Question',
      'name'  => 'Are the jackpots and next draw dates updated automatically?',
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text'  => 'Yes. Each game tile displays the latest stored draw results along with the next draw date and jackpot when available. Some games may not publish jackpot values, so that field can be blank.'
      ]
    ],
    [
      '@type' => 'Question',
      'name'  => 'What do the analysis tools include?',
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text'  => 'Tools commonly include results archives, number frequency insights, skip/hit patterns, heatmaps, and wheel generators. Features can vary by game.'
      ]
    ],
    [
      '@type' => 'Question',
      'name'  => 'Why do some games show an extra ball (Power Play, Megaplier, Fireball, Wild Ball)?',
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text'  => 'Some lotteries add a multiplier or bonus feature. When the data is available, LottoExpert shows it under the winning numbers for that game.'
      ]
    ],
    [
      '@type' => 'Question',
      'name'  => 'Does this page tell me the odds, ticket cost, or payout structure for each game?',
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text'  => 'This page focuses on results intelligence first: winning numbers, next draw dates, jackpots, and analysis access. Ticket cost, odds, and payout structure can vary by game and should be confirmed on the official game page or state lottery source when needed.'
      ]
    ],
  ]
];

Factory::getDocument()->addCustomTag(
  '<script type="application/ld+json">'
  . json_encode($faqJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  . '</script>'
);

}
echo '<section class="le-results-intro" id="leResultsCards" aria-label="Results card introduction"><p>Each card below shows the latest stored draw result for a game, followed by the next draw date and the currently posted jackpot when available. Use these cards for fast reference. Use the analysis link inside each card when you want historical results, frequency interpretation, or deeper number behavior research.</p></section>';
echo '<div class="lotResultWrap">';
    
    foreach($resultList as $rl){
        
        /** GET RESULT LIST **/
        $gameId = $rl->game_id;

        // Phase 3: use batched latest rows (avoids per-game queries)
        if (!isset($latestByGameId[(string) $gameId])) {
            continue;
        }

        $drawResult = array($latestByGameId[(string) $gameId]);

foreach($drawResult as $dr){
            $gState = $dr->stateprov_name;
            $gId = $dr->game_id;
            $gName = $dr->game_name;
            $dDate = $dr->draw_date;

            if (leIsRetiredGame($gId, $retiredGameIds)) {
                continue;
            }
            $dResult = $dr->draw_results;
            $nDraw = $dr->next_draw_date;
            $nJackpot = $dr->next_jackpot;
            $gPhoto = leBuildGameLogoPath($stAbrev, $gName);
            $gameHref = leBuildGameHref($stName, $stAbrev, $gId, $gName);

            /** NUMBER POSITIONS **/
            $posOne = $dr->first;
            $posTwo = $dr->second;
            $posThree = $dr->third;
            $posFour = $dr->fourth;
            $posFive = $dr->fifth;
            $posSix = $dr->sixth;
            $posSeven = $dr->seventh;
            $posEight = $dr->eighth;
            $posNine = $dr->nineth;
            $posTen = $dr->tenth;
            $posEleven = $dr->eleventh;
            $posTwelve = $dr->twelveth;
            $posThirteen = $dr->thirtheenth;
            $posFourteen = $dr->fourteenth;
            $posFifteen = $dr->fifteenth;
            $posSixteen = $dr->sixteenth;
            $posSeventeen = $dr->seventeenth;
            $posEighteen = $dr->eighteenth;
            $posNineteen = $dr->nineteenth;
            $posTwenty = $dr->twentieth;
            $posTwentyOne = $dr->twenty_first;
            $posTwentyTwo = $dr->twenty_second;
            $posTwentyThree = $dr->twenty_third;
            $posTwentyFour = $dr->twenty_fourth;
            $posTwentyFive = $dr->twenty_fifth;

            // Daily-picks exclusions (shared by tiles + nav)
            $skaiDailyPickExcludeGameIds = [
                '101D','FLDF','FLBF','ILI','ILJ','CTCW','CTDW','INDF','INCF','MSCF','MSDF','NJDF','NJCF','NCCF','NCDF',
                'PADW','PACW','SCDF','SCCF','TNDW','TNBW','TNFW','TXBF','TXMF','TXLF','TXDF','VACF','VADF','CTAW','CTBW',
                'FLAF','FLCF','ILH','ILG','INBF','INAF','MSAF','MSBF','NCBF','NCAF','NJBF','NJAF','PABW','PAAW','SCBF',
                'SCAF','TNCW','TNAW','TNEW','TXCF','TXKF','TXJF','TXAF','VAAF','VABF','PAFW','PAEW','FLGF','FLHF','FLFF',
                'FLEF','PAGW','PAHW','NJG','VAEF','VAFF'
            ];

            $skaiDailyPickExcludeGameNames = [
                'Evening 3 Double',
                'Pick 4 Day Wild'
            ];
            
               /** EXCLUDE DAILY PICKS **/
            if ($gId != '101D' && $gId != 'FLDF' && $gId != 'FLBF' && $gId != 'ILI' && $gId != 'ILJ' && $gId != 'CTCW' && $gId != 'CTDW' && $gId != 'INDF' && $gId != 'INCF' && $gId != 'MSCF' && $gId != 'MSDF' && $gId != 'NJDF' && $gId != 'NJCF' && $gId != 'NCCF' && $gId != 'NCDF' && $gId != 'PADW' && $gId != 'PACW' && $gId != 'SCDF' && $gId != 'SCCF' && $gId != 'TNDW' && $gId != 'TNBW' && $gId != 'TNFW' && $gId != 'TXBF' && $gId != 'TXMF' && $gId != 'TXLF' && $gId != 'TXDF' && $gId != 'VACF' && $gId != 'VADF' && $gId != 'CTAW' && $gId != 'CTBW' && $gId != 'FLAF' && $gId != 'FLCF' && $gId != 'ILH' && $gId != 'ILG' && $gId != 'INBF' && $gId != 'INAF' && $gId != 'MSAF' && $gId != 'MSBF' && $gId != 'NCBF' && $gId != 'NCAF' && $gId != 'NJBF' && $gId != 'NJAF' && $gId != 'PABW' && $gId != 'PAAW' && $gId != 'SCBF' && $gId != 'SCAF' && $gId != 'TNCW' && $gId != 'TNAW' && $gId != 'TNEW' && $gId != 'TXCF' && $gId != 'TXKF' && $gId != 'TXJF' && $gId != 'TXAF' && $gId != 'VAAF' && $gId != 'VABF'  && $gId != 'PAFW' && $gId != 'PAEW' && $gId != 'FLGF' && $gId != 'FLHF' && $gId != 'FLFF' && $gId != 'FLEF' && $gId != 'PAGW' && $gId != 'PAHW' && $gId != 'NJG' && $gId != 'VAEF' && $gId != 'VAFF' && $gName != 'Evening 3 Double' && $gName != 'Pick 4 Day Wild') {
                
echo '<div class="resultWrap lottery-tile">'; // added lottery-tile for unified SKAI card styling
echo '<h2>'.htmlspecialchars($gName, ENT_QUOTES, 'UTF-8').'</h2>';
/** SET IMAGE CLICKABLE LINK **/
echo '<a title="View '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($gName, ENT_QUOTES, 'UTF-8').' Results & Analysis" href="'.htmlspecialchars($gameHref, ENT_QUOTES, 'UTF-8').'">';
echo '<img class="lotto-logo" src="'.htmlspecialchars($gPhoto, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($gName, ENT_QUOTES, 'UTF-8').'" loading="lazy" decoding="async" width="180" height="60">';
echo '</a>';
                
                /** POWERBALL RESULTS **/
                if($gName === 'Powerball'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span>&nbsp;&nbsp;<span class="circlesPb">'.$posSix.'</span><br /><span class="pplay">Power Play: <span class="circlesFb">'.$posSeven.'</span></span></p>';
  
  
                /** UK National, Lunchtime 49s, Teatime 49s IE Daily Million 2pm 9pm Plus 2pm 9pm**/
               }else if(($gName === 'Lunchtime 49s' && $stAbrev === 'uk') || ($gName === 'Teatime 49s' && $stAbrev === 'uk') || ($gName === 'LOTTO' && $stAbrev === 'uk') || ($gName === 'Daily Million 2PM' && $stAbrev === 'ie') || ($gName === 'Daily Million 9PM' && $stAbrev === 'ie') || ($gName === 'Daily Million Plus 2PM' && $stAbrev === 'ie') || ($gName === 'Daily Million Plus 9PM' && $stAbrev === 'ie') || ($gName === 'IrishLotto' && $stAbrev === 'ie') || ($gName === 'Lotto Plus 1' && $stAbrev === 'ie') || ($gName === 'Lotto Plus 2' && $stAbrev === 'ie')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span>&nbsp;&nbsp;<span class="circlesPb">'.$posSeven.'</span></p>';
  

                /** EuroMillions **/
                }else if($gName === 'EuroMillions' && $stAbrev === 'uk'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br />&nbsp;&nbsp;<span class="pplay">Lucky Stars: <span class="circlesPb">'.$posSix.'</span><span class="circlesPb">'.$posSeven.'</span></span><br /></p>';
  
  
                /** THUNDERBALL RESULTS **/
                }else if($gName === 'Thunderball' && $stAbrev === 'uk'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br />&nbsp;&nbsp;<span class="pplay">Thunderball: <span class="circlesPb">'.$posSix.'</span><br /></span></p>';

                /** Health Lottery RESULTS **/
                }else if($gName === 'Health Lottery' && $stAbrev === 'uk'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br />&nbsp;&nbsp;<span class="pplay">Bonus: <span class="circlesPb">'.$posSix.'</span><br /></span></p>';
  
                   /** Millionaire Rafffle RESULTS **/            
                  }else if($gName === 'Millionaire Raffle' && $stAbrev === 'uk'){
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span style="font-weight:bold; font-size:1.2em;">'.$posOne.'</span></p>';
                  
  
                /** Delaware, IOWA, IDAHO, MAINE, Minnesota, Montana, North Dakota, New Mexico,cash 5 Oklahoma, South Dakota, Tennessee, West-Virginia Lotto America RESULTS**/
                  }else if(($gName === 'Lotto America' && $stAbrev === 'de') || ($gName === 'Lotto America' && $stAbrev === 'ia') || ($gName === 'Lotto America' && $stAbrev === 'id') || ($gName === 'Lotto America' && $stAbrev === 'me') || ($gName === 'Lotto America' && $stAbrev === 'mn') || ($gName === 'Lotto America' && $stAbrev === 'mt') || ($gName === 'Lotto America' && $stAbrev === 'nd') || ($gName === 'Lotto America' && $stAbrev === 'nm') || ($gName === 'Lotto America' && $stAbrev === 'ok') || ($gName === 'Lotto America' && $stAbrev === 'sd') || ($gName === 'Lotto America' && $stAbrev === 'tn') || ($gName === 'Lotto America' && $stAbrev === 'wv') || ($gName === 'Lotto America' && $stAbrev === 'ks')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Star Ball:  <span class="circlesFb">'.$posSix.'</span></span></p>';
               
                
                /** Cash Ball RESULTS **/
                }else if($gName === 'Super Cash' && $stAbrev === 'ks'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Cash Ball: <span class="circlesFb">'.$posSix.'</span></span></p>';
                
                /** Cash Ball RESULTS **/
                }else if($gName === 'Cash Ball' && $stAbrev === 'ky'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /><span class="pplay">Cash Ball: <span class="circlesFb">'.$posFive.'</span></span></p>';
                
                /** Big Sky Bonus RESULTS **/
                }else if($gName === 'Big Sky Bonus' && $stAbrev === 'mt'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /><span class="pplay">Bonus: <span class="circlesFb">'.$posFive.'</span></span></p>';
                
                /** New Jersey Cash 5 RESULTS **/
                }else if($gName === 'Cash 5' && $stAbrev === 'nj'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Xtra: <span class="circlesFb">'.$posSix.'</span></span></p>';
                
                /** SuperLotto Plus RESULTS **/
                }else if($gName === 'SuperLotto Plus'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Mega Ball: <span class="circlesFb">'.$posSix.'</span></span></p>';
                
                /** MEGA MILLIONS RESULTS **/
              //  }else if($gName === 'Mega Millions'){
                }else if($gId === '113'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span>&nbsp;&nbsp;<span class="circlesPb">'.$posSix.'</span><br /><span class="pplay">Megaplier: <span class="circlesFb">'.$posSeven.'</span></span></p>';
                
                /** Bank a Million GAMES **/
                }else if($gName === 'Bank a Million'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /><span class="pplay">Bonus: <span class="circlesFb">'.$posSeven.'</span></span></p>';
                
                /** Cash4Life GAMES **/
            /**     }else if($gName === 'Cash4Life'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Cash Ball: <span class="circlesFb">'.$posSix.'</span></span></p>';
                **/
                /** Lucky For Life GAMES **/
          /**       }else if($gName === 'Lucky For Life'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Cash Ball: <span class="circlesFb">'.$posSix.'</span></span></p>';
                **/
/** Millionaire For Life GAMES — Multistate **/
} else if ($gId === '145') {

    echo '<p class="lstResult">Last Result: ' . date('m-d-Y', strtotime($dDate)) . '<br /><br />'
        . '<span class="circles">' . $posOne . '</span>'
        . '<span class="circles">' . $posTwo . '</span>'
        . '<span class="circles">' . $posThree . '</span>'
        . '<span class="circles">' . $posFour . '</span>'
        . '<span class="circles">' . $posFive . '</span><br />'
        . '<span class="pplay">Life Ball: <span class="circlesFb">' . $posSix . '</span></span>'
        . '<br /><span class="pplay">Multistate</span></p>';

                /** Arkansas LOTTO GAME - OSCAR ADDED 06/13/23 **/
                }else if($gName === 'LOTTO' && $stName === 'Arkansas'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /><span class="pplay">Bonus:  <span class="circlesFb">'.$posSeven.'</span></span></p>';
                
                
                /** MO Millions Main (MOH) and Double Play (MOI) - 6 balls + Millions Ball **/
                }else if($gId === 'MOH' || $gId === 'MOI'){
                    $moTileBonus = trim((string) $posSeven);
                    if ($moTileBonus === '' || $moTileBonus === '0') {
                        $_moTileRaw = trim((string) $dResult);
                        if (preg_match('/\bbonus[:\s]+(\d{1,2})\b/i', $_moTileRaw, $_moTileBm)) {
                            $_moTileVal = (string) (int) $_moTileBm[1];
                            $moTileBonus = ($_moTileVal !== '0') ? $_moTileVal : '';
                        }
                        if ($moTileBonus === '') {
                            $_moTileParts = array_values(array_filter(
                                preg_split('/[\s,\-\.]+/', $_moTileRaw),
                                static function ($t) { return is_numeric(trim($t)); }
                            ));
                            $_moTile7 = isset($_moTileParts[6]) ? trim($_moTileParts[6]) : '';
                            $moTileBonus = ($_moTile7 !== '' && $_moTile7 !== '0') ? $_moTile7 : '';
                        }
                    }
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /><span class="pplay">Millions Ball: <span class="circlesFb">'.htmlspecialchars($moTileBonus, ENT_QUOTES, 'UTF-8').'</span></span></p>';
                
                
                /** LOTTO GAMES **/
                }else if(($gName === 'Lotto' && $stName != 'Illinois' && $stName != 'New York') || ($gName === 'Hoosier Lotto' && $stName === 'Indiana')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span></p>';
                
                                              
                /** LOTTO Double Play GAMES Pick 5 North Carolina Cash 5 Double play Modified and added by Oscar uk **/
                }else if($gName === 'Double Play' && $stName === 'North Carolina'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span></p>';
                


                /** LOTTO Double Play GAMES Pick 6 **/
                }else if($gName === 'Double Play' && $stName != 'Illinois' && $stName != 'New York'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span></p>';
                
                
                /** Illinois LOTTO GAMES **/
                }else if($gName === 'Lotto' && $stName === 'Illinois'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /><span class="pplay">Extra Shot: <span class="circlesFb">'.$posSeven.'</span></span></p>';



                /** Wild Money GAMES **/
                }else if($gName === 'Wild Money' && $stName === 'Rhode Island'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Extra: <span class="circlesFb">'.$posSix.'</span></span></p>';
                
                /** Palmetto Cash 5 GAMES **/
                }else if($gName === 'Palmetto Cash 5' && $stName === 'South Carolina'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                
                /** New York LOTTO GAMES **/
                }else if($gName === 'Lotto' && $stName === 'New York'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /><span class="pplay">Bonus: <span class="circlesFb">'.$posSeven.'</span></span></p>';
                    
                
                /** Bonus Match 5, Tennessee Cash GAMES **/
                }else if(($gName === 'Bonus Match 5' && $stName === 'Maryland') || ($gName === 'Tennessee Cash' && $stName === 'Tennessee') || ($gName === 'Loto Plus' && $stName === 'Puerto Rico')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Bonus: <span class="circlesFb">'.$posSix.'</span></span></p>';
    
                    
                    /** Bonus Texas Two Step GAMES **/
                }else if($gName === 'Texas Two Step' && $stName === 'Texas'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /><span class="pplay">Bonus: <span class="circlesFb">'.$posFive.'</span></span></p>';
                    
    
                /** Megabucks Plus GAMES **/
                }else if(($gName === 'Megabucks Plus' && $stName === 'Maine') || ($gName === 'Megabucks Plus' && $stName === 'New Hampshire') || ($gName === 'Megabucks Plus' && $stName === 'Vermont')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Megaball: <span class="circlesFb">'.$posSix.'</span></span></p>';
                    
                
                /** (STRAIGHT 2) DC 2 Midday, DC 2 Evening GAMES, Puerto Rico Pega 2 Games **/
                }else if($gName === 'DC 2 1:50PM' || $gName === 'DC 2 7:50PM' || $gName === 'Pega 2 Day' || $gName === 'Pega 2 Noche'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span></p>';
                    
                
                /** (STRAIGHT 3) Cash 3 Midday, Cash 3 Evening, Daily 3 Midday, Daily 3 Evening, Play3 Day, Play3 Night, DC 3 Midday, DC 3 Evening, Play 3 Day, Play 3 Night, Cash 3 Night, Evening 3 Double, MyDay, Cash 3 Morning, Daily Game, Daily 3 GAMES **/
                }else if($gName === 'Daily Game' || $gName === 'DC 3 Midday' || $gName === 'DC 3 Evening' || $gName === 'Pega 3 Day' || $gName === 'Pega 3 Noche' || $gName === 'Evening 3 Double' || $gName === 'MyDay' || ($gName === 'Numbers Midday' && $stName === 'New York') || ($gName === 'Numbers Evening' && $stName === 'New York') || $gName === 'DC 3 7:50PM' || $gName === 'DC 3 1:50PM' || $gName === 'DC 3 11:30PM'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span></p>';
                    
                
                /** (STRAIGHT 4) Cash 4 Midday, Cash 4 Evening, Daily 4 GAMES, Play4 Day, DC 4 Midday, DC 4 Evening, Play 4 Day, Play 4 Night, Cash 4 Night, Daily 4 Midday, Daily 4 Evening, 2 By 2, Numbers Midday, Numbers Evening, Win 4 Midday, Win 4 Evening, Win for Life, Cash 4 Morning, Cash 4 Midday, Cash 4 Evening, Match 4 **/
                }else if($gName === 'DC 4 Midday' || $gName === 'DC 4 Evening' || $gName === 'Pega 4 Day' || $gName === 'Pega 4 Noche' || $gName === 'Play 4 Day' || $gName === 'Play 4 Night' || $gName === 'Cash 4 Night' || $gName === '2 By 2' || ($gName === 'Numbers Midday' && $stName != 'New York') || ($gName === 'Numbers Evening' && $stName != 'New York') || $gName === 'Win 4 Midday' || $gName === 'Win 4 Evening' || $gName === 'Win for Life'|| $gName === 'Match 4' || $gName === 'DC 4 7:50PM' || $gName === 'DC 4 1:50PM' || $gName === 'DC 4 11:30PM'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span></p>';
                    
                
                /** TX2 for Texas - dedicated handler **/
                }else if($gId === 'TX2' && $stAbrev === 'tx'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span></p>';
                    
                /** (STRAIGHT 5) Fantasy 5, Natural State Jackpot, Cash 5, DC 5 Midday, DC 5 Evening, Georgia FIVE Midday, Georgia FIVE Evening, Idaho Cash, 5 Star Draw, Weekly Grand, LuckyDay Lotto Midday, LuckyDay Lotto Evening, Easy 5, MassCash, Gimme 5, World Poker Tour, Poker Lotto, Gopher 5, NORTH5, Show Me Cash, Montana Cash, Roadrunner Cash, Take 5 Midday, Take 5 Evening, Rolling Cash 5, Treasure Hunt, Dakota Cash, Hit 5, Badger 5 Cowboy Draw GAMES **/
                }else if($gName === 'Fantasy 5' || $gName === 'Fantasy 5 Evening' || $gName === 'Fantasy 5 Midday' || $gName === 'Natural State Jackpot' || ($gName === 'Cash 5' && $stAbrev != 'nj' && $stAbrev != 'nc') || $gId === 'TX2' || $gName === 'DC 5 Midday' || $gName === 'DC 5 Evening' || $gName === 'Georgia FIVE Midday' || $gName === 'Georgia FIVE Evening' || $gName === 'Idaho Cash' || $gName === '5 Star Draw' || $gName === 'Weekly Grand' || $gName === 'LuckyDay Lotto Midday' || $gName === 'LuckyDay Lotto Evening' || $gName === 'Easy 5' || $gName === 'MassCash' || $gName === 'Gimme 5' || $gName === 'World Poker Tour' || $gName === 'Poker Lotto' || $gName === 'Gopher 5' || $gName === 'NORTH5' || $gName === 'Show Me Cash' || $gName === 'Montana Cash' || $gName === 'Roadrunner Cash' || $gName === 'Take 5 Midday' || $gName === 'Take 5 Evening' || $gName === 'Rolling Cash 5' || $gName === 'Treasure Hunt' || $gName === 'Dakota Cash' || $gName === 'Hit 5' || $gName === 'Badger 5' || $gName === 'Cowboy Draw' || $gName === 'DC 5 7:50PM' || $gName === 'DC 5 1:50PM'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span></p>';
                    
                
                /** (STRAIGHT 6) Jackpot Triple Play, Triple Twist, Lotto Plus, Multi-Win Lotto, Jumbo Bucks Lotto, Megabucks Doubler, MultiMatch, Classic Lotto 47, Classic Lotto, Megabucks, Match 6 Lotto, Super Cash, Cash 25, GAMES **/
                }else if($gName === 'Jackpot Triple Play' || $gName === 'Triple Twist' || $gName === 'Lotto Plus' || $gName === 'Multi-Win Lotto' || $gName === 'Megabucks Doubler' || $gName === 'MultiMatch' || $gName === 'Classic Lotto 47' || $gName === 'Classic Lotto' || $gName === 'Megabucks' || $gName === 'Match 6 Lotto' || $gName === 'Super Cash' || $gName === 'Cash 25'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span></p>';
                    
                
                /** (STRAIGHT 8) Lucky Lines GAMES **/
                }else if($gName === 'Lucky Lines'){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><span class="circles">'.$posSeven.'</span><span class="circles">'.$posEight.'</span></p>';
                    
                
                /** ALL All or Nothing Midday, All or Nothing Evening GAMES **/
                }else if(($gName === 'All or Nothing Evening' && $stName === 'Wisconsin') || ($gName === 'All or Nothing Midday' && $stName === 'Wisconsin')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><span class="circles">'.$posSeven.'</span><span class="circles">'.$posEight.'</span><span class="circles">'.$posNine.'</span><span class="circles">'.$posTen.'</span><span class="circles">'.$posEleven.'</span></p>';
                    
                
                /** ALL (TX) All or Nothing Midday, All or Nothing Evening GAMES **/
                }else if(($gName === 'All or Nothing Morning' && $stName === 'Texas') || ($gName === 'All or Nothing Day' && $stName === 'Texas') || ($gName === 'All or Nothing Evening' && $stName === 'Texas') || ($gName === 'All or Nothing Night' && $stName === 'Texas')){
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><span class="circles">'.$posSeven.'</span><span class="circles">'.$posEight.'</span><span class="circles">'.$posNine.'</span><span class="circles">'.$posTen.'</span><span class="circles">'.$posEleven.'</span><span class="circles">'.$posTwelve.'</span></p>';
                    
                
                /** ALL Indiana Quick Draw Midday, Quick Draw Evening GAMES **/
                }else if(($gName === 'Quick Draw Midday' && $stName === 'Indiana') || ($gName === 'Quick Draw Evening' && $stName === 'Indiana')){
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><span class="circles">'.$posSeven.'</span><span class="circles">'.$posEight.'</span><span class="circles">'.$posNine.'</span><span class="circles">'.$posTen.'</span><span class="circles">'.$posEleven.'</span><span class="circles">'.$posTwelve.'</span><span class="circles">'.$posThirteen.'</span><span class="circles">'.$posFourteen.'</span><span class="circles">'.$posFifteen.'</span><span class="circles">'.$posSixteen.'</span><span class="circles">'.$posSeventeen.'</span><span class="circles">'.$posEighteen.'</span><span class="circles">'.$posNineteen.'</span><span class="circles">'.$posTwenty.'</span><span class="circles">'.$posTwentyOne.'</span><br /></p>';
                
                
                /** Michigan Keno + New York Keno GAME **/
                }else if($gId === 'MI3' || $gId === 'NY3'){

                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br />';

                    echo '<span class="circles">'.$posOne.'</span>';
                    echo '<span class="circles">'.$posTwo.'</span>';
                    echo '<span class="circles">'.$posThree.'</span>';
                    echo '<span class="circles">'.$posFour.'</span>';
                    echo '<span class="circles">'.$posFive.'</span>';
                    echo '<span class="circles">'.$posSix.'</span>';
                    echo '<span class="circles">'.$posSeven.'</span>';
                    echo '<span class="circles">'.$posEight.'</span>';
                    echo '<span class="circles">'.$posNine.'</span>';
                    echo '<span class="circles">'.$posTen.'</span>';
                    echo '<span class="circles">'.$posEleven.'</span>';
                    echo '<span class="circles">'.$posTwelve.'</span>';
                    echo '<span class="circles">'.$posThirteen.'</span>';
                    echo '<span class="circles">'.$posFourteen.'</span>';
                    echo '<span class="circles">'.$posFifteen.'</span>';
                    echo '<span class="circles">'.$posSixteen.'</span>';
                    echo '<span class="circles">'.$posSeventeen.'</span>';
                    echo '<span class="circles">'.$posEighteen.'</span>';
                    echo '<span class="circles">'.$posNineteen.'</span>';
                    echo '<span class="circles">'.$posTwenty.'</span>';

                    if($gId === 'MI3'){
                        echo '<span class="circles">'.$posTwentyOne.'</span>';
                        echo '<span class="circles">'.$posTwentyTwo.'</span>';
                    }

                    echo '</p>';
   
   
      
      
    /** Oscar Tx3 pick 2 merge, Pick 2 With FB **/
                }else if($gId === 'FLF'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLFF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><br /></p>';
                    echo '<p class="lstResult"> Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
               }else if($gId === 'FLE'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLEF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><br /></p>';
                    echo '<p class="lstResult"> Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
               }else if($gId === 'PAG'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PAGW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><br /></p>';
                    echo '<p class="lstResult"> Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
               }else if($gId === 'PAH'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PAHW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><br /></p>';
                    echo '<p class="lstResult"> Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

   
      
   
    /** Oscar T pick 3 merge, Pick 3 With FB **/
                }else if($gId === 'CTA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'CTAW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                 }else if($gId === 'CTB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'CTBW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                  }else if($gId === 'FLA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
         
         
                
                  }else if($gId === 'FLC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === '121'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'ILH' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === '120'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'ILG' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'INB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'INBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'INA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'INAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'MSA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'MSAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'MSB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'MSBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'NCB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NCBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'NCA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NCAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'NJB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NJBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'NJA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NJAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'PAB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PABW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'PAA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PAAW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'SCB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'SCBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'SCA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'SCAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TNC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNCW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TNA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNAW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TNE'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNEW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TXC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TXK'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXKF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TXJ'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXJF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'TXA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'VAA'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'VAAF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

         
                
                  }else if($gId === 'VAB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'VABF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><br /></p>';
                    echo '<p class="lstResult"> Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

   
   
        /** Oscar T pick 4 merge, Pick 4 Midday / Evening With FB **/
                }else if($gId === 'FLD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                }else if($gId === 'FLB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';


                }else if($gId === '122'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'ILI' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                }else if($gId === '123'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'ILJ' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
                
                 }else if($gId === 'CTC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'CTCW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Play4 Day Wild: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

               }else if($gId === 'CTD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'CTDW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Wild Ball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
                
}else if($gId === 'IND'){

    $db = Factory::getDbo();
    // Match the same draw date as the base game + use draw_results (keeps leading zeros)
    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
    $qState = $db->quote($stNameNormalized);
    $sqlfb = "SELECT `draw_results`
              FROM `$dbCol`
              WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState
                AND `game_id` = 'INDF'
                AND DATE(`draw_date`) = $qDate
              ORDER BY `id` DESC
              LIMIT 1";
    $db->setQuery($sqlfb);
    $db->execute();

    $fbRaw    = (string) $db->loadResult();
    $fbResult = preg_replace('/\D+/', '', $fbRaw);

    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
      

                }else if($gId === 'INC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'INCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
               
                }else if($gId === 'MSC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'MSCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
               
                }else if($gId === 'MSD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'MSDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                }else if($gId === 'NJD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NJDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
                   }else if($gId === 'NJC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NJCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'NCC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NCCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'NCD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'NCDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'PAD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PADW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
                   }else if($gId === 'PAC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PACW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'SCD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'SCDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                
                   }else if($gId === 'SCC'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'SCCF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TND'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNDW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TNB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNBW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TNF'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TNFW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                


                   }else if($gId === 'TXB'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXBF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TXM'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXMF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TXL'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXLF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'TXD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'TXDF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

}else if($gId === 'VAC'){

    $db = Factory::getDbo();
    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
    $qState = $db->quote($stNameNormalized);
    $sqlfb = "SELECT `draw_results`
              FROM `$dbCol`
              WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState
                AND `game_id` = 'VACF'
                AND DATE(`draw_date`) = $qDate
              ORDER BY `id` DESC
              LIMIT 1";
    $db->setQuery($sqlfb);
    $db->execute();

    $fbRaw    = (string) $db->loadResult();
    $fbResult = preg_replace('/\D+/', '', $fbRaw);

    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';









                   }else if($gId === 'VAD'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'VADF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   /** Virginia Pick 5 Evening (VAE) with EZ Match **/
                   }else if($gId === 'VAE'){

                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'VAEF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();

                    $fbRaw    = (string) $db->loadResult();
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);

                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">EZ Match: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';


                   /** Virginia Pick 5 Day (VAF) with EZ Match **/
                   }else if($gId === 'VAF'){

                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'VAFF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();

                    $fbRaw    = (string) $db->loadResult();
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);

                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">EZ Match: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';


                   }else if($gId === 'FLH'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLHF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                   }else if($gId === 'FLG'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'FLGF' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">Fireball: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                



                   }else if($gId === 'PAE'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PAEW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">Wild : <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                



                   }else if($gId === 'PAF'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = 'PAFW' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /></p>';
                    echo '<p class="lstResult">Wild: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                

                  /** Oscar T Lotto America joining with All Star Bonus  **/
                /**   }else if($gName === 'Lotto America' && $gId === '135'){
                    
                    $db = Factory::getDbo();
                    $qDate  = $db->quote(date('Y-m-d', strtotime($dDate)));
                    $qState = $db->quote($stNameNormalized);
                    $sqlfb = "SELECT `draw_results` FROM `$dbCol` WHERE TRIM(LOWER(REPLACE(`stateprov_name`, '-', ' '))) = $qState AND `game_id` = '136' AND DATE(`draw_date`) = $qDate ORDER BY `id` DESC LIMIT 1";
                    $db->setQuery($sqlfb);
                    $db->execute();
                    
                    $fbRaw    = (string) $db->loadResult();

                    
                    $fbResult = preg_replace('/\D+/', '', $fbRaw);
                    
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><br /><span class="pplay">Star Ball:  <span class="circlesFb">'.$posSix.'</span></span></p>';
                    echo '<p class="lstResult">All Star Bonus: <span class="circlesPb">'.htmlspecialchars($fbResult, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                **/

 
                
                                /** ALL OTHER GAMES **/
                }else if($gId === 'MOH' || $gId === 'MOI'){
                    /** MO Millions Main / Double Play - 6 balls + Millions Ball stored in posSeven **/
                    $moTileBonus2 = trim((string) $posSeven);
                    if ($moTileBonus2 === '' || $moTileBonus2 === '0') {
                        $_moTile2Raw = trim((string) $dResult);
                        if (preg_match('/\bbonus[:\s]+(\d{1,2})\b/i', $_moTile2Raw, $_moTile2Bm)) {
                            $_moTile2Val = (string) (int) $_moTile2Bm[1];
                            $moTileBonus2 = ($_moTile2Val !== '0') ? $_moTile2Val : '';
                        }
                        if ($moTileBonus2 === '') {
                            $_moTile2Parts = array_values(array_filter(
                                preg_split('/[\s,\-\.]+/', $_moTile2Raw),
                                static function ($t) { return is_numeric(trim($t)); }
                            ));
                            $_moTile27 = isset($_moTile2Parts[6]) ? trim($_moTile2Parts[6]) : '';
                            $moTileBonus2 = ($_moTile27 !== '' && $_moTile27 !== '0') ? $_moTile27 : '';
                        }
                    }
                    echo '<p class="lstResult">Last Result: '.date('m-d-Y',strtotime($dDate)).'<br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span><span class="circles">'.$posSix.'</span><br /></p>';
                    echo '<p class="lstResult">Millions Ball: <span class="circlesPb">'.htmlspecialchars($moTileBonus2, ENT_QUOTES, 'UTF-8').'</span><br /></p>';
                }else if (preg_match('/^\s*(Cash\s*Pop|Pop|Pick\s*1)/i', $gName)) {
                    // Single-ball: render strictly from draw_results to keep any leading zero
                    $num = trim((string)$dResult);
                    $num = preg_replace('/\D+/', '', $num); // normalize (e.g., "03 " -> "03")
                    echo '<p class="lstResult">Last Result: ' . date('m-d-Y', strtotime($dDate)) . '<br /><span class="circles">' . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') . '</span></p>';
                }else{
                    if($gId != 'AR2'){ /** EXCLUDE AR LOTTO **/
                        // Robust split for -, space, comma, or dot. If draw_results is malformed
                        // or concatenated for multi-number games, fall back to stored position fields.
                        $parts = preg_split('/\s*[-,\s\.]\s*/', trim((string)$dResult));
                        $parts = array_values(array_filter($parts, 'strlen'));

                        $positionValues = array(
                            $posOne, $posTwo, $posThree, $posFour, $posFive,
                            $posSix, $posSeven, $posEight, $posNine, $posTen,
                            $posEleven, $posTwelve, $posThirteen, $posFourteen, $posFifteen,
                            $posSixteen, $posSeventeen, $posEighteen, $posNineteen, $posTwenty,
                            $posTwentyOne, $posTwentyTwo, $posTwentyThree, $posTwentyFour, $posTwentyFive
                        );
                        $positionValues = array_values(array_filter($positionValues, function ($value) {
                            return trim((string) $value) !== '';
                        }));

                        $usePositionValues = false;
                        $chunkedDenseValues = array();
                        $isDenseBallGame = (bool) preg_match('/^(Pick\s*10|Pick\s*11|Pick\s*12|Pick\s*13|Pick\s*14|Pick\s*15|Pick\s*20|Keno|Quick Draw)/i', $gName);
                        $ballClass = 'circles';

                        if (count($parts) <= 1 && count($positionValues) >= 2) {
                            $usePositionValues = true;
                        }

                        if (!$usePositionValues && count($positionValues) >= 2) {
                            $hasTextTokens = false;

                            foreach ($parts as $part) {
                                if (!preg_match('/^\d+$/', (string) $part)) {
                                    $hasTextTokens = true;
                                    break;
                                }
                            }

                            if ($hasTextTokens) {
                                $usePositionValues = true;
                            }
                        }

                        if (!$usePositionValues && $isDenseBallGame && count($positionValues) >= 2) {
                            $usePositionValues = true;
                        }

                        if (!$usePositionValues && $isDenseBallGame) {
                            $digitsOnly = preg_replace('/\D+/', '', (string) $dResult);
                            if ($digitsOnly !== '' && strlen($digitsOnly) >= 4 && (strlen($digitsOnly) % 2) === 0) {
                                $chunkedDenseValues = str_split($digitsOnly, 2);
                            }
                        }

                        if (($usePositionValues && count($positionValues) >= 10) || (!$usePositionValues && !empty($chunkedDenseValues) && count($chunkedDenseValues) >= 10) || count($parts) >= 10) {
                            $ballClass = 'circles circles--compact';
                        }

                        if ($usePositionValues) {
                            $out = leRenderBallSpans($positionValues, $ballClass);
                        } elseif (!empty($chunkedDenseValues)) {
                            $out = leRenderBallSpans($chunkedDenseValues, $ballClass);
                        } else {
                            $safeParts = array();
                            foreach ($parts as $part) {
                                $safeParts[] = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
                            }
                            $out = '<span class="' . htmlspecialchars($ballClass, ENT_QUOTES, 'UTF-8') . '">' . implode('</span><span class="' . htmlspecialchars($ballClass, ENT_QUOTES, 'UTF-8') . '">', $safeParts) . '</span>';
                        }

                        echo '<p class="lstResult">Last Result: ' . date('m-d-Y', strtotime($dDate)) . '<br />' . $out . '</p>';
                    }else{
                        echo '<p class="lstResult">Last Result: ' . date('m-d-Y', strtotime($dDate)) . '<br /><span>' . htmlspecialchars($dResult, ENT_QUOTES, 'UTF-8') . '</span></p>';
                    }
               }

if($gId === 'MI3' || $gId === 'IN9' || $gId === 'IN7' || $gId === 'WA4' || $gId === 'NY3'){ /** ADD SOME GAP FOR KENO GAMES **/
    echo '<p class="nDraw" style="margin-top:60px">Next Draw</p>';
}else{
    // Added inline top margin so "Next Draw" sits clearly below Power Play / Megaplier / Cash Ball
    echo '<p class="nDraw" style="margin-top:35px">Next Draw</p>';
}
echo '<h3 class="nDrawDate">'.date('m-d-Y',strtotime($nDraw)).'</h3>';

/** IF NO JACKPOT INFO THEN DISPLAY NOTHING **/
if($nJackpot != '' && $nJackpot > '0' && $nJackpot != 'n/a'){
    echo '<p class="nDraw">Next Jackpot</p>';
    echo '<h3 class="nJackpot">$'.number_format((float)$nJackpot, 0, '.', ',').'</h3>';
} /** EO IF NO JACKPOT INFO **/

/** SET RESULTS AND ANALYSIS LINK ? wrapped in lotto-actions for SKAI tile layout **/
echo '<div class="lotto-actions">';
echo '<a title="View '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($gName, ENT_QUOTES, 'UTF-8').' Results & Analysis" class="rnaBtn pbHistoryBtn" href="'.htmlspecialchars($gameHref, ENT_QUOTES, 'UTF-8').'">';

echo 'Open Advanced Number Analysis';
echo '</a>';
echo '</div>'; // .lotto-actions
echo '</div>';
            } /** EO EXCLUDE DAILY PICKS **/
        } /** EO FOREACH **/
    }
    
/** Close tiles wrapper **/

echo '</div>'; // .lotResultWrap

echo '<div class="le-ad-zone" aria-label="Deep engagement zone"><span class="le-ad-zone__label">Deep engagement</span><p class="le-ad-zone__text">Users who continue below this point are usually looking for interpretation, history, odds-adjacent context, or related research paths. That makes this another strong non-intrusive monetization zone without interrupting the result cards themselves.</p></div>';

/** INJECT FOOTER DESCRIPTION TEXT (query builder + prepared output) **/
if (!empty($stAbrevSql)) {
    $qState = strtoupper($stAbrevSql);

    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select($db->quoteName('footertext'))
        ->from($db->quoteName('#__lottostates_words'))
        ->where($db->quoteName('statename') . ' = :statename')
        ->where($db->quoteName('state') . ' = 1')
        ->bind(':statename', $qState);

    $db->setQuery($query);
    $fottext = (string) $db->loadResult();

    if ($fottext !== '') {
        echo '<div class="ftextwrapper">';
        echo '<div class="le-footer-reset">';
        echo JHtml::_('content.prepare', $fottext);
        echo '</div>';
        echo '</div>';
    }
}

echo '<section class="le-state-reference" aria-label="About this state lottery page">';
echo '<h2 class="le-state-reference__title">About this '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' lottery page</h2>';
echo '<p class="le-state-reference__text">This page is structured to work as a clear state lottery reference hub. It provides top-level access to '.htmlspecialchars($stName, ENT_QUOTES, 'UTF-8').' winning numbers, next draw dates, jackpot references, and advanced analysis routes for each available game. The deeper pages can extend that view into historical results databases, frequency-based interpretation, active and quiet number context, hot and cold number studies, and broader draw history insights. The goal is clarity, not hype: information that is easy to scan, easy to interpret, and useful for both quick checks and more serious research.</p>';
echo '</section>';

} else {
    echo 'No lottery found for this State';
}

echo '</div>'; /** EO MAIN RIGHT COLUMN sidebar adjustment**/
/** EO THE COLUMN SYSTEM **/
echo '</div>';
echo '</div>';