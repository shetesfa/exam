<?php
require_once 'db.php';

if (!isLoggedIn() && !isStudent()) {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

// Determine class targeting for the logged in viewer
$my_class_ids = [];
if ($role === 'teacher') {
    $rows = dbFetchAll($conn, "SELECT DISTINCT class_id FROM teacher_class WHERE teacher_id = ?", "i", [$user_id]);
    $my_class_ids = array_column($rows, 'class_id');
} elseif ($role === 'attendance_submitter') {
    $rows = dbFetchAll($conn, "SELECT DISTINCT class_id FROM attendance_assignments WHERE submitter_id = ?", "i", [$user_id]);
    $my_class_ids = array_column($rows, 'class_id');
} elseif (isStudent()) {
    $srow = dbFetchOne($conn, "SELECT class_id FROM students WHERE id = ?", "i", [$_SESSION['student_id']]);
    if ($srow) $my_class_ids = [intval($srow['class_id'])];
}
$class_ids_sql = !empty($my_class_ids) ? implode(',', array_map('intval', $my_class_ids)) : '0';

// Ethiopian calendar helpers
$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

$greg_months_am = [
    1 => 'ጃንዋሪ', 2 => 'ፌብሩዋሪ', 3 => 'ማርች', 4 => 'ኤፕሪል',
    5 => 'ሜይ', 6 => 'ጁን', 7 => 'ጁላይ', 8 => 'ኦገስት',
    9 => 'ሴፕቴምበር', 10 => 'ኦክቶበር', 11 => 'ኖቬምበር', 12 => 'ዲሴምበር'
];

if (!function_exists('toEthiopicNumber')) {
    function toEthiopicNumber($num) {
        $geez = [
            1=>'፩', 2=>'፪', 3=>'፫', 4=>'፬', 5=>'፭', 6=>'፮', 7=>'፯', 8=>'፰', 9=>'፱', 10=>'፲',
            11=>'፲፩', 12=>'፲፪', 13=>'፲፫', 14=>'፲፬', 15=>'፲፭', 16=>'፲፮', 17=>'፲፯', 18=>'፲፰', 19=>'፲፱', 20=>'፳',
            21=>'፳፩', 22=>'፳፪', 23=>'፳፫', 24=>'፳፬', 25=>'፳፭', 26=>'፳፮', 27=>'፳፯', 28=>'፳፰', 29=>'፳፱', 30=>'፴'
        ];
        return $geez[$num] ?? (string)$num;
    }
}

if (!function_exists('toEthiopicYear')) {
    function toEthiopicYear($year) {
        if ($year >= 2000 && $year < 2100) {
            $rem = $year - 2000;
            $geez_digits = [
                0 => '', 1 => '፩', 2 => '፪', 3 => '፫', 4 => '፬', 5 => '፭', 6 => '፮', 7 => '፯', 8 => '፰', 9 => '፱',
                10 => '፲', 11 => '፲፩', 12 => '፲፪', 13 => '፲፫', 14 => '፲፬', 15 => '፲፭', 16 => '፲፮', 17 => '፲፯', 18 => '፲፰', 19 => '፲፱',
                20 => '፳', 21 => '፳፩', 22 => '፳፪', 23 => '፳፫', 24 => '፳፬', 25 => '፳፭', 26 => '፳፮', 27 => '፳፯', 28 => '፳፰', 29 => '፳፱', 30 => '፴'
            ];
            return '፳፻' . ($geez_digits[$rem] ?? (string)$rem);
        }
        return (string)$year;
    }
}

if (!function_exists('getEvangelistName')) {
    function getEvangelistName($year) {
        $rem = $year % 4;
        switch ($rem) {
            case 0: return 'ዘመነ ዮሐንስ';
            case 1: return 'ዘመነ ማቴዎስ';
            case 2: return 'ዘመነ ማርቆስ';
            case 3: return 'ዘመነ ሉቃስ';
        }
        return '';
    }
}

// 30 Daily Commemorative Saints in the Ethiopian Orthodox Church Calendar
// (Day 3 set to ቅዱስ ሩፋኤል as celebrated in this church)
$daily_saints = [
    1  => 'ልደታ ለማርያም / ቅዱስ ራጉኤል',
    2  => 'ታዴዎስ ሐዋርያ / ኢዮብ ጻድቅ',
    3  => 'ቅዱስ ሩፋኤል / በዓታ ለማርያም',
    4  => 'ዮሐንስ ወልደ ነጎድጓድ',
    5  => 'አቡነ ገብረ መንፈስ ቅዱስ / ቅዱስ ጴጥሮስ ወጳውሎስ',
    6  => 'ደብረ ቁስቋም / ቅድስት አርሴማ',
    7  => 'ቅድስት ሥላሴ / አባ ጊዮርጊስ ዘጋስጫ',
    8  => 'አባ ኪሮስ / አባ ብሦይ',
    9  => 'ሠለስቱ ምዕት (፫፻፲፰ቱ ሊቃውንት) / ቶማስ ሐዋርያ',
    10 => 'መስቀለ ክርስቶስ',
    11 => 'ቅዱስ ፋሲለደስ ሰማዕት / ሐና ወኢያቄም',
    12 => 'ቅዱስ ሚካኤል ሊቀ መላእክት',
    13 => 'እግዚአብሔር አብ / ቅዱስ ሩፋኤል',
    14 => 'አቡነ አረጋዊ / ገብረ ክርስቶስ',
    15 => 'ቅዱስ ቂርቆስና እናቱ ቅድስት ኢየሉጣ',
    16 => 'ኪዳነ ምሕረት',
    17 => 'ቅዱስ እስጢፋኖስ / ያዕቆብ ሐዋርያ',
    18 => 'አቡነ ኤዎስጣቴዎስ / ፊልጶስ ሐዋርያ',
    19 => 'ቅዱስ ገብርኤል ሊቀ መላእክት',
    20 => 'ሕንፀተ ቤተክርስቲያን / ነቢዩ ኤልሳዕ',
    21 => 'እመቤታችን ቅድስት ድንግል ማርያም',
    22 => 'ቅዱስ ዑራኤል ሊቀ መላእክት / ቅዱስ ደቅስዮስ',
    23 => 'ቅዱስ ጊዮርጊስ ሊቀ ሰማዕታት',
    24 => 'አቡነ ተክለ ሃይማኖት',
    25 => 'ቅዱስ መርቆሬዎስ ሰማዕት',
    26 => 'አቡነ ሐብተ ማርያም / ቶማስ ዘመርዓስ',
    27 => 'መድኃኔዓለም / ሕዝቅኤል ነቢይ',
    28 => 'አማኑኤል / ቴዎድሮስ ባኔድሮስ',
    29 => 'በዓለ ወልድ / እግዚእነ ኢየሱስ ክርስቶስ',
    30 => 'ቅዱስ ዮሐንስ መጥምቅ / ቅዱስ ማርቆስ ወንጌላዊ'
];

// Special Church Feast Days for Atsede Tiguhan Sunday School
// Highlighted with reverent Orthodox spiritual icons and golden prominence
$church_special_feasts = [
    3  => ['name' => 'ቅዱስ ሩፋኤል', 'icon' => '🕊️'],
    16 => ['name' => 'ኪዳነ ምሕረት', 'icon' => '👑'],
    21 => ['name' => 'እመቤታችን ቅድስት ድንግል ማርያም', 'icon' => '🌸'],
    23 => ['name' => 'ቅዱስ ጊዮርጊስ', 'icon' => '☦️'],
    26 => ['name' => 'አቡነ ሐብተ ማርያም', 'icon' => '🕯️'],
    27 => ['name' => 'መድኃኔዓለም', 'icon' => '✝️']
];

// Canonical Major Annual Orthodox Feasts (ዓመታዊ የቤተክርስቲያን በዓላት በ13ቱም አውደ አኅዋራት)
$annual_holidays = [
    // 1. መስከረም
    '1-1'  => '🌟 ርእሰ ዐውደ ዓመት (ቅዱስ ዮሐንስ / እንቁጣጣሽ)',
    '1-10' => '✝️ ጾመ ዮዲት (የመስቀል ጾም መጀመሪያ)',
    '1-16' => '🔥 ደመራ (የመስቀል ዋዜማ)',
    '1-17' => '✝️ በዓለ መስቀል (ቅዱስ መስቀል የተገኘበት)',
    '1-21' => '✝️ በዓለ ግማደ መስቀል (የቅዱስ መስቀል ማጠቃለያ)',
    
    // 2. ጥቅምት
    '2-5'  => '🕊️ አቡነ ገብረ መንፈስ ቅዱስ (ተሰጥዎ)',
    '2-14' => '🕊️ አቡነ አረጋዊ (ስውረታቸው / በዓለ ዕረፍት)',
    '2-24' => '🕊️ አቡነ ተክለ ሃይማኖት (ፍልሰተ አጽም)',
    '2-27' => '✝️ ጥቅምት መድኃኔዓለም (ለኖኅ ቃል ኪዳን የገባበት)',
    
    // 3. ኅዳር
    '3-6'  => '🌸 ደብረ ቁስቋም (የስደት ፍጻሜ)',
    '3-8'  => '🕊️ አሥራ ሁለቱ አበው ነቢያት',
    '3-9'  => '📜 ሠለስቱ ምዕት (፫፻፲፰ቱ የኒቂያ ሊቃውንት)',
    '3-12' => '🕊️ ቅዱስ ሚካኤል (ኅዳር ሚካኤል)',
    '3-15' => '🌸 ጾመ ነቢያት (የገና ጾም ዋዜማ)',
    '3-16' => '🌸 ጾመ ነቢያት (የገና ጾም መግቢያ)',
    '3-21' => '🌸 ጽዮን ማርያም (ታቦተ ጽዮን ወደ ኢትዮጵያ የገባችበት)',
    '3-24' => '👑 ሃያ አራቱ ካህናተ ሰማይ',
    '3-25' => '☦️ ቅዱስ መርቆሬዎስ ሰማዕት',
    
    // 4. ታኅሣሥ
    '4-3'  => '🌸 በዓታ ለማርያም (እመቤታችን ወደ ቤተ መቅደስ የገባችበት)',
    '4-19' => '🕊️ ቅዱስ ገብርኤል ሊቀ መላእክት (ታኅሣሥ ገብርኤል)',
    '4-22' => '👑 ቅዱስ ደቅስዮስ',
    '4-24' => '🕯️ አቡነ ተክለ ሃይማኖት (ልደታቸው)',
    '4-28' => '✝️ በዓለ አማኑኤል (በዘመነ ዮሐንስ የገና ዋዜማ)',
    '4-29' => '🌟 በዓለ ልደት (ገና - የጌታችን ልደት)',
    
    // 5. ጥር
    '5-6'  => '👑 ግዝረተ ክርስቶስ (የጌታችን ግዝረት)',
    '5-11' => '🕊️ በዓለ ጥምቀት (የጌታችን መጠመቅ)',
    '5-12' => '🌟 ቃና ዘገሊላ / ቅዱስ ሚካኤል',
    '5-18' => '☦️ ቅዱስ ጊዮርጊስ (ፍልሰተ አጽም)',
    '5-21' => '🌸 አስተርእዮ ማርያም (ዕረፍተ ድንግል)',
    '5-22' => '🕊️ ቅዱስ ዑራኤል ሊቀ መላእክት',
    
    // 6. የካቲት
    '6-8'  => '🕯️ አባ ኪሮስ',
    '6-16' => '🌸 ኪዳነ ምሕረት (ታላቁ የቃል ኪዳን በዓል)',
    '6-23' => '☦️ ቅዱስ ጊዮርጊስ',
    
    // 7. መጋቢት
    '7-10' => '✝️ ተረክቦተ መስቀል (ንግሥት ዕሌኒ መስቀሉን ያገኘችበት)',
    '7-27' => '✝️ መጋቢት መድኃኔዓለም (መታሰቢያ ስቅለተ ክርስቶስ)',
    '7-29' => '🌟 በዓለ ጽንሰት (የጌታችን ጽንሰት)',
    
    // 8. ሚያዝያ
    '8-23' => '☦️ ቅዱስ ጊዮርጊስ (ሰማዕትነቱ / ዕረፍቱ - ታላቁ በዓል)',
    
    // 9. ግንቦት
    '9-1'  => '🌸 ልደታ ለማርያም (የእመቤታችን ልደት - ግንቦት ልደታ)',
    '9-12' => '🕊️ ቅዱስ ሚካኤል ሊቀ መላእክት (ግንቦት ሚካኤል)',
    '9-20' => '🌸 ጽንሰታ ለማርያም (ኢያቄምና ሐና እመቤታችንን የፀነሱበት)',
    '9-21' => '🌸 አስተርእዮተ ማርያም (የእመቤታችን በብርሃን መገለጥ)',
    
    // 10. ሰኔ
    '10-12' => '🕊️ ቅዱስ ሚካኤል (ሰኔ ሚካኤል - ባሕራንን ያዳነበት)',
    '10-20' => '⛪ ሕንፀተ ቤተክርስቲያን (የመጀመሪያዋ ቤተክርስቲያን የታነጸችበት)',
    '10-30' => '🕊️ ቅዱስ ዮሐንስ መጥምቅ (ልደቱ) / ቅዱስ ማርቆስ ወንጌላዊ (ዕረፍቱ)',
    
    // 11. ሐምሌ
    '11-5'  => '👑 ቅዱሳን ጴጥሮስ ወጳውሎስ (ሰማዕትነታቸው / የሐዋርያት ጾም ፍጻሜ)',
    '11-7'  => '✝️ ቅድስት ሥላሴ (ሐምሌ ሥላሴ - አብርሃም ሥላሴን ያስተናገደበት)',
    '11-19' => '👑 ቅዱስ ቂርቆስና እናቱ ቅድስት ኢየሉጣ (ሰማዕትነታቸው)',
    
    // 12. ነሐሴ
    '12-1'  => '🌸 ጾመ ፍልሰታ መጀመሪያ',
    '12-13' => '🌟 ደብረ ታቦር (ቡሄ / ብርሃነ መለኮት)',
    '12-16' => '🌸 ፍልሰታ ለማርያም (ዕርገተ ሥጋዋ ለእመቤታችን)',
    '12-24' => '🕯️ አቡነ ተክለ ሃይማኖት (ዕረፍታቸው)',
    
    // 13. ጳጉሜን
    '13-3'  => '🕊️ ቅዱስ ሩፋኤል ሊቀ መላእክት',
    '13-6'  => '🌟 ጳጉሜን 6 (በዘመነ ዮሐንስ መታሰቢያ)'
];

// Event Type Metadata
$event_type_meta = [
    'exam'        => ['label' => '📝 ፈተና', 'badge_class' => 'pill-exam'],
    'teaching'    => ['label' => '📚 ትምህርት', 'badge_class' => 'pill-teaching'],
    'revision'    => ['label' => '📖 ክለሳ', 'badge_class' => 'pill-revision'],
    'holiday'     => ['label' => '🎉 በዓል', 'badge_class' => 'pill-holiday'],
    'church'      => ['label' => '⛪ የበዓል ቀን', 'badge_class' => 'pill-church'],
    'meeting'     => ['label' => '👨‍🏫 ስብሰባ', 'badge_class' => 'pill-meeting'],
    'assessment'  => ['label' => '📋 ምዘና', 'badge_class' => 'pill-assessment'],
    'announcement'=> ['label' => '📢 ማስታወቂያ', 'badge_class' => 'pill-announcement'],
    'important'   => ['label' => '⚠️ አስፈላጊ', 'badge_class' => 'pill-important'],
];

// Current Date & User Selection
$today_eth = getCurrentEthiopianDate();
$selected_eth_month = isset($_GET['m']) ? intval($_GET['m']) : intval($today_eth['month']);
$selected_eth_year = isset($_GET['y']) ? intval($_GET['y']) : intval($today_eth['year']);
$view_mode = in_array($_GET['v'] ?? '', ['list', 'week', 'month']) ? $_GET['v'] : 'month';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

if ($selected_eth_month < 1 || $selected_eth_month > 13) {
    $selected_eth_month = intval($today_eth['month']);
}
if ($selected_eth_year < 2000 || $selected_eth_year > 2100) {
    $selected_eth_year = intval($today_eth['year']);
}

// Navigation calculations for months
$prev_m = $selected_eth_month - 1; $prev_y = $selected_eth_year;
if ($prev_m < 1) { $prev_m = 13; $prev_y--; }
$next_m = $selected_eth_month + 1; $next_y = $selected_eth_year;
if ($next_m > 13) { $next_m = 1; $next_y++; }

if (!function_exists('buildCalUrl')) {
    function buildCalUrl($m, $y, $v = null, $type = null, $d = null) {
        global $view_mode, $filter_type;
        $vm = $v !== null ? $v : $view_mode;
        $ft = $type !== null ? $type : $filter_type;
        $url = "calendar_view.php?m={$m}&y={$y}&v={$vm}";
        if ($ft && $ft !== 'all') $url .= "&type=" . urlencode($ft);
        if ($d !== null) $url .= "&d=" . intval($d);
        return $url;
    }
}

// Days in current, previous and next months
$days_in_month = getEthiopianDaysInMonth($selected_eth_year, $selected_eth_month);
$days_in_prev_month = getEthiopianDaysInMonth($prev_y, $prev_m);

// Gregorian boundaries for this Ethiopian month
$first_day_greg = ethiopianToGregorian($selected_eth_year, $selected_eth_month, 1);
$last_day_greg = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $days_in_month);

