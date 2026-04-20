<?php
/**
 * @version    CVS: 1.0.3
 * @package    Com_Lotterydb
 * @author     FULLSTACK DEV <admin@fullstackdev.us>
 * @copyright  2022 FULLSTACK DEV
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;

$showPageHeader = true;

$app   = Factory::getApplication();
$doc   = Factory::getDocument();
$input = $app->input;
$db    = Factory::getDbo(); // CHG: Joomla 5 standard DB handle

// --- Get and normalize inputs (CHG: strict + predictable) ---
$stateNameRaw = $input->getString('stn', 'Florida');
if ($stateNameRaw !== 'West-Virginia') {
    $stateNameRaw = str_replace('-', ' ', $stateNameRaw);
}
$stateNameRaw = trim($stateNameRaw);

$gNameRaw = $input->getString('gm', 'Powerball');
if ($gNameRaw !== 'Multi-Win Lotto') {
    $gNameRaw = str_replace('-', ' ', $gNameRaw);
}
$gNameRaw = ucwords(trim($gNameRaw));

// CHG: whitelist state abbrev to avoid table-name injection
// Prefer explicit `st`; if absent/invalid, derive from `stn` before safe fallback.
$stateAbrevRaw = strtolower(trim($input->getString('st', '')));
if (!preg_match('/^[a-z]{2}$/', $stateAbrevRaw)) {
    $stateAbrevFromName = [
        'arkansas'     => 'ar',
        'missouri'     => 'mo',
        'florida'      => 'fl',
        'pennsylvania' => 'pa',
        'virginia'     => 'va',
    ];
    $stateNameKey = strtolower(trim((string) str_replace('-', ' ', $stateNameRaw)));
    $stateAbrevRaw = $stateAbrevFromName[$stateNameKey] ?? '';
}
if (!preg_match('/^[a-z]{2}$/', $stateAbrevRaw)) {
    $stateAbrevRaw = 'fl';
}
$stateAbrev = $stateAbrevRaw;

// CHG: keep as a separate variable; always quoteName when used in queries
$dbCol = '#__lotterydb_' . $stateAbrev;

// Sanitize for HTML output (keep raw variants for DB binding)
$stateName = htmlspecialchars($stateNameRaw, ENT_QUOTES, 'UTF-8');
$gName     = htmlspecialchars($gNameRaw, ENT_QUOTES, 'UTF-8');

// CHG: Always use RAW name for branching/logic; escaped name is for output only
$gNameLogic = $gNameRaw;

/**
 * CHG: This page now has a SKAI-style digit analysis header + latest results card.
 * The legacy "top results" block (logo/date/balls) would duplicate the same info.
 * Suppress legacy rendering for digit-based daily games (Pick 3 / Numbers / Cash 3 / etc.).
 */
$leSuppressLegacyTopResults = (bool) preg_match(
    '/\b(Pick\s*3|Daily\s*3|Numbers|Cash\s*3|Play\s*3|Win\s*3)\b/i',
    (string) $gNameLogic
);

// CHG: canonical + social URL safety
$currentUrlObj = Uri::getInstance();
$currentUrl    = (string) $currentUrlObj;
$currentUrl    = filter_var($currentUrl, FILTER_VALIDATE_URL) ? $currentUrl : Uri::base();

$imageUrl = Uri::base() . 'images/lottoexpert_logo-stacked.jpg';

/**
 * ---------------------------------------------------------------------
 * CHG: Non-breaking DB wrapper helpers (Joomla-safe, injection-resistant)
 * ---------------------------------------------------------------------
 * - Centralizes table quoting and parameter binding
 * - Replaces repeated raw SQL string concatenation patterns
 * - Designed to be adopted incrementally (no behavior changes)
 */

if (!function_exists('leQuoteTable')) {
    function leQuoteTable(\Joomla\Database\DatabaseInterface $db, string $table): string
    {
        // Accept #__ prefix and quote safely for the active DB driver
        return $db->quoteName($table);
    }
}

if (!function_exists('leFetchScalar')) {
    /**
     * Fetch a single scalar value using query builder + bind.
     *
     * @param array<string, mixed> $whereEq  Exact-match where conditions ['col' => value]
     */
    function leFetchScalar(
        \Joomla\Database\DatabaseInterface $db,
        string $table,
        string $selectCol,
        array $whereEq = [],
        ?string $orderBy = null
    ): string {
        $query = $db->getQuery(true)
            ->select($db->quoteName($selectCol))
            ->from(leQuoteTable($db, $table));

        foreach ($whereEq as $col => $val) {
            // Unique bind key per column to avoid collisions
            $bindKey = ':w_' . preg_replace('/[^a-z0-9_]+/i', '_', (string) $col);
            $query->where($db->quoteName((string) $col) . ' = ' . $bindKey);
            $query->bind($bindKey, $val);
        }

        if (!empty($orderBy)) {
            $query->order($orderBy);
        }

        $db->setQuery($query, 0, 1);

        $res = $db->loadResult();

        return is_scalar($res) ? (string) $res : '';
    }
}

if (!function_exists('leFetchDrawResultsByDate')) {
    /**
     * Fetch draw_results for a given game_id on the same calendar day as $drawDateYmd.
     * Optional stateprov_name match (used in some tables).
     *
     * @param string $drawDateYmd YYYY-mm-dd
     */
    function leFetchDrawResultsByDate(
        \Joomla\Database\DatabaseInterface $db,
        string $table,
        string $gameId,
        string $drawDateYmd,
        ?string $stateprovName = null
    ): string {
        $query = $db->getQuery(true)
            ->select($db->quoteName('draw_results'))
            ->from(leQuoteTable($db, $table))
            ->where($db->quoteName('game_id') . ' = :gid')
            ->where('DATE(' . $db->quoteName('draw_date') . ') = :dte')
            ->order($db->quoteName('id') . ' DESC');

        $query->bind(':gid', $gameId);
        $query->bind(':dte', $drawDateYmd);

        if ($stateprovName !== null && $stateprovName !== '') {
            $query->where($db->quoteName('stateprov_name') . ' = :spn');
            $query->bind(':spn', $stateprovName);
        }

        $db->setQuery($query, 0, 1);

        $res = $db->loadResult();

        return is_scalar($res) ? (string) $res : '';
    }
}

if (!function_exists('leFetchColumnByDate')) {
    /**
     * CHG: Fetch a specific column (e.g., 'first') for a given game_id
     * on the same calendar day as $drawDateYmd. Optional stateprov_name match.
     *
     * This fixes "wrong companion game value" bugs caused by fetching
     * a random row without a date constraint.
     *
     * @param string $drawDateYmd YYYY-mm-dd
     */
    function leFetchColumnByDate(
        \Joomla\Database\DatabaseInterface $db,
        string $table,
        string $selectCol,
        string $gameId,
        string $drawDateYmd,
        ?string $stateprovName = null
    ): string {
        $query = $db->getQuery(true)
            ->select($db->quoteName($selectCol))
            ->from(leQuoteTable($db, $table))
            ->where($db->quoteName('game_id') . ' = :gid')
            ->where('DATE(' . $db->quoteName('draw_date') . ') = :dte')
            ->order($db->quoteName('id') . ' DESC');

        $query->bind(':gid', $gameId);
        $query->bind(':dte', $drawDateYmd);

        if ($stateprovName !== null && $stateprovName !== '') {
            $query->where($db->quoteName('stateprov_name') . ' = :spn');
            $query->bind(':spn', $stateprovName);
        }

        $db->setQuery($query, 0, 1);

        $res = $db->loadResult();

        return is_scalar($res) ? (string) $res : '';
    }
}

if (!function_exists('leFilenameSlug')) {
    /**
     * CHG: Build a predictable, filesystem-safe slug for image filenames.
     * - Uses raw game name (not HTML-escaped)
     * - Normalizes whitespace and strips unsafe characters
     */
    function leFilenameSlug(string $s): string
    {
        $s = trim($s);
        $s = strtolower($s);

        // Normalize common separators that appear in game names
        $s = str_replace(['&', '/', '\\', '+'], ' ', $s);

        // Keep only letters, numbers, spaces, and hyphens
        $s = preg_replace('/[^a-z0-9\s-]+/', '', $s);

        // Collapse whitespace/hyphens to single hyphens
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);

        return trim($s, '-');
    }
}

// CHG: safe canonical (fixes prior undefined $canon issue)
$doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . '">');

// Meta & Title
$browserbar = $stateName . ' (' . strtoupper($stateAbrev) . ') ' . $gName . ' AI Predictions, Lottery Results & Analysis';
$doc->setTitle($browserbar);

$m_description = $gName . ' - ' . $stateName . ' (' . strtoupper($stateAbrev) . '), AI predictions, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators.';
$doc->setDescription(strip_tags($m_description));

// Commented out to avoid undefined $canon bug
// $doc->addCustomTag("<link rel='canonical' href='{$canon}'>");

// Social Sharing Tags (Twitter & Open Graph)
$doc->addCustomTag('<meta name="twitter:card" content="summary_large_image">');
$doc->addCustomTag('<meta name="twitter:title" content="' . $stateName . ' ' . $gName . ' Results and Analysis - LottoExpert.net">');
$doc->addCustomTag('<meta name="twitter:description" content="All ' . $stateName . ' ' . $gName . ' AI predictions, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators.">');
$doc->addCustomTag('<meta name="twitter:image" content="' . $imageUrl . '">');

$doc->addCustomTag('<meta property="og:title" content="' . $stateName . ' ' . $gName . ' Results - LottoExpert.net">');
$doc->addCustomTag('<meta property="og:description" content="All ' . $stateName . ' ' . $gName . ' AI predictions, AI analysis, Skip and Hit, Heatmaps, archives and free Lotto Wheel Generators.">');
$doc->addCustomTag('<meta property="og:url" content="' . $currentUrl . '">');
$doc->addCustomTag('<meta property="og:type" content="article">');
$doc->addCustomTag('<meta property="og:image" content="' . $imageUrl . '">');





