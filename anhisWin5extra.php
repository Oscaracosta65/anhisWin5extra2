<?php
/**
 * LottoExpert.net — Pick 5 Digit Results Intelligence Page
 * Joomla 5.x + PHP 8.1+
 *
 * Converted from Target Page (Pick-5 digit game) using Reference Page
 * SKAI architecture, visual system, and UX hierarchy.
 *
 * GAME STRUCTURE (detected from target):
 * - Pick 5 daily game: 5 positions, each digit 0–9
 * - Optional extra ball (Fireball / Wild Ball) stored under a
 *   separate game_id in the same table, digit in 'first' column
 * - No classic numbered-ball pool (no range 1–41)
 *
 * ASSUMES gmCode URL parameter is present (e.g., ?gmCode=FLH)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$app   = Factory::getApplication();
$doc   = Factory::getDocument();
$input = $app->input;
$db    = Factory::getDbo();
$user  = Factory::getUser();

/* -----------------------------------------------------------------------
 * GAME TABLE MAP
 * ----------------------------------------------------------------------- */
$gameTableMap = [
    'FLH' => '#__lotterydb_fl',
    'FLG' => '#__lotterydb_fl',
    'PAF' => '#__lotterydb_pa',
    'PAG' => '#__lotterydb_pa',
];

/* -----------------------------------------------------------------------
 * GAME INFO MAP
 * ----------------------------------------------------------------------- */
$gameInfoMap = [
    'FLH' => [
        'state'           => 'Florida',
        'stateAbrev'      => 'FL',
        'lottery'         => 'Pick 5 Evening',
        'mainGameId'      => 'FLH',
        'extraBallGameId' => 'FLHF',
        'extraBallLabel'  => 'Fireball',
    ],
    'FLG' => [
        'state'           => 'Florida',
        'stateAbrev'      => 'FL',
        'lottery'         => 'Pick 5 Midday',
        'mainGameId'      => 'FLG',
        'extraBallGameId' => 'FLGF',
        'extraBallLabel'  => 'Fireball',
    ],
    'PAF' => [
        'state'           => 'Pennsylvania',
        'stateAbrev'      => 'PA',
        'lottery'         => 'Pick 5 Evening',
        'mainGameId'      => 'PAF',
        'extraBallGameId' => 'PAFW',
        'extraBallLabel'  => 'Wild Ball',
    ],
    'PAG' => [
        'state'           => 'Pennsylvania',
        'stateAbrev'      => 'PA',
        'lottery'         => 'Pick 5 Day',
        'mainGameId'      => 'PAG',
        'extraBallGameId' => 'PAEW',
        'extraBallLabel'  => 'Wild Ball',
    ],
];

/* -----------------------------------------------------------------------
 * VALIDATE gmCode
 * ----------------------------------------------------------------------- */
$gmCode = strtoupper(trim($input->getString('gmCode', '')));

if ($gmCode === '' || !preg_match('/^[A-Z0-9]{3,5}$/', $gmCode)) {
    die('Invalid game code.');
}

if (!array_key_exists($gmCode, $gameTableMap)) {
    die('Invalid game ID.');
}

$gId   = $gmCode;
$dbCol = $gameTableMap[$gId];

$mainGameId    = '';
$extraBallGId  = null;
$stateName     = '';
$stateAbrev    = '';
$lotteryName   = '';
$extraBallLabel = 'Extra Ball';
$gameFound     = false;

foreach ($gameInfoMap as $infoKey => $gameInfo) {
    $matchMain  = ($gameInfo['mainGameId'] === $gId);
    $matchExtra = (isset($gameInfo['extraBallGameId']) && $gameInfo['extraBallGameId'] === $gId);

    if ($matchMain || $matchExtra) {
        $mainGameId    = $gameInfo['mainGameId'];
        $extraBallGId  = isset($gameInfo['extraBallGameId']) ? $gameInfo['extraBallGameId'] : null;
        $stateName     = $gameInfo['state'];
        $stateAbrev    = $gameInfo['stateAbrev'];
        $lotteryName   = $gameInfo['lottery'];
        $extraBallLabel = isset($gameInfo['extraBallLabel']) ? $gameInfo['extraBallLabel'] : 'Extra Ball';
        $gameFound     = true;
        break;
    }
}

if (!$gameFound) {
    die('Invalid game ID.');
}

/* -----------------------------------------------------------------------
 * SEO / CANONICAL / META
 * ----------------------------------------------------------------------- */
$uri              = Uri::getInstance();
$canonicalNoQuery = $uri->toString(['scheme', 'host', 'port', 'path']) . '?gmCode=' . rawurlencode($gmCode);

$doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="en" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');

$doc->setTitle('Digit Frequency Analysis — ' . $stateName . ' ' . $lotteryName . ' | LottoExpert.net');
$doc->setMetaData('description', 'Analyze digit frequency, per-position heatmaps, and combination history for the ' . $stateName . ' ' . $lotteryName . '. Review active digits, quiet positions, draw recency, and complete historical data.');

/* -----------------------------------------------------------------------
 * USER / SESSION
 * ----------------------------------------------------------------------- */
$loginStatus = (int) ($user->guest ?? 1);

if ($loginStatus === 1) {
    $session     = Factory::getSession();
    $userSession = $session->getId();
} else {
    $userId = (int) $user->id;

    $profileQuery = $db->getQuery(true)
        ->select($db->quoteName('profile_value'))
        ->from($db->quoteName('#__user_profiles'))
        ->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.phone'))
        ->where($db->quoteName('user_id') . ' = ' . (int) $userId);

    $db->setQuery($profileQuery);
    $userPhone = (string) $db->loadResult();

    if ($userPhone !== '') {
        $userPhone = str_replace('"', '', $userPhone);
        $userPhone = str_replace('(', '', $userPhone);
        $userPhone = str_replace(')', '-', $userPhone);
    } else {
        $userPhone = 'NULL';
    }
}

/* =======================================================================
 * HELPER FUNCTIONS
 * ===================================================================== */

function leFmtDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return ($ts === false) ? '' : date('m-d-Y', $ts);
}

function leFmtDateLong(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return ($ts === false) ? '—' : date('F j, Y', $ts);
}

function leResolveLogo(string $stateAbrev, string $lotteryName): array
{
    $stateSlug   = strtolower(trim($stateAbrev));
    $lotterySlug = strtolower(str_replace(' ', '-', trim($lotteryName)));
    $rel         = '/images/lottodb/us/' . $stateSlug . '/' . $lotterySlug . '.png';
    $abs         = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR) . $rel;

    if (is_file($abs)) {
        return ['url' => $rel, 'exists' => true];
    }

    return ['url' => '', 'exists' => false];
}

function leCommaList(array $items): string
{
    $clean = array_values(array_filter(
        array_map('trim', array_map('strval', $items)),
        static function ($v) {
            return $v !== '';
        }
    ));

    return empty($clean) ? '—' : implode(', ', $clean);
}

function leDrawingsAgoLabel(?int $idx, int $window): array
{
    if ($idx === null) {
        return [$window + 1, 'Not in last ' . $window . ' drws'];
    }

    if ($idx === 0) {
        return [1, 'In last drw'];
    }

    return [$idx + 1, ($idx + 1) . ' drws ago'];
}

function leEscapeJsString(string $value): string
{
    return str_replace(
        ["\\", "'", "\r", "\n", '</'],
        ["\\\\", "\\'", '', '', '<\/'],
        $value
    );
}

function leFetchRecentRows(\Joomla\Database\DatabaseDriver $db, string $dbCol, string $gameId, int $limit): array
{
    $query = $db->getQuery(true)
        ->select([
            $db->quoteName('id'),
            $db->quoteName('draw_date'),
            $db->quoteName('next_draw_date'),
            $db->quoteName('next_jackpot'),
            $db->quoteName('first'),
            $db->quoteName('second'),
            $db->quoteName('third'),
            $db->quoteName('fourth'),
            $db->quoteName('fifth'),
        ])
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
        ->order($db->quoteName('draw_date') . ' DESC');

    $db->setQuery($query, 0, $limit);
    $rows = $db->loadAssocList();

    return is_array($rows) ? $rows : [];
}

function leGetLatestResult(\Joomla\Database\DatabaseDriver $db, string $dbCol, string $mainGameId, ?string $extraBallGameId = null): ?array
{
    $query = $db->getQuery(true)
        ->select([
            $db->quoteName('id'),
            $db->quoteName('draw_date'),
            $db->quoteName('next_draw_date'),
            $db->quoteName('next_jackpot'),
            $db->quoteName('first'),
            $db->quoteName('second'),
            $db->quoteName('third'),
            $db->quoteName('fourth'),
            $db->quoteName('fifth'),
        ])
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = ' . $db->quote($mainGameId))
        ->order($db->quoteName('draw_date') . ' DESC');

    $db->setQuery($query, 0, 1);
    $row = $db->loadAssoc();

    if (!$row) {
        return null;
    }

    if ($extraBallGameId) {
        $eq = $db->getQuery(true)
            ->select($db->quoteName('first'))
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('game_id') . ' = ' . $db->quote($extraBallGameId))
            ->order($db->quoteName('draw_date') . ' DESC');

        $db->setQuery($eq, 0, 1);
        $extraDigit = $db->loadResult();

        if ($extraDigit !== null) {
            $row['extra_ball'] = (string) $extraDigit;
        }
    }

    return $row;
}

function leOverallFreqsFromRows(array $rows): array
{
    $freqs = [];

    foreach ($rows as $row) {
        $f = trim($row['first'] ?? '');
        $s = trim($row['second'] ?? '');
        $t = trim($row['third'] ?? '');
        $fo = trim($row['fourth'] ?? '');
        $fi = trim($row['fifth'] ?? '');

        if ($f === '' || $s === '' || $t === '' || $fo === '' || $fi === '') {
            continue;
        }

        $combo = $f . $s . $t . $fo . $fi;

        if (!isset($freqs[$combo])) {
            $freqs[$combo] = 0;
        }

        $freqs[$combo]++;
    }

    arsort($freqs);

    return $freqs;
}