// Day of week of Ethiopian day 1 (0 = Sunday ... 6 = Saturday)
$first_day_dow = intval(date('w', strtotime($first_day_greg)));

// Gregorian subtitle range
$greg_start_ts = strtotime($first_day_greg);
$greg_end_ts = strtotime($last_day_greg);
$greg_range_str = ($greg_months_am[intval(date('n', $greg_start_ts))] ?? date('M', $greg_start_ts)) . ' ' . date('j', $greg_start_ts) . ' – ' .
                  ($greg_months_am[intval(date('n', $greg_end_ts))] ?? date('M', $greg_end_ts)) . ' ' . date('j, Y', $greg_end_ts) .
                  ' (' . date('M j', $greg_start_ts) . ' – ' . date('M j, Y', $greg_end_ts) . ')';

// Query Events for this month
// Query All Events for this month (Unfiltered for accurate category statistics)
$raw_events_sql = "SELECT DISTINCT e.*, 
                d.name_am AS target_division, g.name_am AS target_grade, c.name AS target_class
               FROM calendar_events e
               LEFT JOIN calendar_event_targets t ON t.event_id = e.id
               LEFT JOIN divisions d ON t.division_id = d.id
               LEFT JOIN grades g ON t.grade_id = g.id
               LEFT JOIN classes c ON t.class_id = c.id
               WHERE e.is_deleted = 0
                 AND (
                     (e.ethiopian_year = ? AND e.ethiopian_month = ?)
                     OR (e.event_date BETWEEN ? AND ?)
                 )";

$raw_params = [$selected_eth_year, $selected_eth_month, $first_day_greg, $last_day_greg];
$raw_types = "iiss";

if ($role !== 'admin' && !empty($my_class_ids)) {
    $raw_events_sql .= " AND (t.id IS NULL OR t.class_id IN ($class_ids_sql)
                         OR t.grade_id IN (SELECT grade_id FROM classes WHERE id IN ($class_ids_sql))
                         OR t.division_id IN (SELECT g.division_id FROM grades g JOIN classes c ON c.grade_id = g.id WHERE c.id IN ($class_ids_sql)))";
}

$raw_events_sql .= " ORDER BY e.event_date ASC, e.priority DESC, e.id ASC";
$raw_month_events = dbFetchAll($conn, $raw_events_sql, $raw_types, $raw_params);

// Canonical Major Annual Church Feasts for this month
$month_annual_holidays = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $hkey = "{$selected_eth_month}-{$d}";
    if (!empty($annual_holidays[$hkey])) {
        $greg_holiday_date = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
        $month_annual_holidays[] = [
            'id' => 'annual_' . $selected_eth_month . '_' . $d,
            'title' => $annual_holidays[$hkey],
            'event_type' => 'holiday',
            'event_date' => $greg_holiday_date,
            'ethiopian_day' => $d,
            'ethiopian_month' => $selected_eth_month,
            'ethiopian_year' => $selected_eth_year,
            'description' => 'የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ዓመታዊ ክብረ በዓል',
            'priority' => 'normal',
            'target_division' => '',
            'target_grade' => '',
            'target_class' => '',
            'is_annual_feast' => true
        ];
    }
}
$annual_holiday_count = count($month_annual_holidays);

// Calculate accurate category statistics for header pills and footer
$exam_count = 0; $db_holiday_count = 0; $teaching_count = 0;
foreach ($raw_month_events as $ev) {
    if ($ev['event_type'] === 'exam') $exam_count++;
    if ($ev['event_type'] === 'holiday' || $ev['event_type'] === 'church') $db_holiday_count++;
    if ($ev['event_type'] === 'teaching' || $ev['event_type'] === 'revision') $teaching_count++;
}
$holiday_count = $annual_holiday_count + $db_holiday_count;
$total_events_month = count($raw_month_events) + $annual_holiday_count;

// Filtered events for Month Grid view
$month_events = [];
foreach ($raw_month_events as $ev) {
    if ($filter_type === 'all') {
        $month_events[] = $ev;
    } elseif ($filter_type === 'exam' && $ev['event_type'] === 'exam') {
        $month_events[] = $ev;
    } elseif ($filter_type === 'holiday' && ($ev['event_type'] === 'holiday' || $ev['event_type'] === 'church')) {
        $month_events[] = $ev;
    } elseif ($filter_type === 'teaching' && ($ev['event_type'] === 'teaching' || $ev['event_type'] === 'revision')) {
        $month_events[] = $ev;
    }
}

// Group events by Ethiopian day for calendar cells
$events_by_day = [];
foreach ($month_events as $ev) {
    $ed = intval($ev['ethiopian_day'] ?? 0);
    if (!$ed && !empty($ev['event_date'])) {
        $conv = getEthiopianDateFromGregorian($ev['event_date']);
        $ed = intval($conv['day']);
    }
    if ($ed >= 1 && $ed <= $days_in_month) {
        $events_by_day[$ed][] = $ev;
    }
}

// Build Agenda / List View Items (Combines church annual feasts and custom events)
$agenda_events = [];
if ($filter_type === 'all' || $filter_type === 'holiday') {
    foreach ($month_annual_holidays as $ah) {
        $agenda_events[] = $ah;
    }
}
foreach ($month_events as $ev) {
    $agenda_events[] = $ev;
}

// Sort Agenda items chronologically by day
usort($agenda_events, function($a, $b) {
    $day_a = intval($a['ethiopian_day'] ?? 0);
    $day_b = intval($b['ethiopian_day'] ?? 0);
    if ($day_a === $day_b) {
        $pri_order = ['urgent' => 3, 'high' => 2, 'normal' => 1];
        $pa = $pri_order[$a['priority'] ?? 'normal'] ?? 1;
        $pb = $pri_order[$b['priority'] ?? 'normal'] ?? 1;
        return $pb <=> $pa;
    }
    return $day_a <=> $day_b;
});