/** CUSTOM CSS ADJUSTMENT **/ ?>
<style type="text/css">
/* --- SKAI + LottoExpert (premium, calm, WCAG AA) --- */
/* CHG: Use stable tokens; keep existing class names to avoid breakage */
:root{
  --skai-blue:#1C66FF;
  --deep-navy:#0A1A33;
  --sky-gray:#EFEFF5;
  --soft-slate:#7F8DAA;
  --success:#20C997;
  --amber:#F5A623;

  --card-bg:#FFFFFF;
  --card-br:#E6E8EF;
  --text:#0A1A33;
  --muted:#43506B;

  --r12:12px;
  --r16:16px;
  --s8:8px;
  --s12:12px;
  --s16:16px;
  --s24:24px;
}

#sp-main-body{ padding: 10px 0; }

/* CHG: Premium page header (stable, calm, WCAG AA) */
.h1wrapper{
  max-width: 1100px;
  margin: 0 auto 14px;
  padding: 0 16px;
}
.h1outter{
  border: 1px solid var(--card-br);
  border-radius: var(--r16);
  background: linear-gradient(180deg, #FFFFFF 0%, #F7F8FC 100%);
  box-shadow: 0 8px 24px rgba(10,26,51,.06);
  padding: 14px 16px;
}
.h1wrapper .border{ display:none; } /* CHG: legacy border shim no longer needed */

h1.lotteryHeading{
  margin: 0;
  display:flex;
  align-items:center;
  gap: 10px;
  flex-wrap: wrap;
  font-size: clamp(20px, 2.2vw, 28px);
  line-height: 1.15;
  letter-spacing: .2px;
  color: var(--text);
}
h1.lotteryHeading span{
  font-weight: 900;
}
img.lottoMan{
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  display:block;
}
@media (max-width: 800px){
  .h1outter{ padding: 12px 14px; }
  img.lottoMan{ width: 30px; height: 30px; }
}

/* Calm spacing wrapper behavior */
.latestDraw{
  overflow:hidden;
  width:100%;
  max-width:560px;
  margin: 0 auto 40px;
  border: 1px solid var(--card-br);
  border-radius: var(--r16);
  background: var(--card-bg);
  box-shadow: 0 8px 24px rgba(10,26,51,.08);
}

/* Header band ? SKAI Deep Horizon */
h2.latestHeading{
  margin:0;
  padding: 14px 16px;
  font-size: 20px;
  line-height: 1.2;
  color: #FFFFFF;
  background: linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
  letter-spacing: .2px;
}

.result-wrapper{
  width:100%;
  padding: 14px 14px 16px;
  box-sizing: border-box;
  background: var(--card-bg);
}

/* CHG: Top row ? logo + date chips (stable, premium, responsive) */
.le-result-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 12px;
  flex-wrap:wrap;
  margin: 6px 0 10px;
}

img.lotteryLogo{
  display:block;
  max-height: 90px;
  width:auto;
  margin: 0;
}

/* Date chips */
.le-date-row{
  display:flex;
  gap: 10px;
  flex-wrap:wrap;
  justify-content:center;
}