function leComboRecencyLabel(string $combo, array $rows, int $window): string
{
    foreach ($rows as $idx => $row) {
        $rc = trim($row['first'] ?? '') . trim($row['second'] ?? '') . trim($row['third'] ?? '') . trim($row['fourth'] ?? '') . trim($row['fifth'] ?? '');

        if ($rc === $combo) {
            if ($idx === 0) {
                return 'Current draw';
            }

            return $idx . ' drw' . ($idx === 1 ? '' : 's') . ' ago';
        }
    }

    return 'Not in last ' . $window . ' dr.';
}

/* =======================================================================
 * TOTAL DRAWINGS COUNT
 * ===================================================================== */
$totalQuery = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName($dbCol))
    ->where($db->quoteName('game_id') . ' = ' . $db->quote($mainGameId));

$db->setQuery($totalQuery);
$totalDrawings = (int) $db->loadResult();

/* =======================================================================
 * INPUT HANDLING
 * ===================================================================== */
$defaultWindow = 100;
$drawRange     = $defaultWindow;

if ($input->getMethod() === 'POST' && Session::checkToken()) {
    $drawRange = (int) $input->post->get('drawRange', $defaultWindow, 'int');
}

$maxWindow = ($totalDrawings > 0) ? $totalDrawings : 9999;
$drawRange = max(10, min($maxWindow, $drawRange));

/* =======================================================================
 * FETCH LATEST DRAW
 * ===================================================================== */
$lr = leGetLatestResult($db, $dbCol, $mainGameId, $extraBallGId);

$drawDate     = $lr ? (string) ($lr['draw_date'] ?? '') : '';
$nextDrawDate = $lr ? (string) ($lr['next_draw_date'] ?? '') : '';
$nextJackpot  = $lr ? (string) ($lr['next_jackpot'] ?? '') : '';

$p1 = $lr ? trim((string) ($lr['first'] ?? '')) : '';
$p2 = $lr ? trim((string) ($lr['second'] ?? '')) : '';
$p3 = $lr ? trim((string) ($lr['third'] ?? '')) : '';
$p4 = $lr ? trim((string) ($lr['fourth'] ?? '')) : '';
$p5 = $lr ? trim((string) ($lr['fifth'] ?? '')) : '';
$pb = ($lr && isset($lr['extra_ball'])) ? trim((string) $lr['extra_ball']) : null;

/* =======================================================================
 * FETCH ANALYSIS WINDOW ROWS
 * ===================================================================== */
$rowsMain = leFetchRecentRows($db, $dbCol, $mainGameId, $drawRange);

/* =======================================================================
 * PER-POSITION AND COMBINED DIGIT COUNTS
 * ===================================================================== */
$posNames        = ['first', 'second', 'third', 'fourth', 'fifth'];
$posDisplayNames = ['1st Position', '2nd Position', '3rd Position', '4th Position', '5th Position'];
$posShortNames   = ['P1', 'P2', 'P3', 'P4', 'P5'];

$posCounts   = [];
$posLastSeen = [];

$allDigitCounts   = [];
$allDigitLastSeen = [];

for ($d = 0; $d <= 9; $d++) {
    $allDigitCounts[$d]   = 0;
    $allDigitLastSeen[$d] = null;
}

foreach ($posNames as $pn) {
    $posCounts[$pn]   = [];
    $posLastSeen[$pn] = [];

    for ($d = 0; $d <= 9; $d++) {
        $posCounts[$pn][$d]   = 0;
        $posLastSeen[$pn][$d] = null;
    }
}

foreach ($rowsMain as $idx => $row) {
    foreach ($posNames as $pn) {
        $ds = trim($row[$pn] ?? '');

        if ($ds === '') {
            continue;
        }

        $d = (int) $ds;

        if ($d < 0 || $d > 9) {
            continue;
        }

        $posCounts[$pn][$d]++;

        if ($posLastSeen[$pn][$d] === null) {
            $posLastSeen[$pn][$d] = $idx;
        }

        $allDigitCounts[$d]++;

        if ($allDigitLastSeen[$d] === null) {
            $allDigitLastSeen[$d] = $idx;
        }
    }
}

/* =======================================================================
 * EXTRA BALL COUNTS
 * ===================================================================== */
$extraCounts   = [];
$extraLastSeen = [];
$rowsExtra     = [];

for ($d = 0; $d <= 9; $d++) {
    $extraCounts[$d]   = 0;
    $extraLastSeen[$d] = null;
}

if ($extraBallGId) {
    $rowsExtra = leFetchRecentRows($db, $dbCol, $extraBallGId, $drawRange);

    foreach ($rowsExtra as $idx => $row) {
        $ds = trim($row['first'] ?? '');

        if ($ds === '') {
            continue;
        }

        $d = (int) $ds;

        if ($d < 0 || $d > 9) {
            continue;
        }

        $extraCounts[$d]++;

        if ($extraLastSeen[$d] === null) {
            $extraLastSeen[$d] = $idx;
        }
    }
}

/* =======================================================================
 * TOP ACTIVE AND QUIETEST DIGITS (combined across all positions)
 * ===================================================================== */
$sortedActive = $allDigitCounts;
arsort($sortedActive);

$topActiveKeys   = array_keys($sortedActive);
$topActiveLabels = array_map('strval', $topActiveKeys);
$topActiveValues = array_values($sortedActive);

$recencyMap = [];

for ($d = 0; $d <= 9; $d++) {
    $recencyMap[$d] = ($allDigitLastSeen[$d] === null)
        ? ($drawRange + 1)
        : ((int) $allDigitLastSeen[$d] + 1);
}

$sortedQuiet    = $recencyMap;
arsort($sortedQuiet);
$quietestKeys   = array_keys($sortedQuiet);
$quietestLabels = array_map('strval', $quietestKeys);
$quietestValues = array_values($sortedQuiet);

/* Combined chart: digits 0–9 in natural order */
$combinedLabels      = [];
$combinedValues      = [];
$recencyChartValues  = [];

for ($d = 0; $d <= 9; $d++) {
    $combinedLabels[]     = (string) $d;
    $combinedValues[]     = (int) $allDigitCounts[$d];
    $recencyChartValues[] = ($allDigitLastSeen[$d] === null)
        ? ($drawRange + 1)
        : ((int) $allDigitLastSeen[$d] + 1);
}

/* Extra ball chart data */
$extraChartLabels = [];
$extraChartValues = [];

for ($d = 0; $d <= 9; $d++) {
    $extraChartLabels[] = (string) $d;
    $extraChartValues[] = (int) $extraCounts[$d];
}

/* Summary stats */
$mostActiveSummary = array_map('strval', array_slice($topActiveKeys, 0, 3));
$quietSummary      = array_map('strval', array_slice($quietestKeys, 0, 3));

/* =======================================================================
 * PER-POSITION ACTIVE / QUIET TAGS (for table row filtering)
 * ===================================================================== */
$posTopActive = [];
$posTopQuiet  = [];

foreach ($posNames as $pn) {
    $s = $posCounts[$pn];
    arsort($s);
    $posTopActive[$pn] = array_keys(array_slice($s, 0, 3, true));

    $r = [];
    for ($d = 0; $d <= 9; $d++) {
        $r[$d] = ($posLastSeen[$pn][$d] === null) ? ($drawRange + 1) : ((int) $posLastSeen[$pn][$d] + 1);
    }
    arsort($r);
    $posTopQuiet[$pn] = array_keys(array_slice($r, 0, 3, true));
}

/* =======================================================================
 * TOP 10 OVERALL COMBINATIONS
 * ===================================================================== */
$comboCounts = leOverallFreqsFromRows($rowsMain);

$top10CombosLabels = [];
$top10CombosValues = [];
$cn = 0;

foreach ($comboCounts as $combo => $cnt) {
    $top10CombosLabels[] = $combo;
    $top10CombosValues[] = (int) $cnt;

    if (++$cn >= 10) {
        break;
    }
}

/* Latest combo frequency */
$latestCombo     = $p1 . $p2 . $p3 . $p4 . $p5;
$latestComboFreq = 0;

if (strlen($latestCombo) === 5 && isset($comboCounts[$latestCombo])) {
    $latestComboFreq = max(0, (int) $comboCounts[$latestCombo] - 1);
}

$latestComboFreqLabel = ($latestComboFreq > 0)
    ? ($latestComboFreq . 'x previously in window')
    : 'First time in window';

/* =======================================================================
 * DRAW HISTORY ROWS (for Recency section)
 * ===================================================================== */
$drawHistoryRows = [];
$latestDigits    = [(int) $p1, (int) $p2, (int) $p3, (int) $p4, (int) $p5];

foreach ($latestDigits as $pidx => $digit) {
    $posCol   = $posNames[$pidx];
    $posLabel = $posDisplayNames[$pidx] . ' — Digit ' . $digit;

    $prevDate = null;
    $drawsAgo = null;

    foreach ($rowsMain as $ridx => $row) {
        if ($ridx === 0) {
            continue;
        }

        $rd = (int) trim($row[$posCol] ?? '');

        if ($rd === $digit) {
            $prevDate = $row['draw_date'] ?? null;
            $drawsAgo = $ridx;
            break;
        }
    }

    $drawHistoryRows[] = [
        'label'    => $posLabel,
        'prevDate' => $prevDate,
        'drawsAgo' => $drawsAgo,
        'isBonus'  => false,
    ];
}

/* Extra ball draw history */
if ($extraBallGId && $pb !== null && count($rowsExtra) > 0) {
    $extraDigit = (int) $pb;
    $prevDate   = null;
    $drawsAgo   = null;

    foreach ($rowsExtra as $ridx => $row) {
        if ($ridx === 0) {
            continue;
        }

        $rd = (int) trim($row['first'] ?? '');

        if ($rd === $extraDigit) {
            $prevDate = $row['draw_date'] ?? null;
            $drawsAgo = $ridx;
            break;
        }
    }

    $drawHistoryRows[] = [
        'label'    => htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8') . ' — Digit ' . $extraDigit,
        'prevDate' => $prevDate,
        'drawsAgo' => $drawsAgo,
        'isBonus'  => true,
    ];
}