// Attendance / School Days status from attendance_days
$attendance_rows = dbFetchAll(
    $conn,
    "SELECT * FROM attendance_days WHERE date_gregorian BETWEEN ? AND ? ORDER BY date_gregorian ASC",
    "ss",
    [$first_day_greg, $last_day_greg]
);
$attendance_by_date = [];
foreach ($attendance_rows as $ar) {
    $gdate = $ar['date_gregorian'];
    if (!isset($attendance_by_date[$gdate]) || !empty($ar['class_id'])) {
        $attendance_by_date[$gdate] = $ar;
    }
}

$is_selected_current_month = ($selected_eth_year == $today_eth['year'] && $selected_eth_month == $today_eth['month']);
$nav_active = 'calendar_view';

// Week View Calculations
$selected_day = isset($_GET['d']) ? intval($_GET['d']) : ($is_selected_current_month ? intval($today_eth['day']) : 1);
if ($selected_day < 1 || $selected_day > $days_in_month) $selected_day = 1;

$selected_day_greg = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $selected_day);
$selected_day_dow = intval(date('w', strtotime($selected_day_greg))); // 0=Sun ... 6=Sat

// Start of this week is Sunday
$week_start_ts = strtotime("-$selected_day_dow days", strtotime($selected_day_greg));
$week_end_ts = strtotime("+6 days", $week_start_ts);

// 7 days in this full week (Sunday through Saturday)
$full_week_days = [];
for ($wi = 0; $wi < 7; $wi++) {
    $w_ts = strtotime("+$wi days", $week_start_ts);
    $w_greg = date('Y-m-d', $w_ts);
    $w_eth = getEthiopianDateFromGregorian($w_greg);
    $w_dow_name_am = ['እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ'][$wi];
    $w_is_class_weekend = ($wi === 0 || $wi === 6);
    $w_is_today = ($w_eth['year'] == $today_eth['year'] && $w_eth['month'] == $today_eth['month'] && $w_eth['day'] == $today_eth['day']);
    $w_saint = $daily_saints[intval($w_eth['day'])] ?? '';
    $w_holiday = $annual_holidays["{$w_eth['month']}-{$w_eth['day']}"] ?? '';
    
    // Day events
    $w_events = [];
    if (intval($w_eth['month']) == $selected_eth_month && intval($w_eth['year']) == $selected_eth_year) {
        $w_events = $events_by_day[intval($w_eth['day'])] ?? [];
    } else {
        $w_events = dbFetchAll($conn, "SELECT DISTINCT e.* FROM calendar_events e WHERE e.is_deleted = 0 AND e.event_date = ?", "s", [$w_greg]);
    }
    
    // Attendance status
    $w_att = $attendance_by_date[$w_greg] ?? null;
    $w_school_open = $w_att ? (intval($w_att['is_school_day']) === 1) : true;
    
    $full_week_days[] = [
        'greg_date' => $w_greg,
        'greg_formatted' => date('M j, Y', $w_ts),
        'dow' => $wi,
        'dow_am' => $w_dow_name_am,
        'eth_year' => $w_eth['year'],
        'eth_month' => $w_eth['month'],
        'eth_month_name' => $ethiopian_months[intval($w_eth['month'])] ?? '',
        'eth_day' => intval($w_eth['day']),
        'eth_geez' => toEthiopicNumber(intval($w_eth['day'])),
        'is_class_weekend' => $w_is_class_weekend,
        'is_sunday' => ($wi === 0),
        'is_today' => $w_is_today,
        'saint' => $w_saint,
        'holiday' => $w_holiday,
        'school_open' => $w_school_open,
        'is_special_feast' => isset($church_special_feasts[intval($w_eth['day'])]),
        'feast_icon' => isset($church_special_feasts[intval($w_eth['day'])]) ? $church_special_feasts[intval($w_eth['day'])]['icon'] : '',
        'events' => $w_events
    ];
}