.le-date-chip{
  border: 1px solid var(--card-br);
  border-radius: 12px;
  background: linear-gradient(180deg, #FFFFFF 0%, #F7F8FC 100%);
  padding: 10px 12px;
  min-width: 160px;
  text-align:center;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.2;
  box-shadow: 0 6px 14px rgba(10,26,51,.06);
}

.le-date-chip strong{
  display:block;
  margin-top: 6px;
  color: var(--text);
  font-weight: 800;
  letter-spacing: .2px;
}

@media (max-width: 520px){
  .le-date-chip{ min-width: 0; width: 100%; }
  .le-result-top{ justify-content:center; }
}

/* CHG: Premium ?meta row? (draw date / next draw / jackpot) */
.fsd-meta{
  display:flex;
  flex-wrap:wrap;
  gap: 10px;
  justify-content:center;
  align-items:stretch;
  margin: 10px 0 8px;

  /* CHG: Ensure chips occupy their own line inside .le-result-top */
  width:100%;
  flex: 1 1 100%;
}

.fsd-meta-item{
  min-width: 160px;
  border: 1px solid var(--card-br);
  border-radius: 12px;
  padding: 10px 12px;
  background: linear-gradient(180deg, #FFFFFF 0%, #F7F8FC 100%);
  text-align:center;
  box-shadow: 0 6px 14px rgba(10,26,51,.06);
}

.fsd-label{
  display:block;
  font-size: 12.5px;
  color: var(--muted);
  letter-spacing: .2px;
  line-height: 1.1;
}

.fsd-value{
  display:block;
  margin-top: 6px;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: .2px;
}

/* CHG: Mobile-first stacking (prevents cramped chips) */
@media (max-width: 520px){
  .fsd-meta{ gap: 8px; }
  .fsd-meta-item{ width: 100%; min-width: 0; }
}

/* CHG: Number ?pills? (consistent alignment across all games) */
.lstResult{ text-align:center; margin: 0; }
.circles,
.circlesPb{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  margin: 6px 6px 0 0;
  font-weight: 800;
  font-size: 15px;
  line-height: 1;
  border: 1px solid var(--card-br);
  background: #FFFFFF;
  color: var(--text);
  box-shadow: 0 6px 14px rgba(10,26,51,.08);
}
.circlesPb{
  border-color: rgba(28,102,255,.35);
  background: linear-gradient(180deg, #FFFFFF 0%, #EEF4FF 100%);
}

/* Jackpot block (if you still use fsd-nJackpot below) */
.fsd-nJackpot{
  width:100%;
  text-align:center;
  margin: 10px 0 0;
  color: var(--muted);
  font-size: 14px;
}
.fsd-nJackpot span{
  display:inline-block;
  margin-top: 4px;
  font-weight: 900;
  font-size: 18px;
  color: var(--text);
}

/* Results band: light, readable, no ?warning yellow? */
.fsd-dresults{
  display:block;
  width:100%;
  text-align:center;
  color: var(--text);
  font-weight: 600;
  font-size: 16px;
  background: linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
  margin: 10px 0 14px;
  border: 1px solid var(--card-br);
  border-radius: var(--r12);
  line-height: 1.25;
  padding: 14px 10px;
}
.fsd-dresults strong{
  font-size: 20px;
  font-weight: 800;
  letter-spacing: .8px;
}

/* Keep existing semantic labels, improve contrast */
span.pball{ color:#D23B2A; }
span.pplay{ color: var(--skai-blue); font-weight: 700; }

/* CHG: Results row ? premium number pills (stable sizing; WCAG AA contrast) */
p.lstResult{
  /* CHG: Use flex so pills always center + wrap evenly */
  display:flex;
  flex-wrap:wrap;
  justify-content:center;
  align-items:center;
  gap: 6px;
  text-align:center;
  margin: 10px 0 0;
  line-height: 1.2;
}

/* CHG: Remove per-pill right margin when flex gap is active */
p.lstResult > span.circles,
p.lstResult > span.circlesPb,
p.lstResult > span.circlesPb2,
p.lstResult > span.circlesPb3{
  margin: 0;
}

/* CHG: Anything that?s ?meta text? inside lstResult should always be its own line */
p.lstResult .le-result-meta{
  flex: 0 0 100%;
  display:block;
  margin-top: 10px;
}

/* Pills (main numbers) */
/* CHG: Make pills truly uniform (removes width drift from min-width + padding) */
span.circles,
span.circlesPb,
span.circlesPb2,
span.circlesPb3{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width: 40px;              /* CHG: fixed width */
  height: 40px;
  padding: 0;               /* CHG: remove padding that causes uneven pills */
  margin: 6px 6px 0 0;
  border-radius: 999px;
  border: 1px solid var(--card-br);
  background: #FFFFFF;
  color: var(--text);
  font-weight: 800;
  font-size: 15px;
  letter-spacing: .2px;
  vertical-align: middle;
  font-variant-numeric: tabular-nums; /* CHG: more even digit rhythm */
  box-shadow: 0 6px 14px rgba(10,26,51,.06);
}

/* Bonus balls (distinct but calm) */
span.circlesPb,
span.circlesPb2,
span.circlesPb3{
  border-color: rgba(28,102,255,.35);
  background: linear-gradient(180deg, rgba(28,102,255,.10) 0%, #FFFFFF 100%);
}

/* Label line under the balls (Power Play, MegaPlier, etc.) */
.le-result-meta{
  display:block;
  margin-top: 10px;
  color: var(--muted);
  font-weight: 700;
}
.le-result-meta .pplay{ color: var(--skai-blue); }

/* Keep coming-soon band as-is */
p.acsoon{
  background: #0A1A33;
  color: #FFFFFF;
  padding: 10px 12px;
  text-align:center;
  margin: 0;
  font-weight: 600;
}

/* Optional ?words? containers */
.lwords-inner,.fwords-inner{
  position:relative;
  max-width: 1000px;
  margin: 16px auto;
  padding: 16px;
  border: 1px solid var(--card-br);
  border-radius: var(--r16);
  background: #FFFFFF;
  box-shadow: 0 8px 24px rgba(10,26,51,.06);
}
.lwordIntro,.lwordDesc{
  max-height:0;
  overflow:hidden;
  transition: max-height .25s ease, margin .25s ease;
}
.lwords-inner h2{ cursor:pointer; }
.lwords-inner h2:after{
  content:"\27A4";
  position:absolute;
  right: 16px;
  top: 16px;
  transition: transform .2s ease;
}
.expandText{ max-height:5000px !important; margin: 12px 0 0; }
.lwords-inner h2.spinMe:after{ transform: rotate(90deg) !important; }

@media (max-width: 800px){
  h2.latestHeading{ font-size: 18px; }
  h1.lotteryHeading{ font-size: 18px; }
  img.lottoMan{ max-width: 32px; }
}

/* Accessibility helper */
.le-sr-only{
  position:absolute !important;
  width:1px; height:1px;
  padding:0; margin:-1px;
  overflow:hidden; clip:rect(0,0,0,0);
  white-space:nowrap; border:0;
}

/* CHG: Single-ball card alignment (Cash Pop / Pick 1 / Pop)
   Ensures result chip renders BELOW the date, not inline */
.le-card .circles,
.le-card .circlesPb,
.le-card .circlesSingle{
  display:block;
  margin: 8px auto 0;
}

/* Ensure date text does not wrap chips inline */
.le-card .last-result,
.le-card .lastResult,
.le-card .result-date{
  display:block;
  text-align:center;
}

/* Hide the entire "Latest Results" top row (logo + meta chips) */
.le-result-top{
  display:none !important;
}
p.lstResult {
  text-align: center;
  display: none;
}
</style>

<?php
/** DISPLAY TOP BUTTONS **/
echo JHtml::_('content.prepare', '{loadposition topLEHeader}');

	 
/** GET LOTTO WORDS FOR THE GAME **/
    /**
     * CHG: Harden DB access (Joomla query builder; no raw SQL interpolation).
     * NOTE: The rendering block below remains commented-out exactly as before (non-breaking).
     */
    $gaID = '';

    // CHG: Resolve game_id via DB wrapper helper (centralized quoting/binding)
    $gaID = leFetchScalar(
        $db,
        $dbCol,
        'game_id',
        ['game_name' => $gNameRaw],
        $db->quoteName('id') . ' DESC'
    );

    if ($gaID !== '') {
        $q2 = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__lottowords'))
            ->where($db->quoteName('game_id') . ' = ' . $db->quote($gaID))
            ->where($db->quoteName('statename') . ' = ' . $db->quote($stateAbrevRaw));

        $db->setQuery($q2);
        $lwords = (array) $db->loadObjectList();

        /** if (!empty($lwords)) {
            echo '<div class="lwords-wrapper">';
            echo '<div class="lwords-inner">';

            foreach ($lwords as $lword) {
                // CHG: Escape plain-text fields; prepare rich text via Joomla (safe plugin processing)
                $safeTitle = htmlspecialchars((string) ($lword->title ?? ''), ENT_QUOTES, 'UTF-8');

                $rawIntro = (string) ($lword->introtext ?? '');
                $rawDesc  = (string) ($lword->descriptiontext ?? '');

                $safeIntro = HTMLHelper::_('content.prepare', $rawIntro);
                $safeDesc  = HTMLHelper::_('content.prepare', $rawDesc);

                echo '<h2>' . $safeTitle . '</h2>';
                echo '<div class="lwordIntro">' . $safeIntro . '</div>';
                echo '<div class="lwordDesc">' . $safeDesc . '</div>';
            }
            ?>
<script>
jQuery(document).ready(function(){
    jQuery('.lwords-inner h2').click(function(e){
        e.preventDefault();
        e.stopPropagation();
        jQuery('.lwordIntro').toggleClass('expandText');
        jQuery('.lwordDesc').toggleClass('expandText');
        jQuery('.lwords-inner h2').toggleClass('spinMe');
    });
});
</script>

            <?php
            echo '</div>';
            echo '</div>';
        } **/
    }

?>



<?php
    // CHG: Ensure downstream logic never reads an undefined game id if no rows are returned
    $gId = '';

    // CHG: Prefer gmCode/game_id when present in the URL.
    // This is safer and more stable than resolving by game_name,
    // especially for Cash Pop and any game names with spacing/casing variations.
    $gmCodeRaw = strtoupper(trim((string) $input->getString('gmCode', '')));

    if ($gmCodeRaw !== '') {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('game_id') . ' = :gid')
            ->order($db->quoteName('draw_date') . ' DESC');

        $query->bind(':gid', $gmCodeRaw);

        $db->setQuery($query, 0, 1);
        $latestResult = (array) $db->loadObjectList();
    } else {
        // Fallback to game_name only if gmCode is not present
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('game_name') . ' = :gname')
            ->order($db->quoteName('draw_date') . ' DESC');

        $query->bind(':gname', $gNameRaw);

        $db->setQuery($query, 0, 1);
        $latestResult = (array) $db->loadObjectList();
    }

if (!empty($latestResult)) {

    // CHG: Track whether we actually output at least one latest result row
    $leHasLatestOutput = false;

        
        foreach($latestResult as $lr){
            $gId = $lr->game_id;
            $draw_date = $lr->draw_date;
            $draw_results = $lr->draw_results;
            $next_draw_date = $lr->next_draw_date;
            $next_jackpot = $lr->next_jackpot;
            /** NUMBER POSITIONS **/
            $posOne = $lr->first;
            $posTwo = $lr->second;
            $posThree = $lr->third;
            $posFour = $lr->fourth;
            $posFive = $lr->fifth;
            $posSix = $lr->sixth;
            $posSeven = $lr->seventh;
            $posEight = $lr->eighth;
            $posNine = $lr->nineth;
            $posTen = $lr->tenth;
            $posEleven = $lr->eleventh;
            $posTwelve = $lr->twelveth;
            $posThirteen = $lr->thirtheenth;
            $posFourteen = $lr->fourteenth;
            $posFifteen = $lr->fifteenth;
            $posSixteen = $lr->sixteenth;
            $posSeventeen = $lr->seventeenth;
            $posEighteen = $lr->eighteenth;
            $posNineteen = $lr->nineteenth;
            $posTwenty = $lr->twentieth;
            $posTwentyOne = $lr->twenty_first;
            $posTwentyTwo = $lr->twenty_second;
            $posTwentyThree = $lr->twenty_third;
            $posTwentyFour = $lr->twenty_fourth;
            $posTwentyFive = $lr->twenty_fifth;
            
            if(!empty($stateAbrev)){
                $imgState = '/'.strtolower($stateAbrev);
            }else{
                $imgState ='/fl';
            }
            
// CHG: Prevent duplicate "Latest Results" UI on SKAI digit pages
if (!$leSuppressLegacyTopResults) {
    echo '<div class="result-wrapper">';
}

            // CHG: Build filename from RAW game name (not HTML-escaped) to avoid broken paths
            $gameSlug = leFilenameSlug((string) $gNameRaw);

            // CHG: Safe logo fallback (prevents broken image icons if the per-game PNG is missing)
            $logoRel   = '/images/lottodb/us' . $imgState . '/' . $gameSlug . '.png';
            $logoAbs   = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $logoRel);
            $logoFinal = is_file($logoAbs) ? $logoRel : '/images/lottoexpert_logo-stacked.jpg';

// CHG: Wrap logo + meta chips in a stable flex row so chips don't sit awkwardly beside other elements.
echo '<div class="le-result-top">';

echo '<img src="' . htmlspecialchars($logoFinal, ENT_QUOTES, 'UTF-8') . '" class="lotteryLogo" alt="' . htmlspecialchars($stateName . ' ' . $gName, ENT_QUOTES, 'UTF-8') . '" width="260" height="90" loading="lazy" decoding="async">';

/* CHG: Premium meta row (reduces <br> stacks; cleaner UX; less layout jank) */
echo '<div class="fsd-meta" role="group" aria-label="Draw details">';
echo   '<div class="fsd-meta-item"><span class="fsd-label">Drawing date</span><span class="fsd-value">' . date('m-d-Y', strtotime((string) $draw_date)) . '</span></div>';
echo   '<div class="fsd-meta-item"><span class="fsd-label">Next drawing</span><span class="fsd-value">' . date('m-d-Y', strtotime((string) $next_draw_date)) . '</span></div>';

if ($next_jackpot !== '' && $next_jackpot > '0' && $next_jackpot !== 'n/a') {
    echo '<div class="fsd-meta-item"><span class="fsd-label">Next jackpot</span><span class="fsd-value">$' . number_format((float) $next_jackpot, 0, '.', ',') . '</span></div>';
}
echo '</div>';   // .fsd-meta
echo '</div>';   // .le-result-top

/** POWERBALL RESULTS **/
if ($gNameLogic === 'Powerball') {

// CHG: Cleaner layout (no double <br>; use meta line for Power Play)
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circlesPb">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>'
    . '<span class="le-result-meta" aria-label="Power Play">'
    . '<span class="pplay">Power Play:</span> ' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
    . '</span>';

    /** POWERBALL DOUBLE PLAY RESULTS jumbo *quick draw*/
    /** }else if($gName === 'Powerball Double Play nothing'){
        
        echo '<p class="lstResult"><br /><br /><span class="circles">'.$posOne.'</span><span class="circles">'.$posTwo.'</span><span class="circles">'.$posThree.'</span><span class="circles">'.$posFour.'</span><span class="circles">'.$posFive.'</span>&nbsp;&nbsp;<span class="circlesPb">'.$posSix.'</span></p>';
    **/
    
/** UK National EuroMillions RESULTS **/
} elseif ($gNameLogic === 'EuroMillions') {


// CHG: Cleaner layout (no double <br>; meta label becomes a stable line)
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>'
    . '<span class="le-result-meta" aria-label="Lucky Stars">'
    . '<span class="pplay">Lucky Stars:</span> '
    . '<span class="circlesPb">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circlesPb">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</span>';

/** MO Millions Main (MOH) and Double Play (MOI) - 6 main balls + Millions Ball **/
/** DB layout: first-sixth = 6 main numbers, seventh = Millions Ball **/
} elseif ($gId === 'MOH' || $gId === 'MOI') {
    // Prefer the dedicated seventh column; fall back to parsing draw_results if empty or default '0'
    $mohBonus = trim((string) $posSeven);
    if ($mohBonus === '' || $mohBonus === '0') {
        $_moRaw = trim((string) $draw_results);
        // First: explicit "Bonus: XX" label (handles "06-25-26-27-32-39, Bonus: 06" format)
        if (preg_match('/\bbonus[:\s]+(\d{1,2})\b/i', $_moRaw, $_moBm)) {
            $_moVal = (string) (int) $_moBm[1];
            $mohBonus = ($_moVal !== '0') ? $_moVal : '';
        }
        // Second: filter to numeric-only tokens and take 7th (index 6)
        if ($mohBonus === '') {
            $_moParts = array_values(array_filter(
                preg_split('/[\s,\-\.]+/', $_moRaw),
                static function ($t) { return is_numeric(trim($t)); }
            ));
            $_mo7 = isset($_moParts[6]) ? trim($_moParts[6]) : '';
            $mohBonus = ($_mo7 !== '' && $_mo7 !== '0') ? $_mo7 : '';
        }
    }
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne,   ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo,   ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFour,  ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFive,  ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posSix,   ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>';
if ($mohBonus !== '') {
    echo '<span class="le-result-meta" aria-label="Millions Ball">'
        . '<span class="pplay">Millions Ball:</span> '
        . '<span class="circlesPb">' . htmlspecialchars($mohBonus, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</span>';
}

                
                
                
                
                /** Delaware, IOWA, IDAHO, MAINE, Minnesota, Montana, North Dakota, New Mexico, Oklahoma, South Dakota, Tennessee, West-Virginia, Kansas Lotto America RESULTS**/
}else if(($gName === 'Lotto America' && $stateAbrev === 'de') || ($gName === 'Lotto America' && $stateAbrev === 'ia') || ($gName === 'Lotto America' && $stateAbrev === 'id') || ($gName === 'Lotto America' && $stateAbrev === 'me') || ($gName === 'Lotto America' && $stateAbrev === 'mn') || ($gName === 'Lotto America' && $stateAbrev === 'mt') || ($gName === 'Lotto America' && $stateAbrev === 'nd') || ($gName === 'Lotto America' && $stateAbrev === 'nm') || ($gName === 'Lotto America' && $stateAbrev === 'ok') || ($gName === 'Lotto America' && $stateAbrev === 'sd') || ($gName === 'Lotto America' && $stateAbrev === 'tn') || ($gName === 'Lotto America' && $stateAbrev === 'wv') || ($gName === 'Lotto America' && $stateAbrev === 'ks')){     
                
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Star Ball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Cash Ball RESULTS **/
                }else if($gName === 'Super Cash' && $stateAbrev === 'ks'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Cash Ball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Cash Ball RESULTS **/
                }else if($gName === 'Cash Ball' && $stateAbrev === 'ky'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Cash Ball:</span> '
                        . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Big Sky Bonus RESULTS **/
                }else if($gName === 'Big Sky Bonus' && $stateAbrev === 'mt'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . '<span class="circlesPb">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</span>'
                        . '</p>';
                
                /** New Jersey Cash 5 RESULTS **/
                }else if($gName === 'Cash 5' && $gId === 'NJ2' && $stateAbrev === 'nj'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Xtra:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
/** SuperLotto Plus RESULTS **/
                }else if($gName === 'SuperLotto Plus'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Mega Ball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
/** MEGA MILLIONS RESULTS **/
                }else if($gName === 'Mega Millions'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circlesPb">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Megaplier:</span> '
                        . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Bank a Million GAMES **/
                }else if(($gName === 'Bank a Million' && $stateAbrev === 'va')){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Cash4Life GAMES **/
                }else if($gName === 'Cash4Life'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Cash Ball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Lucky For Life GAMES **/
                }else if($gName === 'Lucky For Life'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Cash Ball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Millionaire for Life GAMES (5/58 + 1/5 Life Ball) **/
                }else if($gName === 'Millionaire for Life'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Life Ball:</span> '
                        . '<span class="circlesPb">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</span>'
                        . '</p>';
                
                /** LOTTO GAMES **/
                }else if(($gName === 'Lotto' && $stateName != 'Illinois' && $stateName != 'New York') || ($gName === 'Hoosier Lotto' && $stateName === 'Indiana')){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                
                /** LOTTO Double Play GAMES **/
                }else if($gName === 'Double Play' && $stateName != 'Illinois' && $stateName != 'New York'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                
                /** Illinois LOTTO GAMES **/
                }else if($gName === 'Lotto' && $stateName === 'Illinois'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Extra Shot:</span> '
                        . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
 
                 /** Arkansas LOTTO GAMES **/
                }else if($gId === 'AR2' || ($gName === 'LOTTO' && $stateName === 'Arkansas')){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
 
                
                /** Wild Money GAMES **/
                }else if($gName === 'Wild Money' && $stateName === 'Rhode Island'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Extra:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** Palmetto Cash 5 GAMES **/
                }else if($gName === 'Palmetto Cash 5' && $stateName === 'South Carolina'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Power-Up:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                
                /** New York LOTTO GAMES **/
                }else if($gName === 'Lotto' && $stateName === 'New York'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                    
                
                /** Bonus Match 5, Tennessee Cash GAMES Puerto Rico Loto Plus**/
                }else if(($gName === 'Bonus Match 5' && $stateName === 'Maryland') || ($gName === 'Tennessee Cash' && $stateName === 'Tennessee') || ($gName === 'Loto Plus' && $stateName === 'Puerto Rico') || ($gName === 'Pick 5 Midday' && $stateName === 'Florida') || ($gName === 'Pick 5 Evening' && $stateName === 'Florida') || ($gName === 'Thunderball' && $stateName === 'UK National')){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                    
                    /** Bonus Two Step GAMES **/
                }else if($gName === 'Texas Two Step' && $stateName === 'Texas'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
                        . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                    
                
                /** Megabucks Plus GAMES **/
                }else if(($gName === 'Megabucks Plus' && $stateName === 'Maine') || ($gName === 'Megabucks Plus' && $stateName === 'New Hampshire') || ($gName === 'Megabucks Plus' && $stateName === 'Vermont')){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">Megaball:</span> '
                        . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                    
                
   /** (STRAIGHT 2) DC 2 Midday, DC 2 Evening GAMES **/
                }else if($gName === 'DC 2 1:50PM' || $gName === 'DC 2 7:50PM'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                    
                
   /** (STRAIGHT 2 + plus bonus) PAG, FLF, PAH, FLE, GAMES **/
                }else if($gName === 'Pick 2 Day' || $gName === 'Pick 2 Evening' || $gName === 'Pick 2 Evening' || $gName === 'Pick 2 Midday'){
                    
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="le-result-meta"><span class="pplay">Bonus:</span> '
    . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8')
    . '</span>'
    . '</p>';
                    
                
                
                
                
                /** (STRAIGHT 3) Cash 3 Midday, Cash 3 Evening, Daily 3 Midday, Daily 3 Evening, Play3 Day, Play3 Night, DC 3 Midday, DC 3 Evening, Play 3 Day, Play 3 Night, Cash 3 Night, Evening 3 Double, MyDay, Cash 3 Morning, Daily Game, Daily 3 GAMES ** || $gName === 'Pick 3 Day' **/
                }else if($gName === 'Daily 3' || $gName === 'Pick 3' || $gName === 'Daily Game' || $gName === 'Pick 3 Day' || $gName === 'Pick 3 Night' || $gName === 'Pick 3 Evening' || $gName === 'Pick 3 Midday' || $gName === 'Pick 3' || $gName === 'Cash 3 Morning' || $gName === 'Cash 3 Midday' || $gName === 'Cash 3 Evening' || $gName === 'Daily 3 Midday' || $gName === 'Daily 3 Evening' || $gName === 'Play3 Day' || $gName === 'Play3 Night' || $gName === 'DC 3 1:50PM' || $gName === 'DC 3 11:30PM' || $gName === 'DC 3 7:50PM' || $gName === 'Play 3 Day' || $gName === 'Play 3 Night' || $gName === 'Cash 3 Night' || $gName === 'Evening 3 Double' || $gName === 'MyDay' || ($gName === 'Numbers Midday' && $stateName === 'New York') || ($gName === 'Numbers Evening' && $stateName === 'New York') || ($gName === 'Pick 3' && $stateName === 'Nebraska') || ($gName === 'Pick 3' && $stateName === 'Washington') || ($gName === 'Pick 3 Evening' && $stateName === 'Maine')){
                    
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>';
                    
                
                /** (STRAIGHT 4) Cash 4 Midday, Cash 4 Evening, Daily 4 GAMES, Play4 Day, DC 4 Midday, DC 4 Evening, Play 4 Day, Play 4 Night, Cash 4 Night, Daily 4 Midday, Daily 4 Evening, 2 By 2, Numbers Midday, Numbers Evening, Win 4 Midday, Win 4 Evening, Win for Life, Cash 4 Morning, Cash 4 Midday, Cash 4 Evening, Match 4 **/
                }else if($gName === 'Cash 4 Midday' || $gName === 'Cash 4 Evening' || $gName === 'Daily 4' || $gName === 'Play4 Day' || $gName === 'Play4 Night' || $gName === 'DC 4 1:50PM' || $gName === 'DC 4 7:50PM' || $gName === 'DC 4 11:30PM' || $gName === 'Play 4 Day' || $gName === 'Play 4 Night' || $gName === 'Cash 4 Night' || $gName === 'Daily 4 Midday' || $gName === 'Daily 4 Evening' || $gName === '2 By 2' || ($gName === 'Numbers Midday' && $stateName != 'New York') || ($gName === 'Numbers Evening' && $stateName != 'New York') || $gName === 'Win 4 Midday' || $gName === 'Win 4 Evening' || ($gName === 'Win for Life' && $gId === 'OR2') || $gName === 'Cash 4 Morning' || $gName === 'Cash 4 Midday' || $gName === 'Cash 4 Evening' || $gName === 'Match 4' || $gName === 'Daily 4 Morning' || $gName === 'Daily 4 Day' || $gName === 'Daily 4 Evening' || $gName === 'Daily 4 Night' || $gName === 'Pick 4' || $gName === 'Pick 4 Day' || $gName === 'Pick 4 Evening' || $gName === 'Pick 4 Night' || $gName === 'Pick 4 Night' || $gName === 'Pick 4 Midday' || $gName === 'Pick 4 1PM' || $gName === 'Pick 4 4PM' || $gName === 'Pick 4 7PM' || $gName === 'Pick 4 10PM' || $gName === 'Pick 4 Evening'){
                    
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>';
                    
                
                /** (STRAIGHT 5) Fantasy 5, Gopher 5, Hit 5, five Monus MAtch 5, Gimme 5, Kentucky 5, Rolling Cash,  Natural State Jackpot, Daily Tennessee Jackpot, Cash 5, DC 5 Midday, DC 5 Evening, Georgia FIVE Midday, Georgia FIVE Evening, Idaho Cash, 5 Star Draw, Weekly Grand, LuckyDay Lotto Midday, LuckyDay Lotto Evening, Easy 5, MassCash, Gimme 5, World Poker Tour, Poker Lotto, Gopher 5, NORTH5, Show Me Cash, Montana Cash, Roadrunner Cash, Take 5 Midday, Take 5 Evening, Rolling Cash 5, Treasure Hunt, Dakota Cash, Hit 5, Badger 5 Cowboy Draw GAMES **/
                }else if($gName === 'Fantasy 5' || $gName === 'Fantasy 5 Evening' || $gName === 'Fantasy 5 Midday' || $gName === 'Gopher 5' || $gName === 'Hit 5' || $gName === 'Rolling Cash 5' || $gName === 'Kentucky 5' || $gName === 'Bonus Match 5' || $gName === 'Natural State Jackpot' || ($gName === 'Pick 5' && $stateAbrev === 'ne') || $gName === 'Daily Tennessee Jackpot' || ($gName === 'Cash 5' && $stateAbrev != 'nj')  || $gName === 'Cash Five'   || ($gName === 'Cash 5' && $stateAbrev != 'nc') || $gName === 'Idaho Cash' || $gName === '5 Star Draw' || $gName === 'Weekly Grand' || $gName === 'LuckyDay Lotto Midday' || $gName === 'LuckyDay Lotto Evening' || $gName === 'Easy 5' || $gName === 'MassCash' || $gName === 'Gimme 5' || $gName === 'World Poker Tour' || $gName === 'Poker Lotto' || $gName === 'Gopher 5' || $gName === 'NORTH5' || $gName === 'Show Me Cash' || $gName === 'Montana Cash' || $gName === 'Roadrunner Cash' || $gName === 'Take 5 Midday' || $gName === 'Take 5 Evening' || $gName === 'Rolling Cash 5' || $gName === 'Treasure Hunt' || $gName === 'Dakota Cash' || $gName === 'Hit 5' || $gName === 'Badger 5' || $gName === 'Cowboy Draw' || $gName === 'Play 5 Day' || ($gName === 'Pick 5' && $stateAbrev === 'fl') || $gName === 'Match 5' || $gName === 'DC 5 7:50PM' || $gName === 'DC 5 1:50PM' || $gName === 'Pick 5' || $gName === 'Pick 5 Evening' || $gName === 'Pick 5 Midday' || $gName === 'Pick 5 Evening' || $gName === 'Georgia FIVE Evening' || $gName === 'Georgia FIVE Midday'){
                    
echo '<p class="lstResult">'
    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
    . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
    . '</p>';
                   /** NJ Double Play, IN Hoosier Lotto, NJ Pick 6 Lotto **/
                
                /** (STRAIGHT 6) Jackpot Triple Play, Double PlayDCE, Hoosier Lotto, NJ Pick 6 Lotto, Triple Twist, Lotto Plus, Multi-Win Lotto, Jumbo Bucks Lotto, Megabucks Doubler, MultiMatch, Classic Lotto 47, Classic Lotto, Megabucks, Match 6 Lotto, Super Cash, Cash 25, GAMES **/
                }else if($gName === 'Jackpot Triple Play' || $gName === 'Hoosier Lotto' || $gName === 'Pick 6 Lotto' || $gName === 'Double Play' || $gName === 'Cash 25' || $gName === 'Triple Twist' || $gName === 'Lotto Plus' || $gName === 'Lotto Texas' || $gName === 'Multi-Win Lotto' || /** $gName === 'Jumbo Bucks Lotto' || **/ $gName === 'Megabucks Doubler' || $gName === 'MultiMatch' || $gName === 'Classic Lotto 47' || $gName === 'Classic Lotto' || $gName === 'The Pick' || $gName === 'Megabucks' || $gName === 'Match 6 Lotto' || $gName === 'Super Cash'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                    
                
                /** (STRAIGHT 8) Lucky Lines GAMES **/
                }else if($gName === 'Lucky Lines'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                    
 
               
                /** ALL All or Nothing Midday, All or Nothing Evening GAMES **/
                     }else if($gId === 'WI8' || $gId === 'WI7'){
                                       
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNine, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEleven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
                   
                
                /** ALL (TX) All or Nothing Midday, All or Nothing Evening GAMES **/
                   }else if($gId === 'TXF' || $gId === 'TXG' || $gId === 'TXH' || $gId === 'TXI'){
                    
                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNine, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEleven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwelve, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
  
  
                
                /** New York Pick 10 GAME **/
                }else if(($gName === 'Pick 10') || ($gName === 'Keno' && $stateName === 'Michigan')){

                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>' /* CHG: was missing circles */
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNine, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEleven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwelve, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThirteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFourteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFifteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSixteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeventeen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEighteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNineteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwenty, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</p>';
               
               
                /** ALL Indiana Quick Draw Midday, Quick Draw Evening GAMES **/
                }else if(($gName === 'Quick Draw Midday') || ($gName === 'Quick Draw Evening') || ($gName === 'Keno' && $stateName === 'Washington')){

                    echo '<p class="lstResult">'
                        . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNine, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEleven, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwelve, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posThirteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFourteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posFifteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSixteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posSeventeen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posEighteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posNineteen, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="circles">' . htmlspecialchars((string) $posTwenty, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '<span class="le-result-meta"><span class="pplay">BE:</span> '
                        . htmlspecialchars((string) $posTwentyOne, ENT_QUOTES, 'UTF-8')
                        . '</span>'
                        . '</p>';
                    
                /** MICHIGAN KENO **/
            }else if(($gName === 'Keno' && $stateName === 'Michigan')){

                echo '<p class="lstResult">'
                    . '<span class="circles">' . htmlspecialchars((string) $posOne, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posThree, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posFour, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posFive, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posSix, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posSeven, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posEight, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posNine, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posEleven, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTwelve, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posThirteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posFourteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posFifteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posSixteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posSeventeen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posEighteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posNineteen, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTwenty, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTwentyOne, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="circles">' . htmlspecialchars((string) $posTwentyTwo, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '</p>';

/** OUTPUT ALL OTHERS **/
}else if (preg_match('/^(Cash\s*Pop|Pop|Pick\s*1)$/i', (string) $gNameRaw)) { // CHG: use raw (unescaped) name for logic

    // Single-ball: render strictly from draw_results to preserve any leading zero
    $num = trim((string)$draw_results);
    $num = preg_replace('/\D+/', '', $num); // e.g., "03 " -> "03"
    echo '<div class="fsd-dresults"><strong><span class="circles">' 
        . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') 
        . '</span></strong></div>';

}else{
    if ($gId !== 'AR2') { /** EXCLUDE AR LOTTO **/
        // Split on -, comma, space, or dot to avoid joined balls
        $parts = preg_split('/\s*[-,\s\.]\s*/', trim((string)$draw_results));
        $parts = array_filter($parts, 'strlen');
        $out = '<span class="circles">' . implode(
            '</span><span class="circles">',
            array_map(function($p){ return htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); }, $parts)
        ) . '</span>';

        echo '<div class="fsd-dresults"><strong>' . $out . '</strong></div>';

    } else {
        echo '<div class="fsd-dresults"><strong>' 
            . htmlspecialchars($draw_results, ENT_QUOTES, 'UTF-8') 
            . '</strong></div>';
    }
}

            
/* CHG: Moved into fsd-meta row above (prevents duplicate UI) */
            
// CHG: We reached a valid rendered result row
$leHasLatestOutput = true;

echo '</div>';
} /** EO foreach **/

        
   } /** EO if (!empty($latestResult)) **/
        

/** ************************* COMING SOON BUTTON ******************************** **/
/* CHG: Show ONLY when latest results are empty (no rendered rows) */
if (
    empty($leHasLatestOutput)
    && (/**$gId != '101D' && **/$gId != '134'
        && $gId != 'FL1'
        && $gId != 'FLZ'
        && $gId != 'FL3'
        && $gId != 'FL7'
        && $gId != 'FL6'
        && $gId != 'NY1'
        && $gId != 'NY5'
        && $gId != 'NY2')
) {
    echo '<p class="acsoon">Detail Analysis </p>';
}

echo '</div>'; /** EO latestDraw DIV **/

        
        /** ************************* ANALYSIS AND HISTORICAL DATA *************************** **/



            /** UK National Lotteries **/
            /** EuroMillions **/
if ($gId === '801') {
    // CHG: Joomla-safe redirect; avoids "headers already sent" failures
    $app->redirect('https://lottoexpert.net/euromillions');
    $app->close();
}
        /** UK National UK4 Thunderball **/
        if($gId === 'UK4'){ 
            $showPageHeader = false;
            include "anhisUKL.php";
    }
    /** UK National UKL UKT Thunderball Lotto**/
        else if($gId === 'UKL' || $gId === 'UKT' || $gId === 'UK1' || $gId === 'UKH' || $gId === 'IE6' || $gId === 'IE4' || $gId === 'IE7' || $gId === 'IE5' || $gId === 'IEP' || $gId === 'IE0' || $gId === 'IE1' || $gId === 'IE2'){
            $showPageHeader = false;
            include "anhisUKL.php";
        }
     
 
        /** CASH4LIFE 60 numbers + 4 for CASH BALL **/
        else if($gId === '134'){ 
            include "anhiscashforlife.php";
        /** STRAIGHT 2+2 with 26 numbers - ND 2by2  **/
        }else if($gId === '114'){ 
            include "anhis114.php";
        /** STRAIGHT 20 with 80 numbers - NY Pick 10  **/
        }else if($gId === 'NY3'){ 
            include "anhisNY3.php";
        /** STRAIGHT 20 with 80 numbers - NY Pick 10  **/
        }else if($gId === 'WA4'|| $gId === 'IN7' || $gId === 'IN9' ){ 
            include "anhisWA4.php";        /** STRAIGHT 20 with 80 numbers - WA Keno  **/
        }else if($gId === 'MI3'){ 
            include "anhisKeno.php";  
        /** STRAIGHT 11 with 22 numbers - Wisconsin All or Nothing  **/
        }else if($gId === 'WI7' || $gId === 'WI8'){ 
            include "anhisWI8.php";
        /** STRAIGHT 12 with 24 numbers - Texas All or Nothing  **/
        }else if($gId === 'TXG' || $gId === 'TXH' || $gId === 'TXF' || $gId === 'TXI'){ 
            include "anhisTXG.php";
        /** STRAIGHT 8 with 32 numbers - Lucky Lines  **/
        }else if($gId === 'OR4'){ 
            include "anhisOR4.php";
        /** STRAIGHT 6 with 53 numbers - FLORIDA,   **/
        }else if($gId === 'FL1'){ 
            include "anhislotto.php";
        /** STRAIGHT 6 with 59 numbers - NY Lotto,   **/
        }else if($gId === 'NY1'){ 
            include "anhislottoNY.php";
            
            
        /** Straight 6+2 with 50+25 numbers - IL Lotto Extra Shots **/
        }else if($gId === 'IL4'){ 
            include "anhisIL4.php";
        /** Straight 6+1 with 40+40 numbers - Arkansas - Lotto **/
        }else if($gId === 'AR2'){
            include "anhisAR2.php";
        /** Straight 4+1 with 35 and Cash Ball 25 numbers - KY Cash Ball **/
        }else if($gId === 'KY1'){ 
            include "anhisKY1.php";            
        /** Straight 4+1 with 31 and Bonus Ball 16 numbers - Montana MT Big Sky **/
        }else if($gId === 'MT4'){ 
            include "anhisMT4.php";    
            
        /** Straight 4+1 with 34 and Bonus Ball 35 numbers - Texas Two Step **/
        }else if($gId === 'TX3'){ 
            include "anhisTX3.php";   
                       
        /** Straight 5+1 with 39 numbers - MD Bonus Match 5 **/
        }else if($gId === 'MD3'){ 
            include "anhisMD3.php";
        /** Straight 5+1 with 52+10 numbers - ID Lotto of America **/
        }else if($gId === '135'){ 
            include "anhislottoUSA.php";
        /** Straight 5+1 with 41+6 numbers - ID Megabucks Plus **/
        }else if($gId === '128'){ 
            include "anhis128.php";
        /** Straight 6 with 25 numbers - WV Cash 25 **/
        }else if($gId === 'WV1'){ 
            include "anhisWV1.php";
        /** Straight 6 with 35 numbers - DE Multi-Win Lotto **/
        }else if($gId === 'DE2'){ 
            include "anhisDE2.php";
        /** Straight 6 with 38 numbers - Need to Choose a different file that can accomodate the bonus number - but will leave for now because it shows the main history - RI Wild Money Lotto **/
        }else if($gId === 'RI2'){ 
            include "anhisRI2.php";
        /** Straight 5 with 36 numbers - FLORIDA Fantasy 5 **/
        }else if($gId === 'FL3'){ 
            include "anhisFLFantasy5.php";
                /** Straight 5 with 36 numbers - FLORIDA Fantasy 5 **/
        }else if($gId === 'FL7'){ 
            include "anhisFL7.php";    
        /** Straight 6 with 46 numbers - FLORIDA Jackpot Triple Play **/
        }else if($gId === 'FL6'){ 
            include "anhisFL6.php";
        /** Straight 6 with 39 numbers - WI SuperCash **/
        }else if($gId === 'WI2'){ 
            include "anhisWI2.php";
        /** Straight 6 with 40 numbers - CO Lotto, Lotto Plus **/
        }else if($gId === 'CO4' || $gId === 'CO5'){ 
            include "anhisCO4.php";
        /** Straight 6 with 42 numbers - LA Lotto **/
        }else if($gId === 'LA1'){ 
            include "anhisLA1.php";
        /** Straight 6 with 43 numbers - MD Multi-Match **/
        }else if($gId === 'MD4'){ 
            include "anhisMD4.php";
        /** Straight 6 with 44 numbers - CT Lotto **/
        }else if($gId === 'CT1'){ 
            include "anhisCT1.php";
        /** Straight 6 with 44 numbers - The Pick - AZ  **/
        }else if($gId === 'AZ3'){ 
            include "anhisAZ3.php";
        /** Straight 6 with 44 numbers - MO Lotto / MO Millions Main / MO Millions Double Play  **/
        }else if($gId === 'MO1' || $gId === 'MOH' || $gId === 'MOI'){ 
            include "anhisMO1.php";
        /** Straight 6 with 46 numbers - NJ Double Play, IN Hoosier Lotto, NJ Pick 6 Lotto **/
        }else if($gId === 'NJ7'|| $gId === 'IN1' || $gId === 'NJ6' ){ 
            include "anhisNJ7.php";
        /** Straight 6 with 47 numbers - GA Jumbo Bucks Lotto **/
        }else if($gId === 'MI7'){ 
            include "anhisGA1.php";
        /** Straight 6 with 48 numbers - OR Megabucks **/
        }else if($gId === 'OR1'){ 
            include "anhisOR1.php";
        /** Straight 6 with 49 numbers - GA Jumbo Bucks Lotto **/
        }else if($gId === 'PA3' || $gId === 'OH5' || $gId === 'WA1' || $gId === 'WI1' || $gId === 'MA2'){ 
            include "anhisPA3.php";
         /** Straight 6 with 54 numbers - CT Lotto  **/
        }else if($gId === 'TX1'){ 
            include "anhisTX1.php";      
        /** Straight 6 with 40 numbers + Bonus Virginia Bank a Million  **/
        }else if($gId === 'VA3'){ 
            include "anhisVA3.php";        
        /** STRAIGHT 5 with 30 numbers - PA Treasure Hunt **/
        }else if($gId === 'PA6'){ 
            include "anhisPA6.php";
        /** STRAIGHT 5 with 31 numbers - ID Badger 5, MN North 5 **/
        }else if($gId === 'WI5' || $gId === 'MN2'){
            include "anhisWI5.php";
        /** STRAIGHT 5 with 32 numbers - ID Weekly Grand, CO Cash 5 **/
        }else if($gId === 'ID3' || $gId === 'CO2'){ 
            include "anhisID3.php";
        /** STRAIGHT 5 with 35 numbers - MS Match 5 **/
        }else if($gId === 'MS5' || $gId === 'TX2' || $gId === 'CT2' || $gId === 'SD1' || $gId === 'MA3'){      
            include "anhisMS5.php";
        /** STRAIGHT 5 with 36 numbers - OK Cash 5 **/
        }else if($gId === 'OK1'){ 
            include "anhisOK1.php";
        /** STRAIGHT 5 with 37 numbers - NM Roadrunner, LA Easy 5 **/
        }else if($gId === 'NM2' || $gId === 'LA5'){ 
            include "anhisNM2.php";
        /** STRAIGHT 5 with 38 numbers - NM Roadrunner, LA Easy 5 - Daily Tennessee Jackpot **/
        }else if($gId === 'TN5'){
            include "anhisTN5.php";
        /** STRAIGHT 5 with 40 numbers -  **/
        }else if($gId === 'NE1'){ 
            include "anhisNE1.php";
            
        
        /** STRAIGHT 5 with 35 numbers Bonus 10 **/
        }else if($gId === 'PR3'){ 
            include "anhisPR3.php";    
            
            
            
        /** STRAIGHT 5 with 39 numbers - Take 5 Midday & Evening, MI Fantasy 5, MO Show me cash **/
        }else if($gId === 'NY5' || $gId === 'NY2' || $gId === 'MI6' || $gId === '133' || $gId === 'KY6' || $gId === 'OH3' || $gId === 'MO4'){ 
            include "anhisNYtake5.php";
        /** STRAIGHT 5 with 42 numbers -  **/
        }else if($gId === 'WA6'){ 
            include "anhisWA6.php";
        /** STRAIGHT 5 with 43 numbers - NC Cash 5 & Double Play   **/
        }else if($gId === 'PA2' || $gId === 'NC1' || $gId === 'NC1D'){ 
            include "anhisPA2.php";
        /** STRAIGHT 5 with 45 numbers - ID Cash, ID 5 Star Draw, IN Cash 5, NJ Cash 5, WY Cowboy Draw, MT Montana Cash **/
        }else if($gId === 'ID4' || $gId === 'ID5' || $gId === 'IN8' || $gId === 'NJ2' || $gId === 'WY1' || $gId === 'MT1' || $gId === 'IL5' || $gId === 'IL3' || $gId === 'VA2'){ 
            include "anhisID4.php";
        /** STRAIGHT 5 with 47 numbers -  **/
        }else if($gId === 'MN3'){ 
            include "anhisMN3.php";
        /** STRAIGHT 5 with 0-9 digits + Fireball/Wild Ball/EZ Match Extra — FL Pick 5 Evening/Midday, PA Pick 5 Evening/Day, VA Pick 5 Evening/Day **/
        }else if($gId === 'FLH' || $gId === 'FLHF' || $gId === 'FLG' || $gId === 'FLGF' || $gId === 'PAF' || $gId === 'PAFW' || $gId === 'PAG' || $gId === 'PAEW' || $gId === 'VAE' || $gId === 'VAEF' || $gId === 'VAF' || $gId === 'VAFF'){
            $showPageHeader = false;
            include "anhisWin5extra.php";

        /** STRAIGHT 5 with 0-9 numbers - GA FIVE MIDDAY & EVENING **/
        }else if($gId === 'GAE' || $gId === 'GAF' || $gId === 'DCF' || $gId === 'DCE' || $gId === 'LAC' || $gId === 'MDF' || $gId === 'MDE' || $gId === 'OHG'/**
  *  || $gId === 'FLH' || $gId === 'FLG'
  */ || $gId === 'OHF'){
            include "anhisGA5.php";            
        /** STRAIGHT 4 with 24 numbers WA7 - **/
        }else if($gId === 'WA7'){ 
            include "anhisWA7.php";  
        /** STRAIGHT 4 with 77 numbers Win for Life (OR2) - **/
        }else if($gId === 'OR2'){ 
            include "anhisOR2.php"; 
        /** STRAIGHT 4 with 0-9 numbers - NY WIN 4 MIDDAY & EVENING, AR CASH4 MIDDAY & EVENING, CA Daily 4, GA Cash 4, MI Daily 4 evening and midday **/
        }else if($gId === 'NYC' || $gId === 'NYD' || $gId === 'DCJ' || $gId === 'ARD' || $gId === 'ARC' || $gId === 'CAB' || $gId === 'GAC' || $gId === 'GAD' || $gId === 'GAH' || $gId === 'MIC' || $gId === 'MID' || $gId === '110' || $gId === '116' || $gId === 'DCC' || $gId === 'DCD' || $gId === 'DEC' || $gId === 'DED' || $gId === 'IAC' || $gId === 'IAD' || $gId === 'IDC' || $gId === 'IDD' || $gId === 'KYC' || $gId === 'KYD' || $gId === 'LAB' || $gId === 'MAA' || $gId === 'MAC' || $gId === 'MDC' || $gId === 'MDD' || $gId === 'MOC' || $gId === 'MOD' || $gId === 'NME' || $gId === 'NMF' || $gId === 'OHC' || $gId === 'OHD' || $gId === 'ORD' || $gId === 'ORE' || $gId === 'ORF' || $gId === 'ORG' || $gId === 'RIC' || $gId === 'RID' || $gId === 'WIC' || $gId === 'WID' || $gId === 'WVC'){ 
            include "anhisWin4.php";
        /** STRAIGHT 3 with 0-9 numbers - NY NUMBERS MIDDAY & EVENING, ARKANSAS CASH 3 MIDDAY & EVENING, CA Daily 3, GA CASH 3 MIDDAY-NIGHT & EVENING, MI DAILY 3 EVE AND MID **/
        }else if($gId === 'NYA' || $gId === 'NYB' || $gId === 'ARB' || $gId === 'DCA' || $gId === 'DCB' || $gId === '115' || $gId === 'AZA' || $gId === 'COA' || $gId === 'COB' || $gId === 'DEA' || $gId === 'DEB' || $gId === 'IAA' || $gId === 'IAB' || $gId === 'IDA' || $gId === 'IDB' || $gId === 'KSA' || $gId === 'KSB' || $gId === 'KYA' || $gId === 'KYB' || $gId === 'LAA' || $gId === 'MDA' || $gId === 'MDB' || $gId === 'MNA' || $gId === 'MOA' || $gId === 'MOB' || $gId === 'NEA' || $gId === 'NMA' || $gId === 'NMB' || $gId === 'OHA' || $gId === 'OHB' || $gId === 'OKA' || $gId === 'WIA' || $gId === 'WIB' || $gId === 'WVA' || $gId === 'ARA' || $gId === 'CAC' || $gId === 'CAA' || $gId === 'GAA' || $gId === 'GAB' || $gId === 'GAG' || $gId === 'MIA' || $gId === '109' || $gId === 'MIB' || $gId === 'WAA'){ 
            include "anhisNYNumbers.php";


        /** CASH POP 1-15 numbers **/
        }else if($gId === 'FLM' || $gId === 'FLN' || $gId === 'FLO' || $gId === 'FLP' || $gId === 'FLQ' || $gId === 'GAM' || $gId === 'GAN' || $gId === 'GAO' || $gId === 'GAP' || $gId === 'GAQ' || $gId === 'INM' || $gId === 'INN' || $gId === 'INO' || $gId === 'INK' || $gId === 'INQ' || $gId === 'MEE' || $gId === 'MEB' || $gId === 'MEM' || $gId === 'MES' || $gId === 'MEN' || $gId === 'MDM' || $gId === 'MDN' || $gId === 'MDO' || $gId === 'MDP' || $gId === 'MSM' || $gId === 'MSP' || $gId === 'MOM' || $gId === 'MON' || $gId === 'MOO' || $gId === 'MOP' || $gId === 'MOQ' || $gId === 'NCM' || $gId === 'NCN' || $gId === 'NCO' || $gId === 'NCP' || $gId === 'NCQ' || $gId === 'NJX' || $gId === 'PAM' || $gId === 'PAN' || $gId === 'PAO' || $gId === 'PAP' || $gId === 'SCM' || $gId === 'SCP' || $gId === 'VAM' || $gId === 'VAN' || $gId === 'VAO' || $gId === 'VAP' || $gId === 'VAQ' || $gId === 'WAM'){
            include "anhisCashPop.php";
   


            
            /** Oscar Implemented STRAIGHT 3 with 0-9 numbers With Extra Ball Test **/
        }else if($gId === 'CTA' || $gId === 'CTB' || $gId === 'CTAW' || $gId === 'CTBW' || $gId === 'FLA' || $gId === 'FLAF' || $gId === 'FLC' || $gId === 'FLCF' || $gId === 'ILG' || $gId === 'ILH' || $gId === '120' || $gId === '121' || $gId === 'INA' || $gId === 'INAF' || $gId === 'INB' || $gId === 'INBF' || $gId === 'MSA' || $gId === 'MSAF' || $gId === 'MSB' || $gId === 'MSBF' || $gId === 'NCA' || $gId === 'NCAF' || $gId === 'NCB' || $gId === 'NCBF' || $gId === 'NJA' || $gId === 'NJAF' || $gId === 'NJB' || $gId === 'NJBF' || $gId === 'PAA' || $gId === 'PAAW' || $gId === 'PAB' || $gId === 'PABW' || $gId === 'SCA' || $gId === 'SCAF' || $gId === 'SCB' || $gId === 'SCBF' || $gId === 'TNA' || $gId === 'TNAW' || $gId === 'TNC' || $gId === 'TNCW' || $gId === 'TNE' || $gId === 'TNEW' || $gId === 'TXA' || $gId === 'TXAF' || $gId === 'TXC' || $gId === 'TXCF' || $gId === 'TXJ' || $gId === 'TXJF' || $gId === 'TXK' || $gId === 'TXKF' || $gId === 'VAA' || $gId === 'VAAF' || $gId === 'VAB' || $gId === 'VABF'){

            /**
             * CHG: Use DB wrapper helpers (leFetchScalar / leFetchDrawResultsByDate)
             * - Same output, reduced SQL injection surface, removes JFactory usage here
             */
            $pb = ''; // default if not found
            $drawYmd = date('Y-m-d', strtotime((string) $draw_date));

            // Most cases are simple "first" pulls from a paired game_id
            $pbFirstMap = [
                'CTA' => 'CTAW',
                'CTB' => 'CTBW',
                'FLA' => 'FLAF',
                'FLC' => 'FLCF',
                '120' => 'ILG',
                '121' => 'ILH',
                'INA' => 'INAF',
                'MSA' => 'MSAF',
                'MSB' => 'MSBF',
                'NJA' => 'NJAF',
                'NJB' => 'NJBF',
                'PAA' => 'PAAW',
                'PAB' => 'PABW',
                'SCA' => 'SCAF',
                'SCB' => 'SCBF',
                'TNA' => 'TNAW',
                'TNC' => 'TNCW',
                'TNE' => 'TNEW',
                'TXA' => 'TXAF',
                'TXC' => 'TXCF',
                'TXJ' => 'TXJF',
                'TXK' => 'TXKF',
                'VAA' => 'VAAF',
                'VAB' => 'VABF',
            ];

                       if (isset($pbFirstMap[$gId])) {
                // CHG: Pull the companion value for the SAME draw date (fixes mismatched ?other lottery? values)
                $pb = leFetchColumnByDate($db, $dbCol, 'first', $pbFirstMap[$gId], $drawYmd, $stateNameRaw);
            } else if ($gId === 'INB') {

                // Pull from draw_results by date + stateprov_name (preserve leading zeros by using draw_results)
                $pbRaw = leFetchDrawResultsByDate($db, $dbCol, 'INBF', $drawYmd, $stateNameRaw);
                $pb    = preg_replace('/\D+/', '', (string) $pbRaw);
            } else if ($gId === 'NCA') {
                // Pull from draw_results by date + stateprov_name (preserve leading zeros)
                $pbRaw = leFetchDrawResultsByDate($db, $dbCol, 'NCAF', $drawYmd, $stateNameRaw);
                $pb    = preg_replace('/\D+/', '', (string) $pbRaw);
            } else if ($gId === 'NCB') {
                // Pull from draw_results by date + stateprov_name (preserve leading zeros)
                $pbRaw = leFetchDrawResultsByDate($db, $dbCol, 'NCBF', $drawYmd, $stateNameRaw);
                $pb    = preg_replace('/\D+/', '', (string) $pbRaw);
            }

            include "anhisWin3xtra.php";
            
                /** Oscar Implemented STRAIGHT 4 with 0-9 numbers With Extra Ball Test **/
        }else if($gId === 'CTC' || $gId === 'CTCW' || $gId === 'CTD' || $gId === 'CTDW' || $gId === 'FLB' || $gId === 'FLBF' || $gId === 'FLD' || $gId === 'FLDF' || $gId === '122' || $gId === 'ILI' || $gId === '123' || $gId === 'ILJ' || $gId === 'INC' || $gId === 'INCF' || $gId === 'IND' || $gId === 'INDF' || $gId === 'MSC' || $gId === 'MSCF' || $gId === 'MSD' || $gId === 'MSDF' || $gId === 'NCC' || $gId === 'NCCF' || $gId === 'NCD' || $gId === 'NCDF' || $gId === 'NJC' || $gId === 'NJCF' || $gId === 'NJD' || $gId === 'NJDF' || $gId === 'PAC' || $gId === 'PACW' || $gId === 'PAD' || $gId === 'PADW' || $gId === 'SCC' || $gId === 'SCCF' || $gId === 'SCD' || $gId === 'SCDF' || $gId === 'TNB' || $gId === 'TNBW' || $gId === 'TND' || $gId === 'TNDW' || $gId === 'TNF' || $gId === 'TNFW' || $gId === 'TXB' || $gId === 'TXBF' || $gId === 'TXD' || $gId === 'TXDF' || $gId === 'TXL' || $gId === 'TXLF' || $gId === 'TXM' || $gId === 'TXMF' || $gId === 'VAC' || $gId === 'VACF' || $gId === 'VAD' || $gId === 'VADF'){

            /**
             * CHG: Use DB wrapper helpers (leFetchScalar / leFetchDrawResultsByDate)
             * - Same output, reduced SQL injection surface, removes JFactory usage here
             */
            $pb = ''; // default if not found
            $drawYmd = date('Y-m-d', strtotime((string) $draw_date));

            // Most cases are simple "first" pulls from a paired game_id
            $pbFirstMap = [
                'CTC' => 'CTCW',
                'CTD' => 'CTDW',
                'FLB' => 'FLBF',
                'FLD' => 'FLDF',
                '122' => 'ILI',
                '123' => 'ILJ',
                'INC' => 'INCF',
                'IND' => 'INDF',
                'MSC' => 'MSCF',
                'MSD' => 'MSDF',
                'NCC' => 'NCCF',
                'NJC' => 'NJCF',
                'NJD' => 'NJDF',
                'PAC' => 'PACW',
                'PAD' => 'PADW',
                'SCC' => 'SCCF',
                'SCD' => 'SCDF',
                'TNB' => 'TNBW',
                'TND' => 'TNDW',
                'TNF' => 'TNFW',
                'TXB' => 'TXBF',
                'TXD' => 'TXDF',
                'TXL' => 'TXLF',
                'TXM' => 'TXMF',
                'VAC' => 'VACF',
            ];

            if (isset($pbFirstMap[$gId])) {
                // CHG: Pull the companion value for the SAME draw date (fixes mismatched ?other lottery? values)
                $pb = leFetchColumnByDate($db, $dbCol, 'first', $pbFirstMap[$gId], $drawYmd, $stateNameRaw);
            } else if ($gId === 'NCD') {
                // Pull Fireball from NCDF using draw_results; match same draw_date; preserve leading zeros
                $pbRaw = leFetchDrawResultsByDate($db, $dbCol, 'NCDF', $drawYmd, null);
                $pb    = preg_replace('/\D+/', '', (string) $pbRaw);
            } else if ($gId === 'VAD') {
                // Pull Fireball from VADF using draw_results; match same draw_date; preserve leading zeros
                $pbRaw = leFetchDrawResultsByDate($db, $dbCol, 'VADF', $drawYmd, null);
                $pb    = preg_replace('/\D+/', '', (string) $pbRaw);
            }

            include "anhisWin4extra.php";
           


                /** Oscar Implemented STRAIGHT 2 with 0-9 numbers With no Extra Ball **/
        }else if($gId === 'DCH' || $gId === 'DCG'){  
            include "anhisWin2.php";
 
 


        /** STRAIGHT 5 + CASH BALL with 48 numbers + 18 FOR CASH BALL - AR LUCKY4LIFE - **/
        }else if($gId === '132'){ 
            include "anhisLucky4Life.php";
        /** STRAIGHT 5 + CASH BALL with 32 numbers + 25 FOR CASH BALL - KS SUPER CASH (KS3) - **/
        }else if($gId === 'KS3'){ 
            include "anhisKS3.php";    
        /** STRAIGHT 5 with 35 numbers & 1 with 1-5  - TENNESSEE Cash **/
        }else if($gId === 'TN3'){
            include "anhisTN3.php";  
       /** STRAIGHT 5 with 38 numbers & 1 with 1-5  - Palmetto Cash 5 **/
        }else if($gId === 'SC2'){
            include "anhisSC2.php";    
        /** STRAIGHT 5 with 39 numbers - AR NATURAL STATE JACKPOT, CA Fantasy 5 **/
        }else if($gId === 'AR1'){
            include "anhisARNSJ.php";
        }else if($gId === 'CA2'){
            include "anhisARNSJ.php";
        /** STRAIGHT 5 with 41 numbers - AR FANTASY 5, Virginia Cash 5 **/
        }else if($gId === 'AZ2'){ 
            include "anhisAZF5.php";
        }else if($gId === 'AZT'){ 
            include "anhisAZT.php";
        /** STRAIGHT 5 with 42 numbers  - GA FANTASY 5 **/
        }else if($gId === 'GA2'){ 
            include "anhisGA2.php";
        /** STRAIGHT 5 with 47 numbers & 1 with 27 numbers - CA SUPER LOTTO PLUS **/
        }else if($gId === 'CA1'){
            include "anhisCA1.php";
        /** STRAIGHT 5 with 58 numbers & 1 with 5 numbers - Millionaire for Life **/
        }else if($gId === '145'){
            include "anhisMforlife.php";
        }


// CHG: Replace fragile <br> stacks with a consistent spacer (reduces CLS risk; cleaner structure)
echo '<div class="le-spacer" aria-hidden="true"></div>';
echo JHtml::_('content.prepare', '{loadposition OfficialLotteries}');

echo '<div class="le-spacer" aria-hidden="true"></div>';

echo JHtml::_('content.prepare', '{loadposition OddsCalculator}');
echo '<div class="le-spacer" aria-hidden="true"></div>';

    /**
     * CHG: Harden DB access (Joomla query builder; no raw SQL interpolation).
     * OUTPUT: Rendering remains unchanged.
     */
    $gaID = '';

    // CHG: Resolve game_id via DB wrapper helper (centralized quoting/binding)
    $gaID = leFetchScalar(
        $db,
        $dbCol,
        'game_id',
        ['game_name' => $gNameRaw],
        $db->quoteName('id') . ' DESC'
    );

    if ($gaID !== '') {
        $q2 = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__lottowords'))
            ->where($db->quoteName('game_id') . ' = ' . $db->quote($gaID))
            ->where($db->quoteName('statename') . ' = ' . $db->quote($stateAbrevRaw));

        $db->setQuery($q2);
        $fwords = (array) $db->loadObjectList();

        if (!empty($fwords)) {
            echo '<div class="fwords-wrapper">';
            echo '<div class="fwords-inner">';

            foreach ($fwords as $fword) {
                // CHG: Escape plain-text fields; preserve rich text safely via Joomla content.prepare
                $safeTitle = htmlspecialchars((string) ($fword->title ?? ''), ENT_QUOTES, 'UTF-8');

                // footertext often contains intended HTML; prepare it (plugins/filters) instead of raw echo
                $rawFooter = (string) ($fword->footertext ?? '');
                $safeFooter = HTMLHelper::_('content.prepare', $rawFooter);

                echo '<h2>' . $safeTitle . '</h2>';
                echo '<div class="fwordDesc">' . $safeFooter . '</div>';
            }

            echo '</div>';
            echo '</div>';
        }
    }
    ?>
    
<style>
/* CHG: Consistent vertical rhythm ? avoids <br> stacks and reduces layout jank */
.le-spacer{ display:block; height:32px; }

/* Existing */
ul.ulStatesList li {position:relative;display: inline-block;margin-right: 10px;}
ul.ulStatesList li:after {content: "|";position: absolute;right: -8px;top: -1px;font-weight: 900;color: #191195;}
ul.ulStatesList li:last-child:after {content: " ";}
</style>