/* =======================================================================
 * WINDOW SHIFT ANALYSIS (50-draw vs 300-draw combined digit frequency)
 * ===================================================================== */
$rows50  = leFetchRecentRows($db, $dbCol, $mainGameId, 50);
$rows300 = leFetchRecentRows($db, $dbCol, $mainGameId, 300);

$counts50  = array_fill(0, 10, 0);
$counts300 = array_fill(0, 10, 0);

foreach ($rows50 as $row) {
    foreach ($posNames as $pn) {
        $d = (int) trim($row[$pn] ?? '');
        if ($d >= 0 && $d <= 9) {
            $counts50[$d]++;
        }
    }
}

foreach ($rows300 as $row) {
    foreach ($posNames as $pn) {
        $d = (int) trim($row[$pn] ?? '');
        if ($d >= 0 && $d <= 9) {
            $counts300[$d]++;
        }
    }
}

$sortedCounts50  = $counts50;
$sortedCounts300 = $counts300;
arsort($sortedCounts50);
arsort($sortedCounts300);

$top50  = array_keys(array_slice($sortedCounts50, 0, 5, true));
$top300 = array_keys(array_slice($sortedCounts300, 0, 5, true));

$windowShiftIn  = [];
$windowShiftOut = [];

foreach ($top300 as $wdigit) {
    if (!in_array($wdigit, $top50, true)) {
        $windowShiftIn[] = $wdigit;
    }
}

foreach ($top50 as $wdigit) {
    if (!in_array($wdigit, $top300, true)) {
        $windowShiftOut[] = $wdigit;
    }
}

$top50Strs         = array_map('strval', $top50);
$top300Strs        = array_map('strval', $top300);
$windowShiftInStrs = array_map('strval', $windowShiftIn);
$windowShiftOutStrs= array_map('strval', $windowShiftOut);

$windowChangeNarrative  = 'In the recent 50-draw view, the most frequently appearing digits across all positions are ' . leCommaList(array_slice($top50Strs, 0, 3)) . '. ';
$windowChangeNarrative .= 'In the broader 300-draw view, ' . leCommaList(array_slice($top300Strs, 0, 3)) . ' shows more historical prominence. ';

if (!empty($windowShiftInStrs)) {
    $windowChangeNarrative .= 'Digit ' . leCommaList(array_slice($windowShiftInStrs, 0, 2)) . ' gains more prominence when the window broadens. ';
}

if (!empty($windowShiftOutStrs)) {
    $windowChangeNarrative .= 'Digit ' . leCommaList(array_slice($windowShiftOutStrs, 0, 2)) . ' shows more concentration in the shorter recent window.';
}

/* =======================================================================
 * LOGO
 * ===================================================================== */
$logo = leResolveLogo($stateAbrev, $lotteryName);

/* =======================================================================
 * COPY / CTA
 * ===================================================================== */
$heroInsight  = 'Latest verified draw and digit activity across all five positions at a glance. Review the most active digits, quiet stretches, and complete per-position distribution before moving into deeper analysis.';
$overviewNote = 'Digit frequency shows historical occurrence within the selected draw window. It can help identify recent concentration and quiet periods, but it should be interpreted as context rather than prediction.';

/* =======================================================================
 * LINK HELPERS
 * ===================================================================== */
$linkBase   = '?gmCode=' . rawurlencode($gmCode);
$linkStateN = '&amp;stateName=' . rawurlencode($stateName);
$linkGameN  = '&amp;gName=' . rawurlencode($lotteryName);
$linkSTn    = '&amp;sTn=' . rawurlencode(strtolower($stateAbrev));
$linkGId    = '&amp;gId=' . rawurlencode($gmCode);
$linkMid    = rawurlencode($mainGameId);

$hrefSKAI    = htmlspecialchars('/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=' . $linkMid, ENT_QUOTES, 'UTF-8');
$hrefAI      = htmlspecialchars('/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=' . $linkMid, ENT_QUOTES, 'UTF-8');
$hrefSkipHit = htmlspecialchars('/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=' . $linkMid, ENT_QUOTES, 'UTF-8');
$hrefMCMC    = htmlspecialchars('/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=' . $linkMid, ENT_QUOTES, 'UTF-8');
$hrefHeatmap = htmlspecialchars('/all-lottery-heatmaps?gmCode=' . rawurlencode($gmCode) . $linkStateN . $linkGameN . $linkSTn, ENT_QUOTES, 'UTF-8');
$hrefArchive = htmlspecialchars('/lottery-archives?gmCode=' . rawurlencode($gmCode) . $linkStateN . $linkGameN . $linkSTn, ENT_QUOTES, 'UTF-8');
$hrefLowest  = htmlspecialchars('/lowest-drawn-number-analysis?gmCode=' . rawurlencode($gmCode) . $linkStateN . $linkGameN . $linkSTn, ENT_QUOTES, 'UTF-8');
$hrefFreqDiv = '#frequency-deep-dive';