// Previous & Next Week offsets
$prev_week_day_ts = strtotime("-7 days", strtotime($selected_day_greg));
$prev_week_eth = getEthiopianDateFromGregorian(date('Y-m-d', $prev_week_day_ts));
$next_week_day_ts = strtotime("+7 days", strtotime($selected_day_greg));
$next_week_eth = getEthiopianDateFromGregorian(date('Y-m-d', $next_week_day_ts));
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $ethiopian_months[$selected_eth_month] . ' ' . $selected_eth_year; ?> | የትምህርት ካሌንደር</title>
    <?php include 'pwa_head.php'; ?>
    <style>
        :root {
            --cal-parchment: #FDFBF7;
            --cal-card-bg: #FFFFFF;
            --cal-border: #E5E7EB;
            --cal-border-gold: #D97706;
            --cal-text-primary: #1F2937;
            --cal-text-secondary: #4B5563;
            --cal-text-muted: #9CA3AF;
            --cal-brown: #78350F;
            --cal-gold: #F59E0B;
            --cal-gold-dark: #D97706;
            --cal-red: #DC2626;
            --cal-red-light: #FEE2E2;
            --cal-green: #059669;
            --cal-green-light: #D1FAE5;
            --cal-blue: #2563EB;
            --cal-purple: #7C3AED;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; }
        html, body { max-width: 100%; overflow-x: hidden; }
        body { background: #F8F9FA; color: var(--cal-text-primary); -webkit-font-smoothing: antialiased; }
        .cal-wrapper { width: 100%; max-width: 1200px; margin: 20px auto; padding: 0 16px 60px; box-sizing: border-box; overflow-x: clip; }

        /* Traditional Ethiopian Calendar Paper Shell */
        .cal-paper-container {
            background: var(--cal-card-bg);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(120, 53, 15, 0.08), 0 1px 3px rgba(0,0,0,0.05);
            border: 2px solid #F3EDE2;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
        }

        /* Top Ornamental Ribbon */
        .cal-ornament-header {
            background: linear-gradient(135deg, #78350F 0%, #92400E 50%, #B45309 100%);
            color: #FFFFFF;
            padding: 20px 24px;
            border-bottom: 3px solid var(--cal-gold);
            position: relative;
        }
        .cal-ornament-header::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 5px;
            background: repeating-linear-gradient(90deg, #FFD700, #FFD700 15px, #DC2626 15px, #DC2626 30px, #059669 30px, #059669 45px);
        }

        .cal-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .cal-main-title-box { flex: 1; min-width: 260px; }
        .cal-season-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 215, 0, 0.5);
            color: #FEF3C7;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 6px;
        }
        .cal-eth-title {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #FFFFFF;
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cal-eth-title .geez-year {
            font-size: 20px;
            color: #FDE68A;
            font-weight: 700;
        }
        .cal-greg-range {
            font-size: 13px;
            color: #FDE68A;
            opacity: 0.95;
            margin-top: 4px;
            font-weight: 500;
        }

        /* Header Navigation & Actions */
        .cal-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cal-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #FFFFFF;
            color: var(--cal-brown);
            padding: 8px 14px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            transition: all 0.2s;
            border: 1px solid #E5E7EB;
        }
        .cal-nav-btn:hover {
            background: var(--cal-gold);
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .cal-nav-btn.today-btn {
            background: #F59E0B;
            color: #FFFFFF;
            border-color: #D97706;
        }
        .cal-nav-btn.today-btn:hover { background: #D97706; }

        /* Quick Jump Dropdown */
        .cal-jump-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.12);
            padding: 4px 8px;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .cal-jump-form select {
            background: #FFFFFF;
            color: #1F2937;
            border: none;
            padding: 6px 10px;
            border-radius: 16px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
        }

        /* Toolbar Row: Views, Filters & Stats */
        .cal-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #FAF8F5;
            border-bottom: 1px solid #E5E7EB;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cal-view-switchers {
            display: inline-flex;
            background: #E5E7EB;
            padding: 3px;
            border-radius: 10px;
            gap: 2px;
        }
        .cal-view-tab {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: #4B5563;
            transition: all 0.2s;
        }
        .cal-view-tab.active {
            background: #FFFFFF;
            color: var(--cal-brown);
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .cal-filter-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .cal-filter-pill {
            font-size: 12px;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 18px;
            text-decoration: none;
            color: #4B5563;
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .cal-filter-pill:hover { border-color: var(--cal-gold); }
        .cal-filter-pill.active {
            background: var(--cal-brown);
            color: #FFFFFF;
            border-color: var(--cal-brown);
        }

        .cal-action-btns {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-cal-print, .btn-cal-admin {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            color: #374151;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-cal-admin { background: #ECFDF5; border-color: #10B981; color: #047857; }
        .btn-cal-print:hover { background: #F3F4F6; }
        .btn-cal-admin:hover { background: #D1FAE5; }

        /* Responsive Visibility Helpers */
        .day-full { display: inline; }
        .day-short { display: none; }
        .badge-text-full { display: inline; }
        .badge-text-short { display: none; }

        /* Month Calendar Grid (7 Columns) */
        .cal-grid-wrapper {
            padding: 16px 14px;
            background: #FFFFFF;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .cal-weekdays-row {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 6px;
            margin-bottom: 8px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            position: sticky;
            top: 0;
            z-index: 15;
            background: #FFFFFF;
            padding: 4px 0;
        }
        .cal-weekday-th {
            background: #F8F6F0;
            border-radius: 8px;
            padding: 8px 2px;
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            color: #4B5563;
            border-bottom: 2px solid #E5E7EB;
            transition: background 0.15s ease;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-sizing: border-box;
        }
        
        /* Weekend Class Columns (ቅዳሜ and እሁድ are class days: unique green styling) */
        .cal-weekday-th.weekend-class-col {
            background: #ECFDF5;
            color: #065F46;
            border-bottom: 3px solid #10B981;
        }
        .cal-weekday-th small {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #6B7280;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-weekday-th.weekend-class-col small { color: #059669; }

        .cal-month-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 6px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Base Day Cell */
        .cal-day-cell {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            min-height: 120px;
            min-width: 0;
            max-width: 100%;
            padding: 6px 5px;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .cal-day-cell:hover {
            border-color: var(--cal-gold-dark);
            box-shadow: 0 6px 18px rgba(120, 53, 15, 0.12);
            transform: translateY(-2px);
            z-index: 2;
        }
        .cal-day-cell:active {
            transform: scale(0.98);
        }
        .cal-day-cell.other-month {
            background: #F9FAFB;
            opacity: 0.45;
            cursor: default;
        }
        .cal-day-cell.other-month:hover {
            border-color: #E5E7EB;
            box-shadow: none;
            transform: none;
        }

        /* 1. Weekend Class Days (ቅዳሜ & እሁድ): Bold, unique class color */
        .cal-day-cell.cal-class-weekend-cell {
            background: #F0FDF4 !important;
            border: 2px solid #86EFAC !important;
            border-top: 3px solid #10B981 !important;
        }
        .cal-day-cell.cal-class-weekend-cell .cal-eth-num {
            color: #065F46 !important;
            font-weight: 900 !important;
        }
        .cal-day-cell.cal-class-weekend-cell .cal-saint-text {
            color: #047857 !important;
            font-weight: 700;
        }

        /* 2. Special Church Feast Days: Bold golden highlight */
        .cal-day-cell.cal-special-feast-cell {
            background: linear-gradient(180deg, #FFFDF0 0%, #FEF7E0 100%) !important;
            border: 2px solid #D97706 !important;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.18) !important;
        }
        .cal-day-cell.cal-special-feast-cell .cal-eth-num {
            color: #B45309 !important;
            font-weight: 900 !important;
            font-size: 19px !important;
        }
        .cal-day-cell.cal-special-feast-cell .cal-geez-num {
            color: #92400E !important;
            font-weight: 800 !important;
        }
        .cal-day-cell.cal-special-feast-cell .cal-saint-text {
            color: #92400E !important;
            font-weight: 800 !important;
            font-size: 11px !important;
        }

        /* 3. Days with Admin-Added Events: Unique violet/purple prominence */
        .cal-day-cell.cal-has-admin-events {
            border: 2px solid #8B5CF6 !important;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.22) !important;
        }

        /* Today Cell Highlight */
        .cal-day-cell.today-cell {
            border: 2px solid #F59E0B !important;
            background: #FFFBEB !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.35) !important;
        }
        .cal-today-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #F59E0B;
            color: #FFFFFF;
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            letter-spacing: 0.3px;
        }

        /* Day Badges & Ribbons */
        .cal-feast-star-badge {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #FFFFFF;
            font-size: 9px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-bottom: 2px;
            align-self: flex-start;
        }

        .cal-admin-pin-badge {
            background: #7C3AED;
            color: #FFFFFF;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            margin-bottom: 2px;
            align-self: flex-start;
        }

        .cal-sunday-time-tag {
            background: #E0E7FF;
            color: #3730A3;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 5px;
            border-radius: 4px;
            margin-bottom: 3px;
            display: inline-block;
            align-self: flex-start;
        }

        /* Day Cell Numbers Header */
        .cal-cell-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 2px;
            margin-bottom: 2px;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
        }
        .cal-eth-num {
            font-size: 17px;
            font-weight: 800;
            color: var(--cal-brown);
            line-height: 1;
            white-space: nowrap;
        }
        .cal-geez-num {
            font-size: 11px;
            font-weight: 700;
            color: #B45309;
            margin-left: 2px;
            white-space: nowrap;
        }
        .cal-greg-num {
            font-size: 10px;
            font-weight: 600;
            color: #6B7280;
            white-space: nowrap;
        }

        /* Saint / Commemoration Name */
        .cal-saint-text {
            font-size: 10px;
            font-weight: 600;
            color: #6B7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
            padding-bottom: 2px;
            border-bottom: 1px dashed rgba(0,0,0,0.06);
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Holiday Tag */
        .cal-holiday-tag {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            padding: 1.5px 4px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Weekend School Attendance Status Pill */
        .cal-att-status {
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-bottom: 3px;
            align-self: flex-start;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }
        .cal-att-status.open { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .cal-att-status.closed { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

        /* Events Container in Cell */
        .cal-cell-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-top: auto;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
        }
        .cal-ev-pill {
            font-size: 9.5px;
            font-weight: 700;
            padding: 1.5px 4px;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            text-align: left;
            line-height: 1.25;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }
        .pill-exam { background: #FEE2E2; color: #991B1B; border-left: 2px solid #DC2626; }
        .pill-teaching { background: #D1FAE5; color: #065F46; border-left: 2px solid #059669; }
        .pill-revision { background: #DBEAFE; color: #1E40AF; border-left: 2px solid #2563EB; }
        .pill-holiday { background: #FEF3C7; color: #92400E; border-left: 2px solid #D97706; }
        .pill-church { background: #EDE9FE; color: #5B21B6; border-left: 2px solid #7C3AED; }
        .pill-meeting { background: #F1F5F9; color: #334155; border-left: 2px solid #64748B; }
        .pill-assessment { background: #CFFAFE; color: #155E75; border-left: 2px solid #0891B2; }
        .pill-announcement { background: #EEF2FF; color: #3730A3; border-left: 2px solid #4F46E5; }
        .pill-important { background: #FFEDD5; color: #9A3412; border-left: 2px solid #EA580C; }

        /* Full Week View (ሳምንት) Styles */
        .cal-week-view-wrapper {
            padding: 16px;
            background: #FFFFFF;
            width: 100%;
            box-sizing: border-box;
        }
        .cal-week-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
            background: #FAF8F5;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            flex-wrap: wrap;
        }
        .cal-week-range-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--cal-brown);
            text-align: center;
            flex: 1;
            min-width: 200px;
        }
        .cal-week-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            width: 100%;
            box-sizing: border-box;
        }
        .cal-week-day-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
            box-sizing: border-box;
        }
        .cal-week-day-card:hover {
            border-color: var(--cal-gold);
            box-shadow: 0 6px 18px rgba(120, 53, 15, 0.1);
            transform: translateY(-2px);
        }
        .cal-week-day-card.today-card {
            border: 2px solid #F59E0B !important;
            background: #FFFBEB !important;
        }
        .cal-week-day-card.weekend-class-card {
            border-top: 4px solid #10B981 !important;
            background: #F0FDF4;
        }
        .cal-week-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 8px;
            gap: 8px;
        }
        .cal-week-dow-badge {
            font-size: 15px;
            font-weight: 800;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cal-week-date-str {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--cal-brown);
            text-align: right;
        }
        .cal-week-events-box {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 4px;
        }

        .cal-more-events {
            font-size: 9.5px;
            font-weight: 800;
            color: #6B7280;
            margin-top: 1px;
            text-align: right;
        }

        /* List View (Agenda) */
        .cal-list-container {
            padding: 20px;
            background: #FFFFFF;
        }
        .cal-agenda-item {
            display: flex;
            gap: 16px;
            padding: 14px 12px;
            border-bottom: 1px solid #E5E7EB;
            align-items: flex-start;
            transition: background 0.15s;
            border-radius: 8px;
        }
        .cal-agenda-item:hover { background: #F9FAFB; }
        .cal-agenda-item:last-child { border-bottom: none; }
        .cal-agenda-date-box {
            min-width: 90px;
            text-align: center;
            background: #FDFBF7;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 8px 6px;
        }
        .cal-agenda-day-num { font-size: 24px; font-weight: 900; color: var(--cal-brown); line-height: 1; }
        .cal-agenda-geez { font-size: 12px; font-weight: 700; color: #B45309; }
        .cal-agenda-month-str { font-size: 11px; font-weight: 700; color: #4B5563; margin-top: 2px; }
        .cal-agenda-dow { font-size: 11px; color: #6B7280; }
        .cal-agenda-content { flex: 1; }
        .cal-agenda-title { font-size: 15px; font-weight: 700; color: #1F2937; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .cal-agenda-desc { font-size: 13px; color: #4B5563; line-height: 1.4; margin-bottom: 6px; }
        .cal-agenda-meta { font-size: 11.5px; color: #6B7280; display: flex; gap: 12px; flex-wrap: wrap; }

        /* Summary Stats Footer */
        .cal-footer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            padding: 16px 20px;
            background: #FDFBF7;
            border-top: 1px solid #E5E7EB;
        }
        .cal-stat-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
        }
        .cal-stat-num { font-size: 20px; font-weight: 800; color: var(--cal-brown); }
        .cal-stat-lbl { font-size: 11.5px; font-weight: 600; color: #6B7280; margin-top: 2px; }

        /* Modal Dialog (Google Calendar style) */
        .cal-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(3px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .cal-modal-box {
            background: #FFFFFF;
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            border: 2px solid var(--cal-gold);
            overflow: hidden;
            animation: calModalFadeIn 0.2s ease-out;
        }
        @keyframes calModalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .cal-modal-header {
            background: linear-gradient(135deg, #78350F, #92400E);
            color: #FFFFFF;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .cal-modal-title { font-size: 18px; font-weight: 800; }
        .cal-modal-subtitle { font-size: 12.5px; color: #FDE68A; margin-top: 3px; }
        .cal-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #FFFFFF;
            width: 28px; height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .cal-modal-close:hover { background: rgba(255,255,255,0.35); }
        .cal-modal-body { padding: 18px 20px; max-height: 70vh; overflow-y: auto; }
        .cal-modal-section { margin-bottom: 14px; }
        .cal-modal-section-title { font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; margin-bottom: 6px; }

        .cal-feast-greeting-card {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #F59E0B;
            border-radius: 10px;
            padding: 12px 14px;
            color: #78350F;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cal-sunday-child-teacher-card {
            background: #EEF2FF;
            border: 2px solid #6366F1;
            border-radius: 10px;
            padding: 12px 14px;
            color: #312E81;
            font-weight: 700;
            font-size: 13.5px;
            margin-bottom: 12px;
        }

        /* Direct Dark Mode Overrides */
        html.dark-mode, html[data-theme="dark"], body.dark-mode {
            --cal-parchment: #0B1120;
            --cal-card-bg: #111827;
            --cal-border: #334155;
            --cal-text-primary: #F1F5F9;
            --cal-text-secondary: #CBD5E1;
            --cal-text-muted: #94A3B8;
            --cal-brown: #FCD34D;
        }

        html.dark-mode body, body.dark-mode, html[data-theme="dark"] body {
            background: #0B1120 !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .cal-paper-container,
        body.dark-mode .cal-paper-container,
        [data-theme="dark"] .cal-paper-container {
            background: #111827 !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        }

        html.dark-mode .cal-toolbar,
        body.dark-mode .cal-toolbar,
        [data-theme="dark"] .cal-toolbar {
            background: #162032 !important;
            border-color: #334155 !important;
        }

        html.dark-mode .cal-view-switchers,
        body.dark-mode .cal-view-switchers,
        [data-theme="dark"] .cal-view-switchers {
            background: #1E293B !important;
        }

        html.dark-mode .cal-view-tab,
        body.dark-mode .cal-view-tab,
        [data-theme="dark"] .cal-view-tab {
            color: #94A3B8 !important;
        }

        html.dark-mode .cal-view-tab.active,
        body.dark-mode .cal-view-tab.active,
        [data-theme="dark"] .cal-view-tab.active {
            background: #334155 !important;
            color: #FCD34D !important;
        }

        html.dark-mode .cal-filter-pill,
        body.dark-mode .cal-filter-pill,
        [data-theme="dark"] .cal-filter-pill {
            background: #1E293B !important;
            border-color: #334155 !important;
            color: #CBD5E1 !important;
        }

        html.dark-mode .cal-filter-pill.active,
        body.dark-mode .cal-filter-pill.active,
        [data-theme="dark"] .cal-filter-pill.active {
            background: #D97706 !important;
            border-color: #F59E0B !important;
            color: #FFFFFF !important;
        }

        html.dark-mode .cal-grid-wrapper,
        body.dark-mode .cal-grid-wrapper,
        [data-theme="dark"] .cal-grid-wrapper,
        html.dark-mode .cal-list-container,
        body.dark-mode .cal-list-container,
        [data-theme="dark"] .cal-list-container {
            background: #111827 !important;
        }

        html.dark-mode .cal-weekday-th,
        body.dark-mode .cal-weekday-th,
        [data-theme="dark"] .cal-weekday-th {
            background: #162032 !important;
            color: #CBD5E1 !important;
            border-bottom-color: #334155 !important;
        }
        html.dark-mode .cal-weekday-th.weekend-class-col,
        body.dark-mode .cal-weekday-th.weekend-class-col,
        [data-theme="dark"] .cal-weekday-th.weekend-class-col {
            background: #064E3B !important;
            color: #A7F3D0 !important;
            border-bottom-color: #10B981 !important;
        }

        html.dark-mode .cal-day-cell,
        body.dark-mode .cal-day-cell,
        [data-theme="dark"] .cal-day-cell {
            background: #162032 !important;
            border-color: #27354A !important;
        }

        html.dark-mode .cal-day-cell.cal-class-weekend-cell,
        body.dark-mode .cal-day-cell.cal-class-weekend-cell,
        [data-theme="dark"] .cal-day-cell.cal-class-weekend-cell {
            background: #06281E !important;
            border-color: #059669 !important;
            border-top-color: #34D399 !important;
        }
        html.dark-mode .cal-day-cell.cal-class-weekend-cell .cal-eth-num,
        body.dark-mode .cal-day-cell.cal-class-weekend-cell .cal-eth-num,
        [data-theme="dark"] .cal-day-cell.cal-class-weekend-cell .cal-eth-num {
            color: #34D399 !important;
        }

        html.dark-mode .cal-day-cell.cal-special-feast-cell,
        body.dark-mode .cal-day-cell.cal-special-feast-cell,
        [data-theme="dark"] .cal-day-cell.cal-special-feast-cell {
            background: #2A1F0A !important;
            border-color: #F59E0B !important;
            box-shadow: 0 0 14px rgba(245, 158, 11, 0.3) !important;
        }
        html.dark-mode .cal-day-cell.cal-special-feast-cell .cal-eth-num,
        body.dark-mode .cal-day-cell.cal-special-feast-cell .cal-eth-num,
        [data-theme="dark"] .cal-day-cell.cal-special-feast-cell .cal-eth-num {
            color: #FCD34D !important;
        }

        html.dark-mode .cal-day-cell.cal-has-admin-events,
        body.dark-mode .cal-day-cell.cal-has-admin-events,
        [data-theme="dark"] .cal-day-cell.cal-has-admin-events {
            background: #23163E !important;
            border-color: #A855F7 !important;
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.3) !important;
        }

        html.dark-mode .cal-day-cell.other-month,
        body.dark-mode .cal-day-cell.other-month,
        [data-theme="dark"] .cal-day-cell.other-month {
            background: #0F172A !important;
            opacity: 0.35 !important;
        }
        html.dark-mode .cal-day-cell.today-cell,
        body.dark-mode .cal-day-cell.today-cell,
        [data-theme="dark"] .cal-day-cell.today-cell {
            background: #1F2937 !important;
            border-color: #F59E0B !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.4) !important;
        }

        html.dark-mode .cal-eth-num,
        body.dark-mode .cal-eth-num,
        [data-theme="dark"] .cal-eth-num {
            color: #FFFFFF !important;
        }
        html.dark-mode .cal-geez-num,
        body.dark-mode .cal-geez-num,
        [data-theme="dark"] .cal-geez-num {
            color: #FCD34D !important;
        }
        html.dark-mode .cal-saint-text,
        body.dark-mode .cal-saint-text,
        [data-theme="dark"] .cal-saint-text {
            color: #94A3B8 !important;
            border-bottom-color: rgba(255,255,255,0.08) !important;
        }
        html.dark-mode .cal-greg-num,
        body.dark-mode .cal-greg-num,
        [data-theme="dark"] .cal-greg-num {
            color: #64748B !important;
        }

        html.dark-mode .cal-holiday-tag,
        body.dark-mode .cal-holiday-tag,
        [data-theme="dark"] .cal-holiday-tag {
            background: #78350F !important;
            color: #FCD34D !important;
            border-color: #B45309 !important;
        }

        /* Dark mode event pills */
        html.dark-mode .pill-exam, [data-theme="dark"] .pill-exam { background: #3B1212 !important; color: #FCA5A5 !important; border-left-color: #EF4444 !important; }
        html.dark-mode .pill-teaching, [data-theme="dark"] .pill-teaching { background: #064E3B !important; color: #6EE7B7 !important; border-left-color: #10B981 !important; }
        html.dark-mode .pill-revision, [data-theme="dark"] .pill-revision { background: #1E3A8A !important; color: #93C5FD !important; border-left-color: #3B82F6 !important; }
        html.dark-mode .pill-holiday, [data-theme="dark"] .pill-holiday { background: #78350F !important; color: #FCD34D !important; border-left-color: #F59E0B !important; }
        html.dark-mode .pill-church, [data-theme="dark"] .pill-church { background: #3B0764 !important; color: #D8B4FE !important; border-left-color: #8B5CF6 !important; }
        html.dark-mode .pill-meeting, [data-theme="dark"] .pill-meeting { background: #1E293B !important; color: #CBD5E1 !important; border-left-color: #64748B !important; }
        html.dark-mode .pill-assessment, [data-theme="dark"] .pill-assessment { background: #164E63 !important; color: #67E8F9 !important; border-left-color: #06B6D4 !important; }
        html.dark-mode .pill-announcement, [data-theme="dark"] .pill-announcement { background: #1E1B4B !important; color: #A5B4FC !important; border-left-color: #6366F1 !important; }
        html.dark-mode .pill-important, [data-theme="dark"] .pill-important { background: #431407 !important; color: #FDBA74 !important; border-left-color: #F97316 !important; }

        html.dark-mode .cal-att-status.open, [data-theme="dark"] .cal-att-status.open { background: rgba(16, 185, 129, 0.25) !important; color: #6EE7B7 !important; border-color: rgba(16, 185, 129, 0.4) !important; }
        html.dark-mode .cal-att-status.closed, [data-theme="dark"] .cal-att-status.closed { background: rgba(239, 68, 68, 0.25) !important; color: #FCA5A5 !important; border-color: rgba(239, 68, 68, 0.4) !important; }

        html.dark-mode .cal-footer-stats,
        body.dark-mode .cal-footer-stats,
        [data-theme="dark"] .cal-footer-stats {
            background: #162032 !important;
            border-color: #334155 !important;
        }
        html.dark-mode .cal-stat-card,
        body.dark-mode .cal-stat-card,
        [data-theme="dark"] .cal-stat-card {
            background: #111827 !important;
            border-color: #334155 !important;
        }
        html.dark-mode .cal-stat-num, [data-theme="dark"] .cal-stat-num { color: #FCD34D !important; }
        html.dark-mode .cal-stat-lbl, [data-theme="dark"] .cal-stat-lbl { color: #94A3B8 !important; }

        html.dark-mode .cal-nav-btn, [data-theme="dark"] .cal-nav-btn {
            background: #1E293B !important;
            color: #FCD34D !important;
            border-color: #475569 !important;
        }
        html.dark-mode .btn-cal-print, [data-theme="dark"] .btn-cal-print {
            background: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #475569 !important;
        }

        html.dark-mode .cal-modal-box,
        body.dark-mode .cal-modal-box,
        [data-theme="dark"] .cal-modal-box {
            background: #162032 !important;
            border-color: #F59E0B !important;
            color: #F1F5F9 !important;
        }

        html.dark-mode .cal-agenda-item, [data-theme="dark"] .cal-agenda-item { border-bottom-color: #334155 !important; }
        html.dark-mode .cal-agenda-item:hover, [data-theme="dark"] .cal-agenda-item:hover { background: #1A2333 !important; }
        html.dark-mode .cal-agenda-date-box, [data-theme="dark"] .cal-agenda-date-box { background: #1E293B !important; border-color: #334155 !important; }
        html.dark-mode .cal-agenda-day-num, [data-theme="dark"] .cal-agenda-day-num { color: #FFFFFF !important; }
        html.dark-mode .cal-agenda-title, [data-theme="dark"] .cal-agenda-title { color: #F1F5F9 !important; }
        html.dark-mode .cal-agenda-desc, [data-theme="dark"] .cal-agenda-desc { color: #CBD5E1 !important; }

        /* Tablet Responsive Adjustments (769px - 1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .cal-wrapper { padding: 0 12px 50px; }
            .cal-grid-wrapper { padding: 14px 12px; }
            .cal-weekdays-row { gap: 5px; }
            .cal-month-grid { gap: 5px; }
            .cal-day-cell { min-height: 108px; padding: 6px 5px; }
            .cal-eth-num { font-size: 17px; }
            .cal-saint-text { font-size: 10px; }
            .cal-holiday-tag { font-size: 9.5px; }
        }

        /* Mobile Phone Behavior (< 768px) */
        @media (max-width: 768px) {
            .day-full { display: none !important; }
            .day-short { display: inline !important; }
            .badge-text-full { display: none !important; }
            .badge-text-short { display: inline !important; }

            .cal-wrapper { padding: 0 6px 60px; margin: 8px auto; max-width: 100%; }
            .cal-paper-container { border-radius: 14px; border: 1.5px solid #EFE8DA; }
            
            /* Header on Mobile */
            .cal-ornament-header { padding: 14px 12px 12px; }
            .cal-header-flex { flex-direction: column; align-items: stretch; gap: 10px; }
            .cal-main-title-box { text-align: center; min-width: unset; }
            .cal-season-badge { font-size: 11px; padding: 2px 9px; margin-bottom: 4px; }
            .cal-eth-title { font-size: 21px; justify-content: center; gap: 6px; }
            .cal-eth-title .geez-year { font-size: 15px; }
            .cal-greg-range { font-size: 11px; margin-top: 2px; }
            
            /* Header Nav Controls */
            .cal-header-actions {
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 4px;
            }
            .cal-nav-btn {
                padding: 6px 10px;
                font-size: 11.5px;
                border-radius: 18px;
                white-space: nowrap;
            }
            .cal-jump-form {
                flex: 1;
                justify-content: center;
                padding: 2px 4px;
                gap: 3px;
                border-radius: 18px;
            }
            .cal-jump-form select {
                padding: 5px 6px;
                font-size: 11px;
                border-radius: 12px;
                max-width: 78px;
            }

            /* Toolbar on Mobile */
            .cal-toolbar {
                flex-direction: column;
                align-items: stretch;
                padding: 10px 10px;
                gap: 8px;
            }
            .cal-view-switchers {
                width: 100%;
                display: flex;
            }
            .cal-view-tab {
                flex: 1;
                text-align: center;
                padding: 7px 6px;
                font-size: 12px;
            }
            .cal-filter-bar {
                width: 100%;
                display: flex;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                gap: 5px;
                padding-bottom: 2px;
            }
            .cal-filter-bar::-webkit-scrollbar { display: none; }
            .cal-filter-pill {
                padding: 4px 9px;
                font-size: 11.5px;
                flex-shrink: 0;
            }
            .cal-action-btns {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                width: 100%;
            }
            .btn-cal-print, .btn-cal-admin {
                flex: 1;
                justify-content: center;
                padding: 6px 10px;
                font-size: 12px;
            }

            /* 7-Column Grid on Mobile */
            .cal-grid-wrapper { padding: 6px 2px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; }
            .cal-weekdays-row {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 2px;
                margin-bottom: 4px;
                position: sticky;
                top: 0;
                z-index: 20;
                background: #FFFFFF;
                padding: 3px 0;
            }
            .cal-weekday-th {
                padding: 5px 1px;
                font-size: 11px;
                border-radius: 4px;
                min-width: 0;
                overflow: hidden;
            }
            .cal-weekday-th small { display: none !important; }

            .cal-month-grid {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 2px;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .cal-day-cell {
                min-height: 74px;
                padding: 3px 1.5px;
                border-radius: 5px;
                border-width: 1px;
                min-width: 0;
                max-width: 100%;
                overflow: hidden;
            }
            .cal-day-cell:active {
                transform: scale(0.96);
            }
            .cal-cell-header { gap: 1px; margin-bottom: 1px; }
            .cal-eth-num { font-size: 14.5px; font-weight: 800; line-height: 1; }
            .cal-geez-num { font-size: 10px; font-weight: 700; margin-left: 1px; }
            .cal-greg-num { font-size: 8px; color: #9CA3AF; }
            .cal-today-badge { font-size: 8px; padding: 1px 4px; top: 2px; right: 2px; border-radius: 6px; }

            .cal-saint-text {
                font-size: 8.5px;
                line-height: 1.15;
                margin-bottom: 2px;
                padding-bottom: 0;
                border-bottom: none;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .cal-holiday-tag {
                font-size: 8px;
                padding: 1px 2px;
                border-radius: 3px;
                margin-bottom: 2px;
                line-height: 1.15;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .cal-feast-star-badge {
                font-size: 7.5px;
                padding: 1px 3px;
                border-radius: 3px;
                margin-bottom: 1px;
                line-height: 1.1;
                max-width: 100%;
                overflow: hidden;
            }
            .cal-admin-pin-badge {
                font-size: 7.5px;
                padding: 1px 3px;
                border-radius: 3px;
                margin-bottom: 1px;
                line-height: 1.1;
                max-width: 100%;
                overflow: hidden;
            }
            .cal-att-status {
                font-size: 7.5px;
                padding: 1px 3px;
                border-radius: 3px;
                margin-bottom: 1px;
                line-height: 1.1;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .cal-sunday-time-tag {
                font-size: 7.5px;
                padding: 1px 3px;
                border-radius: 3px;
                margin-bottom: 1px;
                line-height: 1.1;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .cal-ev-pill {
                font-size: 8px;
                padding: 1px 2px;
                border-radius: 3px;
                line-height: 1.15;
                margin-bottom: 1px;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .cal-more-events { font-size: 8px; text-align: center; }

            /* Mobile Bottom Sheet Modal */
            .cal-modal-backdrop {
                align-items: flex-end;
                padding: 0;
            }
            .cal-modal-box {
                max-width: 100%;
                width: 100%;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                border-top-left-radius: 22px;
                border-top-right-radius: 22px;
                max-height: 85vh;
                animation: calModalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes calModalSlideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
            .cal-modal-header {
                padding: 15px 18px;
                border-top-left-radius: 20px;
                border-top-right-radius: 20px;
            }
            .cal-modal-title { font-size: 16px; font-weight: 800; }
            .cal-modal-subtitle { font-size: 11.5px; }
            .cal-modal-body {
                padding: 16px 18px 24px;
                max-height: calc(85vh - 70px);
            }

            /* List / Agenda View on Mobile */
            .cal-list-container { padding: 12px 8px; }
            .cal-agenda-item { gap: 10px; padding: 10px 6px; }
            .cal-agenda-date-box { min-width: 66px; padding: 6px 3px; }
            .cal-agenda-day-num { font-size: 20px; }
            .cal-agenda-title { font-size: 14px; }
            .cal-agenda-desc { font-size: 12px; }

            /* Footer Stats on Mobile */
            .cal-footer-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 12px;
            }
            .cal-stat-card { padding: 8px 6px; }
            .cal-stat-num { font-size: 17px; }
            .cal-stat-lbl { font-size: 10.5px; }
        }

        /* Ultra-Small Phones (e.g. iPhone SE, width <= 375px) */
        @media (max-width: 375px) {
            .cal-eth-title { font-size: 19px; }
            .cal-nav-btn { padding: 5px 8px; font-size: 10.5px; }
            .cal-jump-form select { max-width: 64px; font-size: 10px; padding: 4px 3px; }
            .cal-day-cell { min-height: 72px; padding: 3px 1.5px; }
            .cal-eth-num { font-size: 13.5px; }
            .cal-weekday-th { font-size: 10.5px; padding: 5px 1px; }
        }

        /* Print Media Stylesheet */
        @media print {
            body { background: #FFFFFF !important; color: #000000 !important; }
            .main-nav, nav, .cal-nav-btn, .cal-jump-form, .cal-view-switchers, .cal-filter-bar, .cal-action-btns, .cal-footer-stats { display: none !important; }
            .cal-wrapper { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .cal-paper-container { border: 2px solid #000000 !important; box-shadow: none !important; border-radius: 0 !important; }
            .cal-ornament-header { background: #78350F !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cal-day-cell { min-height: 95px !important; border: 1px solid #999999 !important; }
        }
    </style>
</head>
<body>
    <?php include 'mobile_nav.php'; ?>

    <div class="cal-wrapper">
        <div class="cal-paper-container">
            
            <!-- Traditional Ethiopian Calendar Header Banner -->
            <div class="cal-ornament-header">
                <div class="cal-header-flex">
                    <div class="cal-main-title-box">
                        <div class="cal-season-badge">
                            <span>✨</span>
                            <span><?php echo getEvangelistName($selected_eth_year); ?></span>
                            <span>•</span>
                            <span>፲፫ቱ ወራት</span>
                        </div>
                        <div class="cal-eth-title">
                            <span><?php echo $ethiopian_months[$selected_eth_month]; ?> <?php echo $selected_eth_year; ?> ዓ.ም</span>
                            <span class="geez-year"><?php echo toEthiopicYear($selected_eth_year); ?></span>
                        </div>
                        <div class="cal-greg-range">
                            📅 <?php echo $greg_range_str; ?>
                        </div>
                    </div>

                    <!-- Navigation & Quick Jump -->
                    <div class="cal-header-actions">
                        <a href="<?php echo buildCalUrl($prev_m, $prev_y); ?>" class="cal-nav-btn" title="ቀዳሚ ወር">← ቀዳሚ</a>
                        
                        <form method="GET" class="cal-jump-form">
                            <input type="hidden" name="v" value="<?php echo htmlspecialchars($view_mode, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($filter_type !== 'all'): ?>
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <select name="m" onchange="this.form.submit()" aria-label="ወር ምረጥ">
                                <?php foreach ($ethiopian_months as $mn => $mname): ?>
                                <option value="<?php echo $mn; ?>" <?php echo $selected_eth_month == $mn ? 'selected' : ''; ?>>
                                    <?php echo $mname; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="y" onchange="this.form.submit()" aria-label="ዓመተ ምሕረት ምረጥ">
                                <?php for ($yr = 2015; $yr <= 2030; $yr++): ?>
                                <option value="<?php echo $yr; ?>" <?php echo $selected_eth_year == $yr ? 'selected' : ''; ?>>
                                    <?php echo $yr; ?> ዓ.ም
                                </option>
                                <?php endfor; ?>
                            </select>
                        </form>

                        <a href="<?php echo buildCalUrl($next_m, $next_y); ?>" class="cal-nav-btn" title="ቀጣይ ወር">ቀጣይ →</a>
                        <a href="<?php echo buildCalUrl($today_eth['month'], $today_eth['year']); ?>" class="cal-nav-btn today-btn" title="ወደ ዛሬ ቀን ተመለስ">📅 ዛሬ</a>
                    </div>
                </div>
            </div>

            <!-- Toolbar: View Switchers, Category Filters, Print & Admin Actions -->
            <div class="cal-toolbar">
                <div class="cal-view-switchers">
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, 'month'); ?>" class="cal-view-tab <?php echo $view_mode === 'month' ? 'active' : ''; ?>">
                        📅 ወር
                    </a>
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, 'week', null, $selected_day); ?>" class="cal-view-tab <?php echo $view_mode === 'week' ? 'active' : ''; ?>">
                        📆 ሙሉ ሳምንት
                    </a>
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, 'list'); ?>" class="cal-view-tab <?php echo $view_mode === 'list' ? 'active' : ''; ?>">
                        📋 ዝርዝር (<?php echo count($agenda_events); ?>)
                    </a>
                </div>

                <div class="cal-filter-bar">
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, null, 'all'); ?>" class="cal-filter-pill <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        ሁሉም (<?php echo $total_events_month; ?>)
                    </a>
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, null, 'exam'); ?>" class="cal-filter-pill <?php echo $filter_type === 'exam' ? 'active' : ''; ?>">
                        📝 ፈተና (<?php echo $exam_count; ?>)
                    </a>
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, null, 'holiday'); ?>" class="cal-filter-pill <?php echo $filter_type === 'holiday' ? 'active' : ''; ?>">
                        🎉 በዓላት (<?php echo $holiday_count; ?>)
                    </a>
                    <a href="<?php echo buildCalUrl($selected_eth_month, $selected_eth_year, null, 'teaching'); ?>" class="cal-filter-pill <?php echo $filter_type === 'teaching' ? 'active' : ''; ?>">
                        📚 ትምህርት (<?php echo $teaching_count; ?>)
                    </a>
                </div>

                <div class="cal-action-btns">
                    <a href="print_orthodox_calendar.php" target="_blank" class="btn-cal-print" style="background:#8B4513; color:#FFD700; border:1px solid #FFD700; text-decoration:none;" title="የሰንበት ትምህርት ቤቱ የቅዱሳንና ዓመታዊ በዓላት ይፋዊ መዝገብ PDF">
                        📜 የበዓላት መዝገብ (PDF)
                    </a>
                    <button onclick="window.print()" class="btn-cal-print" title="የግድግዳ ካላንደሩን አትም">
                        🖨️ አትም
                    </button>
                    <?php if ($role === 'admin'): ?>
                    <a href="calendar_admin.php" class="btn-cal-admin" title="አዲስ ሁነት መዝግብ">
                        ➕ ሁነት መዝግብ
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Mode 1: Month Calendar Paper Grid -->
            <?php if ($view_mode === 'month'): ?>
            <div class="cal-grid-wrapper">
                
                <!-- 7 Weekdays Header Row: Saturday & Sunday are Class Days -->
                <div class="cal-weekdays-row">
                    <div class="cal-weekday-th weekend-class-col">
                        <span class="day-full">እሑድ</span>
                        <span class="day-short">እሁድ</span>
                        <small>📚 የትምህርት ቀን</small>
                    </div>
                    <div class="cal-weekday-th">
                        <span class="day-full">ሰኞ</span>
                        <span class="day-short">ሰኞ</span>
                        <small>Monday</small>
                    </div>
                    <div class="cal-weekday-th">
                        <span class="day-full">ማክሰኞ</span>
                        <span class="day-short">ማክ</span>
                        <small>Tuesday</small>
                    </div>
                    <div class="cal-weekday-th">
                        <span class="day-full">ረቡዕ</span>
                        <span class="day-short">ረቡ</span>
                        <small>Wednesday</small>
                    </div>
                    <div class="cal-weekday-th">
                        <span class="day-full">ሐሙስ</span>
                        <span class="day-short">ሐሙ</span>
                        <small>Thursday</small>
                    </div>
                    <div class="cal-weekday-th">
                        <span class="day-full">ዓርብ</span>
                        <span class="day-short">ዓርብ</span>
                        <small>Friday</small>
                    </div>
                    <div class="cal-weekday-th weekend-class-col">
                        <span class="day-full">ቅዳሜ</span>
                        <span class="day-short">ቅዳ</span>
                        <small>📚 የትምህርት ቀን</small>
                    </div>
                </div>

                <!-- 7-Column Days Grid -->
                <div class="cal-month-grid">
                    <?php
                    // 1. Leading Faded Days from previous Ethiopian month
                    if ($first_day_dow > 0) {
                        $leading_start = $days_in_prev_month - $first_day_dow + 1;
                        for ($lead = $leading_start; $lead <= $days_in_prev_month; $lead++) {
                            $lead_greg = ethiopianToGregorian($prev_y, $prev_m, $lead);
                            $lead_greg_fmt = $lead_greg ? date('j M', strtotime($lead_greg)) : '';
                            ?>
                            <div class="cal-day-cell other-month">
                                <div class="cal-cell-header">
                                    <div>
                                        <span class="cal-eth-num"><?php echo $lead; ?></span>
                                        <span class="cal-geez-num"><?php echo toEthiopicNumber($lead); ?></span>
                                    </div>
                                    <span class="cal-greg-num"><?php echo $lead_greg_fmt; ?></span>
                                </div>
                                <div class="cal-saint-text"><?php echo $daily_saints[$lead] ?? ''; ?></div>
                            </div>
                            <?php
                        }
                    }

                    // 2. Active Month Days (1 to $days_in_month)
                    for ($d = 1; $d <= $days_in_month; $d++):
                        $greg_str = ethiopianToGregorian($selected_eth_year, $selected_eth_month, $d);
                        $dow_num = $greg_str ? intval(date('w', strtotime($greg_str))) : 0;
                        $dow_name = $greg_str ? date('l', strtotime($greg_str)) : '';
                        $is_class_weekend = ($dow_num === 0 || $dow_num === 6); // 0=Sunday, 6=Saturday
                        $greg_fmt = $greg_str ? date('j M', strtotime($greg_str)) : '';
                        $is_today = ($is_selected_current_month && $d == $today_eth['day']);
                        $saint_name = $daily_saints[$d] ?? '';
                        $holiday_name = $annual_holidays["{$selected_eth_month}-{$d}"] ?? '';
                        $day_events = $events_by_day[$d] ?? [];
                        
                        // Feast checks
                        $is_special_feast = isset($church_special_feasts[$d]);
                        $feast_info = $church_special_feasts[$d] ?? null;

                        // Admin events check
                        $has_admin_events = !empty($day_events);

                        // Attendance status for weekend school days
                        $att_info = $attendance_by_date[$greg_str] ?? null;
                        $is_school_open = $att_info ? (intval($att_info['is_school_day']) === 1) : true;

                        // Build CSS classes for cell
                        $classes = [];
                        if ($is_class_weekend) $classes[] = 'cal-class-weekend-cell';
                        if ($is_special_feast) $classes[] = 'cal-special-feast-cell';
                        if ($has_admin_events) $classes[] = 'cal-has-admin-events';
                        if ($is_today) $classes[] = 'today-cell';

                        // Payload for client-side modal popup
                        $modal_payload = [
                            'eth_day' => $d,
                            'eth_geez' => toEthiopicNumber($d),
                            'eth_month_name' => $ethiopian_months[$selected_eth_month],
                            'eth_year' => $selected_eth_year,
                            'eth_geez_year' => toEthiopicYear($selected_eth_year),
                            'dow_am' => ['እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ'][$dow_num] ?? 'እሑድ',
                            'greg_date' => $greg_str,
                            'greg_formatted' => $greg_str ? date('l, F j, Y', strtotime($greg_str)) : '',
                            'saint' => $saint_name,
                            'holiday' => $holiday_name,
                            'is_class_weekend' => $is_class_weekend,
                            'is_sunday' => ($dow_num === 0),
                            'is_school_open' => $is_school_open,
                            'is_special_feast' => $is_special_feast,
                            'feast_icon' => $feast_info ? $feast_info['icon'] : '',
                            'has_admin_events' => $has_admin_events,
                            'events' => $day_events
                        ];
                    ?>
                        <div class="cal-day-cell <?php echo implode(' ', $classes); ?>"
                             onclick="showDayDetails(<?php echo htmlspecialchars(json_encode($modal_payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)">
                            
                            <?php if ($is_today): ?>
                            <div class="cal-today-badge">ዛሬ</div>
                            <?php endif; ?>

                            <!-- Badges Row -->
                            <?php if ($is_special_feast): ?>
                            <div class="cal-feast-star-badge" title="<?php echo htmlspecialchars($feast_info['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="badge-text-full"><?php echo $feast_info['icon']; ?> የበዓል ዕለት</span>
                                <span class="badge-text-short"><?php echo $feast_info['icon']; ?> በዓል</span>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_admin_events): ?>
                            <div class="cal-admin-pin-badge" title="ሁነት ተመዝግቧል">
                                <span class="badge-text-full">⭐ ሁነት አለ</span>
                                <span class="badge-text-short">⭐ ሁነት</span>
                            </div>
                            <?php endif; ?>

                            <div class="cal-cell-header">
                                <div>
                                    <span class="cal-eth-num"><?php echo $d; ?></span>
                                    <span class="cal-geez-num"><?php echo toEthiopicNumber($d); ?></span>
                                </div>
                                <span class="cal-greg-num"><?php echo $greg_fmt; ?></span>
                            </div>

                            <!-- Commemorative Saint -->
                            <?php if ($saint_name): ?>
                            <div class="cal-saint-text" title="<?php echo htmlspecialchars($saint_name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo ($is_special_feast ? $feast_info['icon'] . ' ' : '') . htmlspecialchars($saint_name, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>

                            <!-- Annual Holiday Tag -->
                            <?php if ($holiday_name): ?>
                            <div class="cal-holiday-tag" title="<?php echo htmlspecialchars($holiday_name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($holiday_name, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>

                            <!-- Weekend School Day Badge -->
                            <?php if ($is_class_weekend): ?>
                            <div class="cal-att-status <?php echo $is_school_open ? 'open' : 'closed'; ?>" title="የትምህርት ቀን ሁኔታ">
                                <span class="badge-text-full">📚 ትምህርት <?php echo $is_school_open ? 'አለ' : 'የለም'; ?></span>
                                <span class="badge-text-short"><?php echo $is_school_open ? '📚 ክፍት' : '🚫 ዝግ'; ?></span>
                            </div>
                            <?php if ($dow_num === 0): ?>
                            <div class="cal-sunday-time-tag" title="የህፃናት ትምህርት መጀመሪያ ሰዓት">
                                <span class="badge-text-full">ህፃናት፡ 05:00</span>
                                <span class="badge-text-short">⏰ 05:00</span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>

                            <!-- Events Pills -->
                            <div class="cal-cell-events">
                                <?php
                                $pill_count = 0;
                                foreach ($day_events as $ev):
                                    if ($pill_count >= 2) break;
                                    $mclass = $event_type_meta[$ev['event_type']]['badge_class'] ?? 'pill-announcement';
                                    $pill_count++;
                                ?>
                                <span class="cal-ev-pill <?php echo $mclass; ?>" title="<?php echo htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php endforeach; ?>

                                <?php if (count($day_events) > 2): ?>
                                <span class="cal-more-events">+<?php echo count($day_events) - 2; ?> ተጨማሪ</span>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endfor; ?>

                    <?php
                    // 3. Trailing Faded Days for next Ethiopian month to complete 7-day row
                    $last_day_dow = intval(date('w', strtotime($last_day_greg)));
                    if ($last_day_dow < 6) {
                        $trailing_days = 6 - $last_day_dow;
                        for ($trail = 1; $trail <= $trailing_days; $trail++) {
                            $trail_greg = ethiopianToGregorian($next_y, $next_m, $trail);
                            $trail_greg_fmt = $trail_greg ? date('j M', strtotime($trail_greg)) : '';
                            ?>
                            <div class="cal-day-cell other-month">
                                <div class="cal-cell-header">
                                    <div>
                                        <span class="cal-eth-num"><?php echo $trail; ?></span>
                                        <span class="cal-geez-num"><?php echo toEthiopicNumber($trail); ?></span>
                                    </div>
                                    <span class="cal-greg-num"><?php echo $trail_greg_fmt; ?></span>
                                </div>
                                <div class="cal-saint-text"><?php echo $daily_saints[$trail] ?? ''; ?></div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- View Mode 2: Full Week View (ሙሉ ሳምንት) -->
            <?php elseif ($view_mode === 'week'): ?>
            <div class="cal-week-view-wrapper">
                
                <!-- Week Navigation Bar -->
                <div class="cal-week-nav-bar">
                    <a href="<?php echo buildCalUrl($prev_week_eth['month'], $prev_week_eth['year'], 'week', null, $prev_week_eth['day']); ?>" class="cal-nav-btn" title="ቀዳሚ ሳምንት">
                        ← ቀዳሚ ሳምንት
                    </a>
                    <div class="cal-week-range-title">
                        📆 የሳምንቱ ቀናት፦ 
                        <?php echo $full_week_days[0]['dow_am']; ?> (<?php echo $full_week_days[0]['eth_month_name'] . ' ' . $full_week_days[0]['eth_day']; ?>) – 
                        <?php echo $full_week_days[6]['dow_am']; ?> (<?php echo $full_week_days[6]['eth_month_name'] . ' ' . $full_week_days[6]['eth_day']; ?>)
                    </div>
                    <a href="<?php echo buildCalUrl($next_week_eth['month'], $next_week_eth['year'], 'week', null, $next_week_eth['day']); ?>" class="cal-nav-btn" title="ቀጣይ ሳምንት">
                        ቀጣይ ሳምንት →
                    </a>
                </div>

                <!-- 7 Full Week Day Cards (Sunday through Saturday) -->
                <div class="cal-week-grid">
                    <?php foreach ($full_week_days as $wday): 
                        $w_classes = ['cal-week-day-card'];
                        if ($wday['is_class_weekend']) $w_classes[] = 'weekend-class-card';
                        if ($wday['is_special_feast']) $w_classes[] = 'special-feast-card';
                        if ($wday['is_today']) $w_classes[] = 'today-card';

                        // Build payload for click modal
                        $w_modal_payload = [
                            'eth_day' => $wday['eth_day'],
                            'eth_geez' => $wday['eth_geez'],
                            'eth_month_name' => $wday['eth_month_name'],
                            'eth_year' => $wday['eth_year'],
                            'eth_geez_year' => toEthiopicYear($wday['eth_year']),
                            'dow_am' => $wday['dow_am'],
                            'greg_date' => $wday['greg_date'],
                            'greg_formatted' => $wday['greg_formatted'],
                            'saint' => $wday['saint'],
                            'holiday' => $wday['holiday'],
                            'is_class_weekend' => $wday['is_class_weekend'],
                            'is_sunday' => $wday['is_sunday'],
                            'is_school_open' => $wday['school_open'],
                            'is_special_feast' => $wday['is_special_feast'],
                            'feast_icon' => $wday['feast_icon'],
                            'has_admin_events' => !empty($wday['events']),
                            'events' => $wday['events']
                        ];
                    ?>
                    <div class="<?php echo implode(' ', $w_classes); ?>"
                         onclick="showDayDetails(<?php echo htmlspecialchars(json_encode($w_modal_payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)">
                        
                        <div class="cal-week-card-header">
                            <div class="cal-week-dow-badge">
                                <span><?php echo $wday['dow_am']; ?></span>
                                <?php if ($wday['is_today']): ?>
                                <span class="cal-today-badge" style="position:static;">ዛሬ</span>
                                <?php endif; ?>
                            </div>
                            <div class="cal-week-date-str">
                                <?php echo $wday['eth_month_name']; ?> <?php echo $wday['eth_geez']; ?> (<?php echo $wday['eth_day']; ?>)
                                <small style="display:block; font-size:11px; color:#6B7280; font-weight:normal; text-align:right;">
                                    <?php echo $wday['greg_formatted']; ?>
                                </small>
                            </div>
                        </div>

                        <!-- Commemorative Saint -->
                        <div style="font-size:13px; font-weight:700; color:var(--cal-brown); display:flex; align-items:center; gap:5px;">
                            <span><?php echo $wday['is_special_feast'] ? $wday['feast_icon'] : '⛪'; ?></span>
                            <span><?php echo htmlspecialchars($wday['saint'] ?: 'መደበኛ ቀን', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <!-- Annual Holiday -->
                        <?php if ($wday['holiday']): ?>
                        <div class="cal-holiday-tag" style="white-space:normal; font-size:12px; padding:3px 8px;">
                            <?php echo htmlspecialchars($wday['holiday'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Weekend Class Status -->
                        <?php if ($wday['is_class_weekend']): ?>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span class="cal-att-status <?php echo $wday['school_open'] ? 'open' : 'closed'; ?>" style="font-size:11px; padding:2px 8px;">
                                📚 ትምህርት <?php echo $wday['school_open'] ? 'አለ' : 'የለም'; ?>
                            </span>
                            <?php if ($wday['is_sunday']): ?>
                            <span class="cal-sunday-time-tag" style="font-size:11px; padding:2px 8px;">
                                ⏰ ህፃናት፡ 05:00
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Events List for this Day -->
                        <div class="cal-week-events-box">
                            <?php if (empty($wday['events'])): ?>
                            <div style="font-size:11.5px; color:#9CA3AF; padding:2px 0;">ሁነት አልተመዘገበም</div>
                            <?php else: ?>
                            <?php foreach ($wday['events'] as $ev): 
                                $mclass = $event_type_meta[$ev['event_type']]['badge_class'] ?? 'pill-announcement';
                            ?>
                            <div class="cal-ev-pill <?php echo $mclass; ?>" style="white-space:normal; font-size:11.5px; padding:4px 8px; margin-bottom:3px;">
                                <strong><?php echo htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($ev['description'])): ?>
                                <div style="font-size:11px; opacity:0.9; margin-top:2px; font-weight:normal;"><?php echo htmlspecialchars($ev['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <!-- View Mode 3: Agenda / List View -->
            <?php else: ?>
            <div class="cal-list-container">
                <?php if (empty($agenda_events)): ?>
                <div style="text-align:center; padding: 60px 20px; color:#9CA3AF;">
                    <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
                    <div style="font-size: 16px; font-weight: 700; color: #4B5563;">በዚህ ወር የተመዘገበ ሁነት የለም</div>
                    <p style="font-size: 13px; margin-top: 4px;">ከላይ ያሉትን የቀን አዝራሮች በመጠቀም ሌሎች ወራትን መመልከት ይችላሉ።</p>
                </div>
                <?php else: ?>
                <div>
                    <?php foreach ($agenda_events as $ev): 
                        $ev_d = intval($ev['ethiopian_day'] ?? 0);
                        if (!$ev_d && !empty($ev['event_date'])) {
                            $conv = getEthiopianDateFromGregorian($ev['event_date']);
                            $ev_d = intval($conv['day']);
                        }
                        $ev_greg = $ev['event_date'];
                        $dow_am = [
                            'Sunday' => 'እሑድ', 'Monday' => 'ሰኞ', 'Tuesday' => 'ማክሰኞ',
                            'Wednesday' => 'ረቡዕ', 'Thursday' => 'ሐሙስ', 'Friday' => 'ዓርብ', 'Saturday' => 'ቅዳሜ'
                        ];
                        $ev_dow = $ev_greg ? ($dow_am[date('l', strtotime($ev_greg))] ?? date('l', strtotime($ev_greg))) : '';
                        $ev_type_label = $event_type_meta[$ev['event_type']]['label'] ?? $ev['event_type'];
                        $badge_class = $event_type_meta[$ev['event_type']]['badge_class'] ?? 'pill-announcement';
                        $is_special_feast_item = isset($church_special_feasts[$ev_d]);
                        $is_annual_feast = !empty($ev['is_annual_feast']);
                    ?>
                    <div class="cal-agenda-item <?php echo $is_special_feast_item ? 'cal-special-feast-agenda' : ''; ?>">
                        <div class="cal-agenda-date-box">
                            <div class="cal-agenda-day-num"><?php echo $ev_d; ?></div>
                            <div class="cal-agenda-geez"><?php echo toEthiopicNumber($ev_d); ?></div>
                            <div class="cal-agenda-month-str"><?php echo $ethiopian_months[$selected_eth_month]; ?></div>
                            <div class="cal-agenda-dow"><?php echo $ev_dow; ?></div>
                        </div>
                        <div class="cal-agenda-content">
                            <div class="cal-agenda-title">
                                <span class="cal-ev-pill <?php echo $badge_class; ?>" style="display:inline-block; font-size:11.5px; padding:3px 8px;">
                                    <?php echo htmlspecialchars($ev_type_label, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span><?php echo htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($ev['priority'] === 'high' || $ev['priority'] === 'urgent'): ?>
                                <span style="font-size:10px; background:#FEE2E2; color:#991B1B; padding:2px 6px; border-radius:10px; font-weight:800;">⚠️ አጣዳፊ</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ev['description'])): ?>
                            <div class="cal-agenda-desc">
                                <?php echo nl2br(htmlspecialchars($ev['description'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <?php endif; ?>
                            <div class="cal-agenda-meta">
                                <span>📅 እ.ኤ.አ፡ <?php echo htmlspecialchars($ev_greg, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($is_annual_feast): ?>
                                <span>⛪ ዓመታዊ የቤተክርስቲያን በዓል</span>
                                <?php elseif (!empty($ev['target_class'])): ?>
                                <span>🎯 ክፍል፡ <?php echo htmlspecialchars($ev['target_class'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php elseif (!empty($ev['target_grade'])): ?>
                                <span>🎯 ደረጃ፡ <?php echo htmlspecialchars($ev['target_grade'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php elseif (!empty($ev['target_division'])): ?>
                                <span>🎯 ዘርፍ፡ <?php echo htmlspecialchars($ev['target_division'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                <span>👥 ለሁሉም (ለሁሉም ተማሪዎችና መምህራን)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Calendar Month Statistics Footer -->
            <div class="cal-footer-stats">
                <div class="cal-stat-card">
                    <div class="cal-stat-num"><?php echo $days_in_month; ?></div>
                    <div class="cal-stat-lbl">የወሩ ቀናት</div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-num" style="color:var(--cal-gold-dark);"><?php echo $total_events_month; ?></div>
                    <div class="cal-stat-lbl">ጠቅላላ ሁነቶች</div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-num" style="color:#DC2626;"><?php echo $exam_count; ?></div>
                    <div class="cal-stat-lbl">📝 ፈተናዎች</div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-num" style="color:#7C3AED;"><?php echo $holiday_count; ?></div>
                    <div class="cal-stat-lbl">🎉 በዓላት</div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-num" style="color:#059669;"><?php echo $teaching_count; ?></div>
                    <div class="cal-stat-lbl">📚 የትምህርት ቀናት</div>
                </div>
            </div>

        </div>
    </div>

    <!-- Day Details Modal (Google Calendar Popup Style) -->
    <div id="calDayModal" class="cal-modal-backdrop" onclick="closeDayModal(event)">
        <div class="cal-modal-box" onclick="event.stopPropagation()">
            <div class="cal-modal-header">
                <div>
                    <div class="cal-modal-title" id="mModalTitle">ቀን ዝርዝር</div>
                    <div class="cal-modal-subtitle" id="mModalSubtitle">የኢትዮጵያ ካሌንደር</div>
                </div>
                <button type="button" class="cal-modal-close" onclick="closeDayModalDirect()" aria-label="ዝጋ">✕</button>
            </div>
            <div class="cal-modal-body">
                
                <div class="cal-modal-section">
                    <div class="cal-modal-section-title">የዕለቱ መታሰቢያ እና በዓል</div>
                    <div id="mModalSaint" style="font-size:15px; font-weight:700; color:var(--cal-brown); margin-bottom:4px;"></div>
                    <div id="mModalHoliday" style="display:none; font-size:13px; font-weight:700; color:#B45309; background:#FEF3C7; padding:6px 10px; border-radius:6px; margin-top:6px;"></div>
                </div>

                <div class="cal-modal-section" id="mModalWeekendBox" style="display:none;">
                    <div class="cal-modal-section-title">የትምህርት ቀን ሁኔታ</div>
                    <div id="mModalSchoolStatus" style="font-size:14px; font-weight:700;"></div>
                </div>

                <div class="cal-modal-section">
                    <div class="cal-modal-section-title">የተመዘገቡ ሁነቶች (<span id="mModalEventCount">0</span>)</div>
                    <div id="mModalEventsList" style="display:flex; flex-direction:column; gap:8px;"></div>
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn-cal-print" onclick="closeDayModalDirect()" style="padding:8px 18px; border-radius:8px;">
                        ዝጋ
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Modal popup controller
        function showDayDetails(data) {
            if (!data) return;

            const modal = document.getElementById('calDayModal');
            document.getElementById('mModalTitle').textContent = 
                data.dow_am + ' ' + data.eth_month_name + ' ' + data.eth_geez + ' (' + data.eth_day + ') ቀን ' + data.eth_year + ' ዓ.ም';
            document.getElementById('mModalSubtitle').textContent = 
                'እ.ኤ.አ፡ ' + (data.greg_formatted || data.greg_date);

            // Saint
            const saintIcon = data.feast_icon ? (data.feast_icon + ' ') : '';
            document.getElementById('mModalSaint').textContent = 
                data.saint ? ('⛪ የዕለቱ ቅዱስ፡ ' + saintIcon + data.saint) : '⛪ መደበኛ ቀን';

            // Holiday
            const holElem = document.getElementById('mModalHoliday');
            if (data.holiday && data.holiday.trim() !== '') {
                holElem.innerHTML = '<span style="font-weight:700;">🌟 ዓመታዊ በዓል፡</span> ' + data.holiday;
                holElem.style.display = 'block';
            } else {
                holElem.style.display = 'none';
            }

            // Weekend school day status
            const weekendBox = document.getElementById('mModalWeekendBox');
            const schoolStatus = document.getElementById('mModalSchoolStatus');
            if (data.is_class_weekend) {
                weekendBox.style.display = 'block';
                if (data.is_school_open) {
                    schoolStatus.innerHTML = '<span style="color:#059669;">🟢 📚 ትምህርት አለ</span>';
                } else {
                    schoolStatus.innerHTML = '<span style="color:#DC2626;">🔒 🚫 ትምህርት የለም</span>';
                }
            } else {
                weekendBox.style.display = 'none';
            }

            // Events List
            const eventsList = document.getElementById('mModalEventsList');
            const eventCount = document.getElementById('mModalEventCount');
            eventsList.innerHTML = '';
            
            const evs = data.events || [];
            eventCount.textContent = evs.length;

            if (evs.length === 0) {
                eventsList.innerHTML = '<div style="font-size:13px; color:#9CA3AF; padding:10px 0;">📌 ለዚህ ቀን የተመዘገበ ልዩ ሁነት የለም።</div>';
            } else {
                evs.forEach(function(ev) {
                    const card = document.createElement('div');
                    card.style.background = '#F9FAFB';
                    card.style.border = '1px solid #E5E7EB';
                    card.style.borderRadius = '8px';
                    card.style.padding = '10px 12px';

                    const title = document.createElement('div');
                    title.style.fontWeight = '700';
                    title.style.fontSize = '14px';
                    title.style.color = '#1F2937';
                    title.style.marginBottom = '4px';
                    title.textContent = ev.title;

                    card.appendChild(title);

                    if (ev.description) {
                        const desc = document.createElement('div');
                        desc.style.fontSize = '12.5px';
                        desc.style.color = '#4B5563';
                        desc.style.marginBottom = '6px';
                        desc.textContent = ev.description;
                        card.appendChild(desc);
                    }

                    const meta = document.createElement('div');
                    meta.style.fontSize = '11px';
                    meta.style.color = '#6B7280';
                    meta.textContent = 'አይነት፡ ' + (ev.event_type || 'ማስታወቂያ');
                    card.appendChild(meta);

                    eventsList.appendChild(card);
                });
            }

            modal.style.display = 'flex';
        }

        function closeDayModalDirect() {
            const modal = document.getElementById('calDayModal');
            if (modal) modal.style.display = 'none';
        }

        function closeDayModal(e) {
            if (e.target.id === 'calDayModal') {
                closeDayModalDirect();
            }
        }

        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDayModalDirect();
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>