$formAction  = htmlspecialchars($uri->toString(['path']) . '?gmCode=' . rawurlencode($gmCode) . '#tables', ENT_QUOTES, 'UTF-8');
?>
<script type="application/ld+json">
<?php
$jldPage = [
    '@context'    => 'https://schema.org',
    '@type'       => 'WebPage',
    'name'        => 'Digit Frequency Analysis — ' . $stateName . ' ' . $lotteryName,
    'description' => 'Per-position digit frequency analysis, heatmaps, recency tracking, and combination history for the ' . $stateName . ' ' . $lotteryName . '.',
    'url'         => $canonicalNoQuery,
    'inLanguage'  => 'en',
    'publisher'   => [
        '@type' => 'Organization',
        'name'  => 'LottoExpert.net',
        'url'   => 'https://lottoexpert.net',
    ],
];
echo json_encode($jldPage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
</script>
<style>
:root{
  --skai-blue:#1C66FF;
  --deep-navy:#0A1A33;
  --sky-gray:#EFEFF5;
  --soft-slate:#7F8DAA;
  --success-green:#20C997;
  --caution-amber:#F5A623;
  --white:#FFFFFF;
  --danger-red:#A61D2D;

  --grad-horizon:linear-gradient(135deg,#0A1A33 0%,#1C66FF 100%);
  --grad-radiant:linear-gradient(135deg,#1C66FF 0%,#7F8DAA 100%);
  --grad-slate:linear-gradient(180deg,#EFEFF5 0%,#FFFFFF 100%);
  --grad-success:linear-gradient(135deg,#20C997 0%,#0A1A33 100%);
  --grad-ember:linear-gradient(135deg,#F5A623 0%,#0A1A33 100%);

  --text:#0A1A33;
  --text-soft:#5F6F8C;
  --line:rgba(10,26,51,.10);
  --line-strong:rgba(10,26,51,.16);
  --shadow-1:0 12px 32px rgba(10,26,51,.08);
  --shadow-2:0 20px 48px rgba(10,26,51,.14);
  --radius-14:14px;
  --radius-18:18px;
  --radius-22:22px;
  --font:Inter,"SF Pro Text","SF Pro Display","Helvetica Neue",Arial,sans-serif;
}

*{box-sizing:border-box}

.skai-page{
  max-width:1180px;
  margin:0 auto;
  padding:20px 14px 32px;
  color:var(--text);
  font-family:var(--font);
}

.skai-page a{text-decoration:none}

.skai-grid{display:grid;gap:14px}

.skai-hero{
  position:relative;
  overflow:hidden;
  border-radius:var(--radius-22);
  background:
    radial-gradient(900px 420px at -10% -20%,rgba(255,255,255,.13) 0%,rgba(255,255,255,0) 55%),
    radial-gradient(780px 340px at 110% 0%,rgba(255,255,255,.10) 0%,rgba(255,255,255,0) 55%),
    var(--grad-horizon);
  color:#fff;
  box-shadow:var(--shadow-2);
  border:1px solid rgba(255,255,255,.10);
}

.skai-hero-inner{padding:22px 20px 18px}

.skai-hero-top{
  display:grid;
  grid-template-columns:110px minmax(0,1fr) 280px;
  gap:18px;
  align-items:start;
}

.skai-logo{
  width:110px;height:110px;border-radius:20px;
  background:rgba(255,255,255,.94);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 14px 30px rgba(0,0,0,.16);
  overflow:hidden;padding:12px;
}

.skai-logo img{width:100%;height:100%;object-fit:contain;display:block}

.skai-hero-copy{min-width:0}

.skai-kicker{
  font-size:12px;line-height:1.2;letter-spacing:.18em;
  text-transform:uppercase;font-weight:800;
  color:rgba(255,255,255,.76);margin:2px 0 8px;
}

.skai-title{
  margin:0;font-size:30px;line-height:1.08;
  font-weight:900;letter-spacing:-.02em;color:#fff;
}

.skai-hero-summary{
  margin:12px 0 0;max-width:68ch;
  font-size:15px;line-height:1.65;color:rgba(255,255,255,.90);
}

.skai-result-panel{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.14);
  border-radius:18px;padding:14px;
  backdrop-filter:blur(4px);
}

.skai-panel-label{
  font-size:11px;line-height:1.2;font-weight:800;
  letter-spacing:.14em;text-transform:uppercase;
  color:rgba(255,255,255,.72);margin:0 0 10px;
}

.skai-meta-stack{display:grid;gap:10px}

.skai-meta-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}

.skai-meta-box{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  border-radius:14px;padding:10px;
}

.skai-meta-box span{display:block}

.skai-meta-box .label{
  font-size:11px;line-height:1.2;font-weight:800;
  letter-spacing:.08em;text-transform:uppercase;
  color:rgba(255,255,255,.70);
}

.skai-meta-box .value{
  margin-top:6px;font-size:15px;
  line-height:1.35;font-weight:850;color:#fff;
}

.skai-ball-row{
  display:flex;align-items:center;flex-wrap:wrap;
  gap:8px;margin-top:16px;
}

.skai-ball{
  width:42px;height:42px;border-radius:999px;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:16px;font-weight:900;letter-spacing:.02em;position:relative;
}

.skai-ball--main{
  background:linear-gradient(180deg,#FFFFFF 0%,#F3F6FF 100%);
  color:var(--deep-navy);
  border:1px solid rgba(10,26,51,.14);
  box-shadow:0 10px 20px rgba(10,26,51,.12),inset 0 1px 0 rgba(255,255,255,.90);
}

.skai-ball--bonus{
  background:radial-gradient(circle at 50% 18%,#C73E4E 0%,#8F1F2D 76%,#4A0911 100%);
  color:#fff;
  border:1px solid rgba(255,255,255,.16);
  box-shadow:0 12px 24px rgba(10,26,51,.18);
}

.skai-ball-gap{width:8px;height:1px}

.skai-hero-actions{
  margin-top:18px;
  display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:10px;
}

.skai-btn{
  display:inline-flex;align-items:center;justify-content:center;
  gap:10px;border-radius:14px;min-height:48px;padding:12px 16px;
  font-size:14px;line-height:1.2;font-weight:850;
  transition:transform .14s ease,box-shadow .14s ease,filter .14s ease;
}

.skai-btn:hover{transform:translateY(-1px)}

.skai-btn:focus,.skai-btn:focus-visible{
  outline:3px solid rgba(255,255,255,.30);outline-offset:3px;
}

.skai-btn--primary{
  background:#fff;color:var(--deep-navy);
  box-shadow:0 12px 22px rgba(0,0,0,.14);
}

.skai-btn--secondary{
  background:rgba(255,255,255,.12);color:#fff;
  border:1px solid rgba(255,255,255,.18);
}

.skai-advanced-links{
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
  gap:10px;margin-top:10px;
}

.skai-mini-link{
  display:flex;align-items:center;justify-content:center;
  text-align:center;min-height:44px;padding:10px 12px;
  border-radius:12px;background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  color:#fff;font-size:13px;line-height:1.3;font-weight:800;
}

.skai-strip{
  margin-top:14px;
  display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;
}

.skai-stat{
  border-radius:18px;overflow:hidden;
  background:var(--grad-slate);
  border:1px solid var(--line);box-shadow:var(--shadow-1);
}

.skai-stat-head{
  padding:12px 14px;color:#fff;
  font-size:12px;line-height:1.25;letter-spacing:.12em;
  text-transform:uppercase;font-weight:850;
}

.skai-stat-head--horizon{background:var(--grad-horizon)}
.skai-stat-head--radiant{background:var(--grad-radiant)}
.skai-stat-head--success{background:var(--grad-success)}
.skai-stat-head--ember{background:var(--grad-ember)}

.skai-stat-body{
  padding:14px;min-height:120px;
  display:flex;flex-direction:column;justify-content:space-between;
}

.skai-stat-value{
  font-size:24px;line-height:1.12;font-weight:900;
  letter-spacing:-.02em;color:var(--deep-navy);
}

.skai-stat-note{
  margin-top:10px;font-size:13px;line-height:1.6;color:var(--text-soft);
}

.skai-tabs{
  margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;
  padding:6px;border-radius:999px;
  background:var(--sky-gray);border:1px solid var(--line);
}

.skai-tab{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:42px;padding:10px 16px;border-radius:999px;
  color:var(--deep-navy);font-size:13px;line-height:1.2;font-weight:850;
}

.skai-tab--active{
  background:var(--grad-horizon);color:#fff;
  box-shadow:0 10px 20px rgba(10,26,51,.12);
}

.skai-section{
  margin-top:16px;background:var(--grad-slate);
  border:1px solid var(--line);border-radius:20px;
  box-shadow:var(--shadow-1);overflow:hidden;
}

.skai-section-head{
  display:flex;align-items:flex-start;justify-content:space-between;
  gap:14px;padding:18px 18px 14px;
  border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.55);
}

.skai-section-title{
  margin:0;font-size:22px;line-height:1.15;
  letter-spacing:-.02em;font-weight:900;color:var(--deep-navy);
}

.skai-section-sub{
  margin:8px 0 0;max-width:76ch;
  font-size:14px;line-height:1.65;color:var(--text-soft);
}

.skai-section-body{padding:16px 18px 18px;background:#fff}

.skai-overview-grid{
  display:grid;grid-template-columns:1.2fr 1fr;gap:14px;
}

.skai-overview-grid > *{min-width:0}

.skai-card{
  background:#fff;border:1px solid var(--line);
  border-radius:18px;box-shadow:0 10px 24px rgba(10,26,51,.06);overflow:hidden;
}

.skai-card-head{
  padding:14px 16px;color:#fff;font-weight:850;
  font-size:16px;line-height:1.25;
}

.skai-card-head--horizon{background:var(--grad-horizon)}
.skai-card-head--radiant{background:var(--grad-radiant)}
.skai-card-head--success{background:var(--grad-success)}
.skai-card-head--ember{background:var(--grad-ember)}

.skai-card-sub{
  display:block;margin-top:4px;
  font-size:12px;line-height:1.45;font-weight:700;opacity:.92;
}

.skai-card-body{padding:14px 16px 16px}

.skai-chart-frame{
  position:relative;width:100%;height:300px;overflow:hidden;
}

.skai-chart-frame--md{height:220px}

.skai-note{
  margin-top:14px;padding:14px 16px;border-radius:16px;
  background:linear-gradient(180deg,#F8FAFE 0%,#FFFFFF 100%);
  border:1px solid var(--line);color:var(--text-soft);
  font-size:13px;line-height:1.7;
}

.skai-two-col{
  display:grid;grid-template-columns:1fr 1fr;gap:14px;
}

.skai-two-col > *{min-width:0}

.skai-history-list{display:grid;gap:10px}

.skai-history-item{
  display:grid;grid-template-columns:190px 1fr auto;
  gap:12px;align-items:center;padding:12px 14px;
  border-radius:14px;border:1px solid var(--line);
  background:linear-gradient(180deg,#FFFFFF 0%,#FAFBFF 100%);
}

.skai-history-name{
  font-size:14px;line-height:1.35;font-weight:850;color:var(--deep-navy);
}

.skai-history-date{font-size:13px;line-height:1.55;color:var(--text-soft)}

.skai-history-badge{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:110px;min-height:36px;padding:8px 12px;
  border-radius:999px;background:var(--grad-radiant);
  color:#fff;font-size:12px;line-height:1.2;font-weight:850;
}

.skai-history-badge--bonus{background:var(--grad-ember)}

.skai-window-shift{display:grid;gap:12px}

.skai-shift-panel{
  border:1px solid var(--line);border-radius:16px;
  padding:14px 15px;
  background:linear-gradient(180deg,#FFFFFF 0%,#FAFBFF 100%);
}

.skai-shift-label{
  margin:0 0 8px;font-size:12px;line-height:1.2;
  letter-spacing:.12em;text-transform:uppercase;
  font-weight:850;color:var(--soft-slate);
}

.skai-shift-text{margin:0;font-size:14px;line-height:1.7;color:var(--text)}

.skai-controls{
  padding:14px 16px;border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.76);
}

.skai-controls form{margin:0}

.skai-controls-row{
  display:flex;flex-wrap:wrap;align-items:center;
  justify-content:space-between;gap:12px;
}

.skai-controls-left{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.skai-controls-right{display:flex;flex-wrap:wrap;align-items:center;gap:10px}

.skai-controls label{
  font-size:13px;line-height:1.2;font-weight:850;color:var(--deep-navy);
}

.skai-select{
  min-width:122px;min-height:44px;padding:10px 12px;
  border-radius:12px;border:1px solid var(--line-strong);
  background:#fff;color:var(--deep-navy);
  font-size:14px;line-height:1.2;font-weight:800;
}

.skai-button{
  min-height:44px;padding:10px 16px;border:none;border-radius:12px;
  background:var(--grad-horizon);color:#fff;
  font-size:13px;line-height:1.2;font-weight:850;
  cursor:pointer;box-shadow:0 10px 20px rgba(10,26,51,.12);
}

.skai-button:hover{filter:brightness(1.03)}

.skai-filter-group{display:flex;flex-wrap:wrap;gap:8px}

.skai-filter{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:36px;padding:8px 12px;border-radius:999px;
  border:1px solid var(--line);background:#fff;
  color:var(--deep-navy);font-size:12px;line-height:1.2;
  font-weight:800;cursor:pointer;
}

.skai-filter.is-active{
  background:var(--grad-horizon);border-color:transparent;color:#fff;
}

.skai-table-wrap{padding:16px;overflow-x:auto}

table.skai-table{
  width:100%;min-width:320px;
  border-collapse:separate;border-spacing:0;
  background:#fff;border:1px solid var(--line);
  border-radius:16px;overflow:hidden;
}

table.skai-table thead th{
  position:sticky;top:0;z-index:1;
  background:var(--grad-horizon);color:#fff;
  padding:8px 6px;font-size:11px;line-height:1.2;
  letter-spacing:.04em;text-transform:uppercase;
  font-weight:850;text-align:center;
  border-bottom:1px solid rgba(255,255,255,.12);
}

table.skai-table tbody td{
  padding:9px 7px;text-align:center;
  border-bottom:1px solid rgba(10,26,51,.06);
  font-size:14px;line-height:1.45;
  color:var(--deep-navy);vertical-align:middle;
}

table.skai-table tbody tr:hover{background:rgba(28,102,255,.04)}

.skai-pill{
  width:34px;height:34px;border-radius:999px;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:14px;line-height:1;font-weight:900;
}

.skai-pill--main{
  background:linear-gradient(180deg,#FFFFFF 0%,#F3F6FF 100%);
  color:var(--deep-navy);border:1px solid rgba(10,26,51,.14);
  box-shadow:0 8px 16px rgba(10,26,51,.08);
}

.skai-pill--bonus{
  background:radial-gradient(circle at 50% 18%,#C73E4E 0%,#8F1F2D 76%,#4A0911 100%);
  color:#fff;border:1px solid rgba(255,255,255,.14);
}

.skai-checkbox{transform:scale(1.25);cursor:pointer}

.skai-tracked{
  margin-top:14px;border:1px solid var(--line);
  border-radius:16px;background:var(--grad-slate);overflow:hidden;
}

.skai-tracked-head{
  display:flex;align-items:center;justify-content:space-between;
  gap:10px;padding:12px 14px;border-bottom:1px solid var(--line);
}

.skai-tracked-title{
  margin:0;font-size:15px;line-height:1.2;
  font-weight:850;color:var(--deep-navy);
}

.skai-tracked-actions{display:flex;align-items:center;gap:8px}

.skai-link-btn{
  border:none;background:none;color:var(--skai-blue);
  font-size:12px;line-height:1.2;font-weight:850;cursor:pointer;padding:0;
}

.skai-chip-wrap{
  padding:12px 14px 14px;display:flex;flex-wrap:wrap;
  gap:8px;min-height:64px;align-items:flex-start;
}

.skai-empty{font-size:13px;line-height:1.6;color:var(--text-soft)}

.skai-chip{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:36px;padding:8px 12px;border-radius:999px;
  font-size:13px;line-height:1.2;font-weight:850;
}

.skai-chip--main{
  background:linear-gradient(180deg,#FFFFFF 0%,#F3F6FF 100%);
  border:1px solid rgba(10,26,51,.14);color:var(--deep-navy);
}

.skai-chip--bonus{
  background:radial-gradient(circle at 50% 18%,#C73E4E 0%,#8F1F2D 76%,#4A0911 100%);
  color:#fff;border:1px solid rgba(255,255,255,.14);
}

.skai-pos-tab-bar{
  display:flex;flex-wrap:wrap;gap:6px;
  padding:12px 16px;border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.76);
}

.skai-pos-tab{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:34px;padding:6px 14px;border-radius:999px;
  border:1px solid var(--line);background:#fff;
  color:var(--deep-navy);font-size:12px;font-weight:850;cursor:pointer;
}

.skai-pos-tab.is-active{
  background:var(--grad-horizon);border-color:transparent;color:#fff;
  box-shadow:0 6px 14px rgba(10,26,51,.12);
}

.le-heatmap-grid{display:grid;gap:8px;margin-top:6px}

.le-heatmap-row{display:flex;align-items:stretch;gap:4px}

.le-heatmap-pos-label{
  width:30px;flex-shrink:0;display:flex;align-items:center;
  justify-content:center;font-size:11px;font-weight:850;
  color:var(--soft-slate);
}

.le-heatmap-cell{
  flex:1;text-align:center;padding:5px 3px;
  border-radius:8px;cursor:default;
}

.le-cell-digit{display:block;font-size:13px;font-weight:900}
.le-cell-freq{display:block;font-size:10px;line-height:1.2}

.le-cell--hot{
  background:linear-gradient(180deg,#1C66FF 0%,#0A3A90 100%);color:#fff;
}

.le-cell--warm{
  background:linear-gradient(180deg,#F5A623 0%,#C17A00 100%);color:#fff;
}

.le-cell--cool{
  background:linear-gradient(180deg,#EFEFF5 0%,#E0E2EA 100%);color:var(--deep-navy);
}

.skai-combo-pills{display:inline-flex;gap:4px;align-items:center}

.skai-tool-grid{
  display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px;
}

.skai-tool{
  border-radius:18px;overflow:hidden;border:1px solid var(--line);
  background:#fff;box-shadow:0 10px 24px rgba(10,26,51,.06);
}

.skai-tool-head{
  padding:14px 16px;color:#fff;font-size:15px;line-height:1.3;font-weight:850;
}

.skai-tool-body{padding:15px 16px 16px}

.skai-tool-copy{margin:0 0 14px;font-size:14px;line-height:1.7;color:var(--text-soft)}

.skai-tool-cta{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:44px;padding:10px 16px;border-radius:12px;
  font-size:13px;line-height:1.2;font-weight:850;
  background:var(--grad-horizon);color:#fff;
}

.skai-utility-grid{
  display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
  gap:10px;margin-top:12px;
}

.skai-utility-link{
  min-height:42px;display:flex;align-items:center;justify-content:center;
  text-align:center;padding:10px 12px;border-radius:12px;
  border:1px solid var(--line);background:var(--grad-slate);
  color:var(--deep-navy);font-size:13px;line-height:1.3;font-weight:850;
}

.skai-method-note{
  padding:16px;border-radius:16px;border:1px solid var(--line);
  background:linear-gradient(180deg,#FAFBFF 0%,#FFFFFF 100%);
  font-size:14px;line-height:1.8;color:var(--text-soft);
}

.skai-method-note strong{color:var(--deep-navy)}

.result-wrapper{display:none !important}

@media(max-width:1080px){
  .skai-hero-top{grid-template-columns:96px minmax(0,1fr)}
  .skai-result-panel{grid-column:1 / -1}
  .skai-strip,.skai-tool-grid,.skai-overview-grid,.skai-two-col{
    grid-template-columns:1fr;
  }
  .skai-hero-actions,.skai-advanced-links{grid-template-columns:1fr}
  .skai-utility-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:780px){
  .skai-page{padding:14px 10px 24px}
  .skai-title{font-size:26px}
  .skai-section-head{padding:16px 14px 12px}
  .skai-section-body{padding:14px}
  .skai-strip{grid-template-columns:1fr 1fr}
  .skai-history-item{grid-template-columns:1fr;align-items:start}
  .skai-meta-row{grid-template-columns:1fr}
  .skai-tabs{border-radius:18px}
  .skai-utility-grid{grid-template-columns:1fr 1fr}
  .le-heatmap-cell{padding:4px 2px}
  .le-cell-digit{font-size:11px}
  .le-cell-freq{font-size:9px}
}

@media(prefers-reduced-motion:reduce){
  .skai-btn,.skai-button{transition:none}
}
</style>

<div class="skai-page">

  <!-- =====================================================================
       HERO
       ===================================================================== -->
  <section class="skai-hero" aria-label="Results intelligence header">
    <div class="skai-hero-inner">
      <div class="skai-hero-top">

        <div class="skai-logo" aria-hidden="<?php echo $logo['exists'] ? 'false' : 'true'; ?>">
          <?php if ($logo['exists'] && $logo['url'] !== '') : ?>
            <img
              src="<?php echo htmlspecialchars($logo['url'], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($stateName . ' ' . $lotteryName, ENT_QUOTES, 'UTF-8'); ?>"
              width="110"
              height="110"
              loading="lazy"
              decoding="async"
            >
          <?php else : ?>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2l2.7 6.2L21 9l-4.7 4.1L17.6 21 12 17.8 6.4 21l1.3-7.9L3 9l6.3-.8L12 2z" stroke="rgba(10,26,51,.55)" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
          <?php endif; ?>
        </div>

        <div class="skai-hero-copy">
          <div class="skai-kicker">Digit Intelligence &bull; Verified Draw &bull; Position Analysis</div>
          <h1 class="skai-title">
            <?php echo htmlspecialchars($stateName, ENT_QUOTES, 'UTF-8'); ?>
            &ndash;
            <?php echo htmlspecialchars($lotteryName, ENT_QUOTES, 'UTF-8'); ?>
          </h1>
          <p class="skai-hero-summary"><?php echo htmlspecialchars($heroInsight, ENT_QUOTES, 'UTF-8'); ?></p>

          <div class="skai-ball-row" aria-label="Latest drawn digits">
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p1, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p2, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p3, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p4, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p5, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($pb !== null && $pb !== '') : ?>
              <span class="skai-ball-gap" aria-hidden="true"></span>
              <span class="skai-ball skai-ball--bonus" title="<?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pb, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          </div>

          <div class="skai-hero-actions" aria-label="Primary actions">
            <a class="skai-btn skai-btn--primary" href="<?php echo $hrefSKAI; ?>">
              Open SKAI Analysis
            </a>
            <a class="skai-btn skai-btn--secondary" href="<?php echo $hrefAI; ?>">
              AI Predictions
            </a>
            <a class="skai-btn skai-btn--secondary" href="<?php echo htmlspecialchars($hrefFreqDiv, ENT_QUOTES, 'UTF-8'); ?>">
              Frequency Deep Dive
            </a>
          </div>

          <div class="skai-advanced-links" aria-label="Advanced tools">
            <a class="skai-mini-link" href="<?php echo $hrefSkipHit; ?>">Skip &amp; Hit Analysis</a>
            <a class="skai-mini-link" href="<?php echo $hrefMCMC; ?>">MCMC Markov Analysis</a>
            <a class="skai-mini-link" href="<?php echo $hrefHeatmap; ?>">Heatmap Analysis</a>
          </div>
        </div>

        <aside class="skai-result-panel" aria-label="Latest draw details">
          <div class="skai-panel-label">Latest draw summary</div>

          <div class="skai-meta-stack">
            <div class="skai-meta-row">
              <div class="skai-meta-box">
                <span class="label">Draw date</span>
                <span class="value"><?php echo htmlspecialchars(leFmtDateLong($drawDate), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="skai-meta-box">
                <span class="label">Next draw date</span>
                <span class="value"><?php echo htmlspecialchars(leFmtDateLong($nextDrawDate), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>

            <div class="skai-meta-box">
              <span class="label">Latest combination</span>
              <span class="value"><?php echo htmlspecialchars($p1 . '-' . $p2 . '-' . $p3 . '-' . $p4 . '-' . $p5 . ($pb !== null && $pb !== '' ? ' + ' . $pb : ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <?php if ($nextJackpot !== '' && $nextJackpot !== '0' && $nextJackpot !== 'n/a') : ?>
              <div class="skai-meta-box">
                <span class="label">Next jackpot</span>
                <span class="value">$<?php echo htmlspecialchars(number_format((float) $nextJackpot, 0, '.', ','), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </aside>

      </div>
    </div>
  </section>

  <!-- =====================================================================
       KEY TAKEAWAYS STRIP
       ===================================================================== -->
  <section class="skai-strip" aria-label="Key takeaways">
    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--horizon">Most active digits</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($mostActiveSummary), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Highest combined appearance counts across all five positions in the last <?php echo (int) $drawRange; ?> draws.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--radiant">Quietest now</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($quietSummary), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Digits sitting furthest from their most recent appearance across all positions in the current window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--success">Latest combo history</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars($latestComboFreqLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">How many times the exact combination <?php echo htmlspecialchars($latestCombo, ENT_QUOTES, 'UTF-8'); ?> appeared previously in the current analysis window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--ember">Window analyzed</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo (int) $drawRange; ?></div>
        <div class="skai-stat-note">Number of draws currently loaded for this page view. Adjust the window in the Tables section.</div>
      </div>
    </article>
  </section>

  <!-- =====================================================================
       TABS NAV
       ===================================================================== -->
  <nav class="skai-tabs" aria-label="Page navigation">
    <a class="skai-tab skai-tab--active" href="#overview">Overview</a>
    <a class="skai-tab" href="#recency-deep-dive">Recency</a>
    <a class="skai-tab" href="#frequency-deep-dive">Frequency</a>
    <a class="skai-tab" href="#tables">Tables</a>
    <a class="skai-tab" href="#tools">Advanced Tools</a>
  </nav>

  <!-- =====================================================================
       OVERVIEW SECTION
       ===================================================================== -->
  <section id="overview" class="skai-section" aria-labelledby="overview-title">
    <div class="skai-section-head">
      <div>
        <h2 id="overview-title" class="skai-section-title">Overview</h2>
        <p class="skai-section-sub">
          A high-level view of digit activity across all five positions. This layer is designed for fast orientation: which digits are most active, which are quiet, and how the frequency distribution looks at a glance.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-overview-grid">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Most active digits
            <span class="skai-card-sub">Combined frequency across all positions in the last <?php echo (int) $drawRange; ?> draws — sorted by activity</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="topActiveChart" aria-label="Most active digits chart" role="img"></canvas>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--ember">
            Quiet stretches
            <span class="skai-card-sub">Digits with the longest combined distance from their last appearance</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="quietChart" aria-label="Quiet digits chart" role="img"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="skai-note">
        <?php echo htmlspecialchars($overviewNote, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  </section>

  <!-- =====================================================================
       RECENCY SECTION
       ===================================================================== -->
  <section id="recency-deep-dive" class="skai-section" aria-labelledby="recency-title">
    <div class="skai-section-head">
      <div>
        <h2 id="recency-title" class="skai-section-title">Recency and draw context</h2>
        <p class="skai-section-sub">
          This section makes the current draw easier to interpret. It shows how recently each digit in the latest draw was previously seen in its specific position, and how the overall digit distribution shifts when comparing a short recent window with a broader historical one.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--radiant">
            Current draw — positional recency
            <span class="skai-card-sub">Previous appearance date and spacing for each latest digit by position</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-history-list">
              <?php foreach ($drawHistoryRows as $hrow) : ?>
                <div class="skai-history-item">
                  <div class="skai-history-name"><?php echo htmlspecialchars((string) $hrow['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="skai-history-date">
                    <?php if (!empty($hrow['prevDate'])) : ?>
                      Previously seen on <?php echo htmlspecialchars(leFmtDateLong((string) $hrow['prevDate']), ENT_QUOTES, 'UTF-8'); ?>
                    <?php else : ?>
                      No previous appearance found in the loaded historical set
                    <?php endif; ?>
                  </div>
                  <div class="skai-history-badge<?php echo $hrow['isBonus'] ? ' skai-history-badge--bonus' : ''; ?>">
                    <?php echo ($hrow['drawsAgo'] !== null) ? (int) $hrow['drawsAgo'] . ' drws ago' : '—'; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--success">
            What changes with the window
            <span class="skai-card-sub">Comparing shorter recent digit behavior with broader historical behavior</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-window-shift">
              <div class="skai-shift-panel">
                <p class="skai-shift-label">Window shift note</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars($windowChangeNarrative, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>

              <div class="skai-shift-panel">
                <p class="skai-shift-label">Recent 50-draw leaders (all positions)</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top50Strs, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>

              <div class="skai-shift-panel">
                <p class="skai-shift-label">Broader 300-draw leaders (all positions)</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top300Strs, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =====================================================================
       FREQUENCY DEEP DIVE SECTION
       ===================================================================== -->
  <section id="frequency-deep-dive" class="skai-section" aria-labelledby="frequency-title">
    <div class="skai-section-head">
      <div>
        <h2 id="frequency-title" class="skai-section-title">Frequency deep dive</h2>
        <p class="skai-section-sub">
          The full digit distribution across all five positions combined, a positional heatmap showing per-position behavior for digits 0&ndash;9, a recency distribution, and<?php echo $extraBallGId ? ' the ' . htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8') . ' distribution.' : ' the complete digit reference.'; ?>
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Full combined digit distribution
            <span class="skai-card-sub">All digits 0&ndash;9 combined across all five positions — last <?php echo (int) $drawRange; ?> draws</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="fullCombinedChart" aria-label="Full combined digit distribution chart" role="img"></canvas>
            </div>

            <h3 style="margin:18px 0 6px;font-size:14px;font-weight:850;color:var(--deep-navy);">Per-position digit heatmap</h3>
            <p style="margin:0 0 10px;font-size:13px;color:var(--text-soft);line-height:1.6;">Each row is a position (P1–P5). Each column is a digit (0–9). Color intensity reflects relative frequency within that position. Blue = high activity, amber = moderate, light = low.</p>

            <div class="le-heatmap-grid" aria-label="Per-position digit heatmap">
              <?php foreach ($posNames as $pidx => $posCol) : ?>
                <?php
                $pf     = $posCounts[$posCol];
                $maxPF  = max($pf) ?: 1;
                ?>
                <div class="le-heatmap-row">
                  <div class="le-heatmap-pos-label"><?php echo htmlspecialchars($posShortNames[$pidx], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php for ($d = 0; $d <= 9; $d++) : ?>
                    <?php
                    $freq  = (int) $pf[$d];
                    $norm  = $freq / $maxPF;
                    $ccls  = ($norm >= 0.67) ? 'le-cell--hot' : (($norm >= 0.33) ? 'le-cell--warm' : 'le-cell--cool');
                    ?>
                    <div class="le-heatmap-cell <?php echo $ccls; ?>" title="P<?php echo $pidx + 1; ?> digit <?php echo $d; ?>: <?php echo $freq; ?> times">
                      <span class="le-cell-digit"><?php echo $d; ?></span>
                      <span class="le-cell-freq"><?php echo $freq; ?></span>
                    </div>
                  <?php endfor; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="skai-grid">
          <?php if ($extraBallGId) : ?>
            <div class="skai-card">
              <div class="skai-card-head skai-card-head--radiant">
                <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> distribution
                <span class="skai-card-sub">Digits 0&ndash;9 across the last <?php echo (int) $drawRange; ?> draws</span>
              </div>
              <div class="skai-card-body">
                <div class="skai-chart-frame skai-chart-frame--md">
                  <canvas id="extraBallChart" aria-label="<?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> distribution chart" role="img"></canvas>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="skai-card">
            <div class="skai-card-head skai-card-head--ember">
              Recency distribution
              <span class="skai-card-sub">Draws since last combined appearance for each digit 0&ndash;9</span>
            </div>
            <div class="skai-card-body">
              <div class="skai-chart-frame skai-chart-frame--md">
                <canvas id="recencyChart" aria-label="Digit recency chart" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="skai-note">
        The combined distribution and per-position heatmap are best used as reference layers. Numbers that appear concentrated in a specific position may behave differently across positions — the heatmap makes that asymmetry visible. The recency chart shows which digits are furthest from their last combined appearance.
      </div>
    </div>
  </section>

  <!-- =====================================================================
       TABLES AND TRACKING SECTION
       ===================================================================== -->
  <section id="tables" class="skai-section" aria-labelledby="tables-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tables-title" class="skai-section-title">Tables and tracked digits</h2>
        <p class="skai-section-sub">
          Use the tables for exact per-position digit counts, recency, and personal tracking. Switch between positions using the tab buttons. Quick filters help narrow the view without losing access to the complete dataset.
        </p>
      </div>
    </div>

    <div class="skai-controls">
      <form name="le-draw-range-form" method="post" action="<?php echo $formAction; ?>">
        <div class="skai-controls-row">
          <div class="skai-controls-left">
            <label for="drawRange">Analysis draw window</label>
            <select name="drawRange" id="drawRange" class="skai-select">
              <?php
              $steps = [10, 25, 50, 75, 100, 150, 200, 300, 400, 500];
              foreach ($steps as $opt) :
                  if ($opt > $totalDrawings) {
                      break;
                  }
              ?>
                <option value="<?php echo (int) $opt; ?>"<?php echo ((int) $opt === (int) $drawRange) ? ' selected="selected"' : ''; ?>>
                  <?php echo (int) $opt; ?> draws
                </option>
              <?php endforeach; ?>
              <?php if (!in_array($totalDrawings, $steps, true) && $totalDrawings >= 10) : ?>
                <option value="<?php echo (int) $totalDrawings; ?>"<?php echo ((int) $totalDrawings === (int) $drawRange) ? ' selected="selected"' : ''; ?>>
                  <?php echo (int) $totalDrawings; ?> draws (all)
                </option>
              <?php endif; ?>
            </select>
          </div>

          <div class="skai-controls-right">
            <button class="skai-button" type="submit" name="le-analyze" value="1">Update analysis window</button>
            <?php echo HTMLHelper::_('form.token'); ?>
          </div>
        </div>
      </form>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">

        <!-- LEFT: Per-Position Tables -->
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Per-position digit frequency
            <span class="skai-card-sub">Select a position to view digit 0–9 counts and recency</span>
          </div>

          <div class="skai-pos-tab-bar" aria-label="Position selector" role="tablist">
            <?php foreach ($posShortNames as $pidx => $pshort) : ?>
              <button
                class="skai-pos-tab<?php echo $pidx === 0 ? ' is-active' : ''; ?>"
                type="button"
                data-pos="<?php echo htmlspecialchars($posNames[$pidx], ENT_QUOTES, 'UTF-8'); ?>"
                role="tab"
                aria-selected="<?php echo $pidx === 0 ? 'true' : 'false'; ?>"
              ><?php echo htmlspecialchars($pshort . ' — ' . $posDisplayNames[$pidx], ENT_QUOTES, 'UTF-8'); ?></button>
            <?php endforeach; ?>
          </div>

          <div class="skai-controls">
            <div class="skai-controls-row">
              <div class="skai-controls-left">
                <div class="skai-filter-group" data-filter-group="position">
                  <button class="skai-filter is-active" type="button" data-filter="all">All digits</button>
                  <button class="skai-filter" type="button" data-filter="active">Most active</button>
                  <button class="skai-filter" type="button" data-filter="quiet">Quietest</button>
                  <button class="skai-filter" type="button" data-filter="recent">Recently seen</button>
                </div>
              </div>
            </div>
          </div>

          <?php foreach ($posNames as $pidx => $posCol) : ?>
            <?php
            $posActive = $posTopActive[$posCol];
            $posQuiet  = $posTopQuiet[$posCol];
            ?>
            <div
              class="js-pos-table"
              data-pos="<?php echo htmlspecialchars($posCol, ENT_QUOTES, 'UTF-8'); ?>"
              style="<?php echo $pidx !== 0 ? 'display:none' : ''; ?>"
              role="tabpanel"
            >
              <div class="skai-table-wrap">
                <table class="skai-table" aria-label="<?php echo htmlspecialchars($posDisplayNames[$pidx], ENT_QUOTES, 'UTF-8'); ?> digit frequency table">
                  <thead>
                    <tr>
                      <th>Digit</th>
                      <th>Drawn Times</th>
                      <th>Last Drawn</th>
                      <th>Track</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php for ($d = 0; $d <= 9; $d++) : ?>
                      <?php
                      $dCount = (int) $posCounts[$posCol][$d];
                      [$lastDrawSort, $lastDrawLabel] = leDrawingsAgoLabel($posLastSeen[$posCol][$d] ?? null, (int) $drawRange);

                      $rowTags = 'all';
                      if (in_array($d, $posActive, true)) {
                          $rowTags .= ' active';
                      }
                      if (in_array($d, $posQuiet, true)) {
                          $rowTags .= ' quiet';
                      }
                      if (($posLastSeen[$posCol][$d] ?? null) !== null && (int) $posLastSeen[$posCol][$d] <= 4) {
                          $rowTags .= ' recent';
                      }

                      $trackId = 'trk-' . $posCol . '-' . $d;
                      ?>
                      <tr data-tags="<?php echo htmlspecialchars($rowTags, ENT_QUOTES, 'UTF-8'); ?>">
                        <td><span class="skai-pill skai-pill--main"><?php echo $d; ?></span></td>
                        <td><?php echo $dCount; ?> X</td>
                        <td data-sort="<?php echo (int) $lastDrawSort; ?>"><?php echo htmlspecialchars($lastDrawLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                          <input
                            class="skai-checkbox js-track-digit"
                            type="checkbox"
                            value="<?php echo $d; ?>"
                            id="<?php echo htmlspecialchars($trackId, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="Track digit <?php echo $d; ?> in <?php echo htmlspecialchars($posDisplayNames[$pidx], ENT_QUOTES, 'UTF-8'); ?>"
                          >
                        </td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="skai-tracked" style="margin:14px 16px 16px">
            <div class="skai-tracked-head">
              <h3 class="skai-tracked-title">Tracked digits</h3>
              <div class="skai-tracked-actions">
                <button class="skai-link-btn" type="button" id="clearDigitTracked">Clear all</button>
              </div>
            </div>
            <div class="skai-chip-wrap" id="digitTrackedWrap">
              <div class="skai-empty">Select digits across any position to build a short tracked set for comparison.</div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Extra Ball + Top Combos -->
        <div class="skai-grid">

          <?php if ($extraBallGId) : ?>
            <div class="skai-card">
              <div class="skai-card-head skai-card-head--radiant">
                <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> frequency table
                <span class="skai-card-sub">Digits 0&ndash;9 across the last <?php echo (int) $drawRange; ?> draws</span>
              </div>

              <div class="skai-table-wrap">
                <table id="skai-extra-table" class="skai-table" aria-label="<?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> frequency table">
                  <thead>
                    <tr>
                      <th>Digit</th>
                      <th>Drawn Times</th>
                      <th>Last Drawn</th>
                      <th>Track</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php for ($d = 0; $d <= 9; $d++) : ?>
                      <?php
                      $eCount = (int) $extraCounts[$d];
                      [$eSort, $eLabel] = leDrawingsAgoLabel($extraLastSeen[$d] ?? null, (int) $drawRange);
                      $eTrackId = 'trk-extra-' . $d;
                      ?>
                      <tr>
                        <td><span class="skai-pill skai-pill--bonus"><?php echo $d; ?></span></td>
                        <td><?php echo $eCount; ?> X</td>
                        <td data-sort="<?php echo (int) $eSort; ?>"><?php echo htmlspecialchars($eLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                          <input
                            class="skai-checkbox js-track-extra"
                            type="checkbox"
                            value="<?php echo $d; ?>"
                            id="<?php echo htmlspecialchars($eTrackId, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="Track <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> digit <?php echo $d; ?>"
                          >
                        </td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>

              <div class="skai-tracked" style="margin:14px 16px 16px">
                <div class="skai-tracked-head">
                  <h3 class="skai-tracked-title">Tracked <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> digits</h3>
                  <div class="skai-tracked-actions">
                    <button class="skai-link-btn" type="button" id="clearExtraTracked">Clear all</button>
                  </div>
                </div>
                <div class="skai-chip-wrap" id="extraTrackedWrap">
                  <div class="skai-empty">Use tracking to keep a small working set visible while comparing modules.</div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="skai-card">
            <div class="skai-card-head skai-card-head--ember">
              Top 10 drawn combinations
              <span class="skai-card-sub">Most frequent 5-digit sequences in the last <?php echo (int) $drawRange; ?> draws</span>
            </div>

            <div class="skai-table-wrap">
              <table class="skai-table" aria-label="Top 10 drawn combinations table">
                <thead>
                  <tr>
                    <th>Combination</th>
                    <th>Drawn Times</th>
                    <th>Last Drawn</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $comboRowN = 0;
                  foreach ($comboCounts as $comboKey => $comboCnt) :
                      $comboDigits = str_split($comboKey);
                      $comboRecency = leComboRecencyLabel($comboKey, $rowsMain, $drawRange);
                  ?>
                    <tr>
                      <td>
                        <span class="skai-combo-pills">
                          <?php foreach ($comboDigits as $cDigit) : ?>
                            <span class="skai-pill skai-pill--main" style="width:28px;height:28px;font-size:12px"><?php echo htmlspecialchars($cDigit, ENT_QUOTES, 'UTF-8'); ?></span>
                          <?php endforeach; ?>
                        </span>
                      </td>
                      <td><?php echo (int) $comboCnt; ?> X</td>
                      <td><?php echo htmlspecialchars($comboRecency, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php
                  $comboRowN++;
                  if ($comboRowN >= 10) {
                      break;
                  }
                  endforeach;
                  ?>
                </tbody>
              </table>
            </div>

            <div class="skai-note" style="margin:14px 16px 16px">
              Tracking is local to this page view. It is intended as a lightweight comparison aid while you move between the overview, tables, and advanced SKAI tools.
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <!-- =====================================================================
       ADVANCED TOOLS SECTION
       ===================================================================== -->
  <section id="tools" class="skai-section" aria-labelledby="tools-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tools-title" class="skai-section-title">Next steps and advanced tools</h2>
        <p class="skai-section-sub">
          The digit frequency page establishes context. These tools take that context into deeper modeling and structured exploration for <?php echo htmlspecialchars($stateName . ' ' . $lotteryName, ENT_QUOTES, 'UTF-8'); ?>.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-tool-grid">
        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--horizon">SKAI Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Best next step for a broader multi-signal view. Use this after reviewing digit frequency and recency to move into the main SKAI intelligence workflow.
            </p>
            <a class="skai-tool-cta" href="<?php echo $hrefSKAI; ?>">Open SKAI Analysis</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--radiant">AI Predictions</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Use when you want a model-driven complement to the historical view shown on this page.
            </p>
            <a class="skai-tool-cta" href="<?php echo $hrefAI; ?>">Open AI Predictions</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--success">Skip &amp; Hit Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Useful for comparing digit appearance spacing and interruption behavior after reviewing current frequency and recency.
            </p>
            <a class="skai-tool-cta" href="<?php echo $hrefSkipHit; ?>">Open Skip &amp; Hit</a>
          </div>
        </article>
      </div>

      <div class="skai-utility-grid">
        <a class="skai-utility-link" href="<?php echo $hrefMCMC; ?>">MCMC Markov Analysis</a>
        <a class="skai-utility-link" href="<?php echo $hrefHeatmap; ?>">Heatmap Analysis</a>
        <a class="skai-utility-link" href="<?php echo $hrefArchive; ?>">Lottery Archives</a>
        <a class="skai-utility-link" href="<?php echo $hrefLowest; ?>">Lowest Number Analysis</a>
      </div>
    </div>
  </section>

  <!-- =====================================================================
       METHOD NOTE
       ===================================================================== -->
  <section class="skai-section" aria-labelledby="method-title">
    <div class="skai-section-head">
      <div>
        <h2 id="method-title" class="skai-section-title">Method note</h2>
        <p class="skai-section-sub">
          This page is designed to help users understand recent and historical digit behavior more clearly, not to imply certainty about future draws.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-method-note">
        <strong>Interpretation guidance:</strong> Digit frequency, positional recency, and combination history can provide useful context for reviewing draw history, but they should be treated as descriptive signals rather than guarantees. The <?php echo htmlspecialchars($stateName . ' ' . $lotteryName, ENT_QUOTES, 'UTF-8'); ?> is a Pick 5 daily game where each of the five positions independently draws a single digit from 0 to 9. Each position's outcome is independent of the others, and each draw is statistically independent of prior draws. The purpose of this page is to make recent behavior easier to understand, compare, and carry into deeper SKAI analysis.
      </div>
    </div>
  </section>

</div>

<script type="text/javascript">
(function () {
  'use strict';

  var chartData = {
    topActiveLabels: <?php echo json_encode(array_values($topActiveLabels)); ?>,
    topActiveValues: <?php echo json_encode(array_values($topActiveValues)); ?>,
    quietLabels:     <?php echo json_encode(array_values($quietestLabels)); ?>,
    quietValues:     <?php echo json_encode(array_values($quietestValues)); ?>,
    combinedLabels:  <?php echo json_encode(array_values($combinedLabels)); ?>,
    combinedValues:  <?php echo json_encode(array_values($combinedValues)); ?>,
    recencyLabels:   <?php echo json_encode(array_values($combinedLabels)); ?>,
    recencyValues:   <?php echo json_encode(array_values($recencyChartValues)); ?>,
    extraLabels:     <?php echo json_encode(array_values($extraChartLabels)); ?>,
    extraValues:     <?php echo json_encode(array_values($extraChartValues)); ?>,
    hasExtra:        <?php echo $extraBallGId ? 'true' : 'false'; ?>
  };

  /* ------------------------------------------------------------------
   * Chart.js loader
   * ------------------------------------------------------------------ */
  function loadChartJsIfNeeded(done) {
    if (window.Chart) {
      done();
      return;
    }

    var cdnUrl   = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    var integrity = 'sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb';

    function tryLoad(withIntegrity) {
      var script    = document.createElement('script');
      script.src    = cdnUrl;
      script.async  = true;

      if (withIntegrity) {
        script.integrity    = integrity;
        script.crossOrigin  = 'anonymous';
      }

      script.onload = function () {
        done();
      };

      script.onerror = function () {
        if (withIntegrity) {
          tryLoad(false);
        } else {
          done();
        }
      };

      document.head.appendChild(script);
    }

    tryLoad(true);
  }

  /* ------------------------------------------------------------------
   * Common chart options
   * ------------------------------------------------------------------ */
  function commonBarOptions(horizontal) {
    return {
      responsive:          true,
      maintainAspectRatio: false,
      indexAxis:           horizontal ? 'y' : 'x',
      animation:           false,
      plugins: {
        legend:  { display: false },
        tooltip: { enabled: true }
      },
      scales: horizontal ? {
        x: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid:  { color: 'rgba(10,26,51,.08)' }
        },
        y: {
          ticks: { autoSkip: false, font: { weight: '700' } },
          grid:  { display: false }
        }
      } : {
        x: {
          ticks: { font: { weight: '700' } },
          grid:  { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid:  { color: 'rgba(10,26,51,.08)' }
        }
      }
    };
  }

  /* ------------------------------------------------------------------
   * Render all charts
   * ------------------------------------------------------------------ */
  function renderCharts() {
    if (!window.Chart) {
      return;
    }

    var topActiveCanvas   = document.getElementById('topActiveChart');
    var quietCanvas       = document.getElementById('quietChart');
    var fullCombCanvas    = document.getElementById('fullCombinedChart');
    var extraCanvas       = document.getElementById('extraBallChart');
    var recencyCanvas     = document.getElementById('recencyChart');

    if (topActiveCanvas) {
      new Chart(topActiveCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.topActiveLabels,
          datasets: [{
            data:            chartData.topActiveValues,
            borderWidth:     0,
            borderRadius:    8,
            backgroundColor: '#1C66FF'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (quietCanvas) {
      new Chart(quietCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.quietLabels,
          datasets: [{
            data:            chartData.quietValues,
            borderWidth:     0,
            borderRadius:    8,
            backgroundColor: '#F5A623'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (fullCombCanvas) {
      new Chart(fullCombCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.combinedLabels,
          datasets: [{
            data:            chartData.combinedValues,
            borderWidth:     0,
            borderRadius:    8,
            backgroundColor: '#1C66FF'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (extraCanvas && chartData.hasExtra) {
      new Chart(extraCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.extraLabels,
          datasets: [{
            data:            chartData.extraValues,
            borderWidth:     0,
            borderRadius:    8,
            backgroundColor: '#8F1F2D'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (recencyCanvas) {
      new Chart(recencyCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.recencyLabels,
          datasets: [{
            data:            chartData.recencyValues,
            borderWidth:     0,
            borderRadius:    8,
            backgroundColor: '#F5A623'
          }]
        },
        options: {
          responsive:          true,
          maintainAspectRatio: false,
          animation:           false,
          plugins: {
            legend:  { display: false },
            tooltip: { enabled: true }
          },
          scales: {
            x: {
              ticks: {
                maxRotation:    0,
                autoSkip:       true,
                maxTicksLimit:  10,
                font:           { weight: '700' }
              },
              grid: { display: false }
            },
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0,
                font:      { weight: '700' }
              },
              grid: { color: 'rgba(10,26,51,.08)' }
            }
          }
        }
      });
    }
  }

  /* ------------------------------------------------------------------
   * Position tabs
   * ------------------------------------------------------------------ */
  function bindPositionTabs() {
    var tabs   = document.querySelectorAll('.skai-pos-tab');
    var panels = document.querySelectorAll('.js-pos-table');

    if (!tabs.length) {
      return;
    }

    function showPos(posName) {
      for (var i = 0; i < panels.length; i++) {
        panels[i].style.display = (panels[i].getAttribute('data-pos') === posName) ? '' : 'none';
      }

      for (var j = 0; j < tabs.length; j++) {
        var active = (tabs[j].getAttribute('data-pos') === posName);
        if (active) {
          tabs[j].classList.add('is-active');
          tabs[j].setAttribute('aria-selected', 'true');
        } else {
          tabs[j].classList.remove('is-active');
          tabs[j].setAttribute('aria-selected', 'false');
        }
      }
    }

    for (var k = 0; k < tabs.length; k++) {
      tabs[k].addEventListener('click', function () {
        showPos(this.getAttribute('data-pos'));
      });
    }

    if (tabs.length > 0) {
      showPos(tabs[0].getAttribute('data-pos'));
    }
  }

  /* ------------------------------------------------------------------
   * Table filter (applies to the currently visible position table)
   * ------------------------------------------------------------------ */
  function bindPositionFilters() {
    var group = document.querySelector('[data-filter-group="position"]');

    if (!group) {
      return;
    }

    var buttons = group.querySelectorAll('.skai-filter');

    function applyFilter(filter) {
      var panels = document.querySelectorAll('.js-pos-table');

      for (var t = 0; t < panels.length; t++) {
        if (panels[t].style.display === 'none') {
          continue;
        }

        var rows = panels[t].querySelectorAll('tbody tr');

        for (var i = 0; i < rows.length; i++) {
          var tags = rows[i].getAttribute('data-tags') || '';

          if (filter === 'all' || tags.indexOf(filter) !== -1) {
            rows[i].style.display = '';
          } else {
            rows[i].style.display = 'none';
          }
        }
      }

      for (var j = 0; j < buttons.length; j++) {
        buttons[j].classList.remove('is-active');

        if (buttons[j].getAttribute('data-filter') === filter) {
          buttons[j].classList.add('is-active');
        }
      }
    }

    for (var k = 0; k < buttons.length; k++) {
      buttons[k].addEventListener('click', function () {
        applyFilter(this.getAttribute('data-filter'));
      });
    }
  }

  /* ------------------------------------------------------------------
   * Tracked digits UI
   * ------------------------------------------------------------------ */
  function bindTrackers() {
    var digitWrap = document.getElementById('digitTrackedWrap');
    var extraWrap = document.getElementById('extraTrackedWrap');
    var clearDigit = document.getElementById('clearDigitTracked');
    var clearExtra = document.getElementById('clearExtraTracked');

    function renderTracked(selector, wrap, chipClass, emptyText) {
      if (!wrap) {
        return;
      }

      var inputs = document.querySelectorAll(selector);
      var seen   = {};
      var items  = [];

      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked) {
          var v = inputs[i].value;

          if (!seen[v]) {
            seen[v] = true;
            items.push(v);
          }
        }
      }

      wrap.innerHTML = '';

      if (!items.length) {
        var empty = document.createElement('div');
        empty.className   = 'skai-empty';
        empty.textContent = emptyText;
        wrap.appendChild(empty);
        return;
      }

      for (var j = 0; j < items.length; j++) {
        var chip = document.createElement('span');
        chip.className   = 'skai-chip ' + chipClass;
        chip.textContent = items[j];
        wrap.appendChild(chip);
      }
    }

    function bindGroup(selector, wrap, chipClass, emptyText, clearBtn) {
      var inputs = document.querySelectorAll(selector);

      for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function () {
          renderTracked(selector, wrap, chipClass, emptyText);
        });
      }

      renderTracked(selector, wrap, chipClass, emptyText);

      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          var all = document.querySelectorAll(selector);

          for (var k = 0; k < all.length; k++) {
            all[k].checked = false;
          }

          renderTracked(selector, wrap, chipClass, emptyText);
        });
      }
    }

    bindGroup(
      '.js-track-digit',
      digitWrap,
      'skai-chip--main',
      'Select digits across any position to build a short tracked set for comparison.',
      clearDigit
    );

    bindGroup(
      '.js-track-extra',
      extraWrap,
      'skai-chip--bonus',
      'Use tracking to keep a small working set visible while comparing modules.',
      clearExtra
    );
  }

  /* ------------------------------------------------------------------
   * Anchor tab highlight
   * ------------------------------------------------------------------ */
  function initAnchors() {
    var tabs = document.querySelectorAll('.skai-tab');

    if (!tabs.length) {
      return;
    }

    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function () {
        for (var j = 0; j < tabs.length; j++) {
          tabs[j].classList.remove('skai-tab--active');
        }

        this.classList.add('skai-tab--active');
      });
    }
  }

  /* ------------------------------------------------------------------
   * Bootstrap
   * ------------------------------------------------------------------ */
  function init() {
    bindPositionTabs();
    bindPositionFilters();
    bindTrackers();
    initAnchors();
    loadChartJsIfNeeded(renderCharts);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
</script>