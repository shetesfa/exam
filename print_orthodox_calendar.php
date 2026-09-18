<?php
require_once 'db.php';

// Access check: allow logged-in users or anyone with active session
$user_id = intval($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';

$today_eth = getCurrentEthiopianDate();
$current_year = intval($today_eth['year'] ?? 2019);

$ethiopian_months = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
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

// 30 Canonical Daily Saints
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
$church_special_feasts = [
    3  => ['name' => 'ቅዱስ ሩፋኤል', 'icon' => '🕊️', 'desc' => 'የአጸደ ትጉሃን ደብር ጠባቂ ሊቀ መላእክት'],
    16 => ['name' => 'ኪዳነ ምሕረት', 'icon' => '👑', 'desc' => 'የእመቤታችን የቃል ኪዳን መታሰቢያ'],
    21 => ['name' => 'እመቤታችን ቅድስት ድንግል ማርያም', 'icon' => '🌸', 'desc' => 'የእመቤታችን ወርሃዊ መታሰቢያ'],
    23 => ['name' => 'ቅዱስ ጊዮርጊስ', 'icon' => '☦️', 'desc' => 'የሊቀ ሰማዕታት ቅዱስ ጊዮርጊስ መታሰቢያ'],
    26 => ['name' => 'አቡነ ሐብተ ማርያም', 'icon' => '🕯️', 'desc' => 'የጻድቁ አቡነ ሐብተ ማርያም መታሰቢያ'],
    27 => ['name' => 'መድኃኔዓለም', 'icon' => '✝️', 'desc' => 'የአምላካችን የመድኃኔዓለም ወርሃዊ በዓል']
];

// 13 Months Annual Feasts (Canonical Register)
$annual_feasts_by_month = [
    1 => [
        'month' => 'መስከረም',
        'feasts' => [
            ['day' => 1, 'title' => 'ርእሰ ዐውደ ዓመት (ቅዱስ ዮሐንስ / እንቁጣጣሽ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'የአዲሱ ዓመት መባቻ፤ ቅዱስ ዮሐንስ መጥምቅ የተሰየመበት', 'icon' => '🌟'],
            ['day' => 10, 'title' => 'ጾመ ዮዲት (የመስቀል ጾም መጀመሪያ)', 'type' => 'አጽዋማት', 'desc' => 'የመስቀል ጾም መግቢያ', 'icon' => '✝️'],
            ['day' => 16, 'title' => 'ደመራ (የመስቀል ዋዜማ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'ንግሥት ዕሌኒ የደመራ ጭስ ተከትላ መስቀሉን ለማግኘት የጀመረችበት ዋዜማ', 'icon' => '🔥'],
            ['day' => 17, 'title' => 'በዓለ መስቀል', 'type' => 'ዓቢይ በዓል', 'desc' => 'ቅዱስ መስቀል የተገኘበት ታላቁ የቤተክርስቲያን በዓል', 'icon' => '✝️'],
            ['day' => 21, 'title' => 'በዓለ ግማደ መስቀል', 'type' => 'ዓቢይ በዓል', 'desc' => 'የቅዱስ መስቀሉ ግማሽ ወደ ግሸን ደብረ ከርቤ የገባበት መታሰቢያ', 'icon' => '✝️'],
        ]
    ],
    2 => [
        'month' => 'ጥቅምት',
        'feasts' => [
            ['day' => 5, 'title' => 'አቡነ ገብረ መንፈስ ቅዱስ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'ተሰጥዎ አቡነ ገብረ መንፈስ ቅዱስ', 'icon' => '🕊️'],
            ['day' => 14, 'title' => 'አቡነ አረጋዊ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'ስውረታቸው / በዓለ ዕረፍታቸው በደብረ ዳሞ', 'icon' => '🕊️'],
            ['day' => 24, 'title' => 'አቡነ ተክለ ሃይማኖት', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'ፍልሰተ አጽማቸው ወደ ደብረ ሊባኖስ', 'icon' => '🕊️'],
            ['day' => 27, 'title' => 'ጥቅምት መድኃኔዓለም', 'type' => 'ዓቢይ በዓል', 'desc' => 'እግዚአብሔር ለኖኅ በደመና የቀስተ ደመና ቃል ኪዳን የገባበት', 'icon' => '✝️'],
        ]
    ],
    3 => [
        'month' => 'ኅዳር',
        'feasts' => [
            ['day' => 6, 'title' => 'ደብረ ቁስቋም', 'type' => 'ዓቢይ በዓል', 'desc' => 'የእመቤታችን ከልጇ ጋር የስደት ፍጻሜ መታሰቢያ', 'icon' => '🌸'],
            ['day' => 8, 'title' => 'አሥራ ሁለቱ አበው ነቢያት', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የአሥራ ሁለቱ ደቂቀ ነቢያት መታሰቢያ', 'icon' => '🕊️'],
            ['day' => 9, 'title' => 'ሠለስቱ ምዕት (፫፻፲፰ቱ የኒቂያ ሊቃውንት)', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'በኒቂያ ጉባኤ ሃይማኖትን ያጸኑ 318ቱ ሊቃውንት መታሰቢያ', 'icon' => '📜'],
            ['day' => 12, 'title' => 'ቅዱስ ሚካኤል (ኅዳር ሚካኤል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'ታላቁ የመልአኩ ቅዱስ ሚካኤል ዓመታዊ በዓል', 'icon' => '🕊️'],
            ['day' => 15, 'title' => 'ጾመ ነቢያት ዋዜማ', 'type' => 'አጽዋማት', 'desc' => 'የገና ጾም ዋዜማ መታሰቢያ', 'icon' => '🌸'],
            ['day' => 16, 'title' => 'ጾመ ነቢያት (የገና ጾም መግቢያ)', 'type' => 'አጽዋማት', 'desc' => 'የገና ጾም መጀመሪያ (ጾመ ፊልጶስ)', 'icon' => '🌸'],
            ['day' => 21, 'title' => 'ጽዮን ማርያም', 'type' => 'ዓቢይ በዓል', 'desc' => 'ታቦተ ጽዮን ወደ ኢትዮጵያ አክሱም የገባችበት ታላቅ በዓል', 'icon' => '🌸'],
            ['day' => 24, 'title' => 'ሃያ አራቱ ካህናተ ሰማይ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'በሰማይ በእግዚአብሔር ዙፋን ፊት የሚያጥኑ 24ቱ ካህናት መታሰቢያ', 'icon' => '👑'],
            ['day' => 25, 'title' => 'ቅዱስ መርቆሬዎስ ሰማዕት', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የሰማዕቱ ቅዱስ መርቆሬዎስ ዓመታዊ በዓል', 'icon' => '☦️'],
        ]
    ],
    4 => [
        'month' => 'ታኅሣሥ',
        'feasts' => [
            ['day' => 3, 'title' => 'በዓታ ለማርያም', 'type' => 'ዓቢይ በዓል', 'desc' => 'እመቤታችን በሦስት ዓመቷ ወደ ቤተ መቅደስ የገባችበት', 'icon' => '🌸'],
            ['day' => 19, 'title' => 'ቅዱስ ገብርኤል ሊቀ መላእክት (ታኅሣሥ ገብርኤል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'ሠለስቱ ደቂቅን ከእቶን እሳት ያዳነበት ታላቅ ዓመታዊ በዓል', 'icon' => '🕊️'],
            ['day' => 22, 'title' => 'ቅዱስ ደቅስዮስ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የቅዱስ ደቅስዮስ ሊቀ ጳጳስ መታሰቢያ', 'icon' => '👑'],
            ['day' => 24, 'title' => 'አቡነ ተክለ ሃይማኖት ልደት', 'type' => 'ዓቢይ በዓል', 'desc' => 'የጻድቁ አቡነ ተክለ ሃይማኖት የልደት በዓል', 'icon' => '🕯️'],
            ['day' => 28, 'title' => 'በዓለ አማኑኤል (የገና ዋዜማ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'በዘመነ ዮሐንስ የገና ዋዜማ (በዘመነ ዮሐንስ ገና ታኅሣሥ 28 ይከበራል)', 'icon' => '✝️'],
            ['day' => 29, 'title' => 'በዓለ ልደት (ገና - የጌታችን ልደት)', 'type' => 'ዓቢይ የጌታ በዓል', 'desc' => 'የጌታችን የመድኃኔዓለም ኢየሱስ ክርስቶስ የልደት በዓል', 'icon' => '🌟'],
        ]
    ],
    5 => [
        'month' => 'ጥር',
        'feasts' => [
            ['day' => 6, 'title' => 'ግዝረተ ክርስቶስ', 'type' => 'ንዑስ የጌታ በዓል', 'desc' => 'የጌታችን በስምንተኛው ቀን የተገዘረበት መታሰቢያ', 'icon' => '👑'],
            ['day' => 11, 'title' => 'በዓለ ጥምቀት', 'type' => 'ዓቢይ የጌታ በዓል', 'desc' => 'ጌታችን በዮርዳኖስ በቅዱስ ዮሐንስ እጅ የተጠመቀበት ታላቁ በዓለ ኤጲፋንያ', 'icon' => '🕊️'],
            ['day' => 12, 'title' => 'ቃና ዘገሊላ / ቅዱስ ሚካኤል', 'type' => 'ንዑስ የጌታ በዓል', 'desc' => 'ጌታችን ውኃውን ወደ ወይን የለወጠበት ተአምርና የቅዱስ ሚካኤል በዓል', 'icon' => '🌟'],
            ['day' => 18, 'title' => 'ቅዱስ ጊዮርጊስ (ፍልሰተ አጽም)', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የቅዱስ ጊዮርጊስ ፍልሰተ አጽም መታሰቢያ', 'icon' => '☦️'],
            ['day' => 21, 'title' => 'አስተርእዮ ማርያም (ዕረፍተ ድንግል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'የእመቤታችን ቅድስት ድንግል ማርያም የዕረፍት በዓል', 'icon' => '🌸'],
            ['day' => 22, 'title' => 'ቅዱስ ዑራኤል ሊቀ መላእክት', 'type' => 'ዓቢይ በዓል', 'desc' => 'የሊቀ መላእክት ቅዱስ ዑራኤል ዓመታዊ መታሰቢያ', 'icon' => '🕊️'],
        ]
    ],
    6 => [
        'month' => 'የካቲት',
        'feasts' => [
            ['day' => 8, 'title' => 'አባ ኪሮስ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የጻድቁ አባ ኪሮስ ዓመታዊ መታሰቢያ', 'icon' => '🕯️'],
            ['day' => 16, 'title' => 'ኪዳነ ምሕረት (ታላቁ የቃል ኪዳን በዓል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'እግዚአብሔር ለእመቤታችን ዓለምን ሁሉ የምታድንበትን ቃል ኪዳን የሰጠበት', 'icon' => '🌸'],
            ['day' => 23, 'title' => 'ቅዱስ ጊዮርጊስ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የሊቀ ሰማዕታት ቅዱስ ጊዮርጊስ ዓመታዊ መታሰቢያ', 'icon' => '☦️'],
        ]
    ],
    7 => [
        'month' => 'መጋቢት',
        'feasts' => [
            ['day' => 10, 'title' => 'ተረክቦተ መስቀል', 'type' => 'ዓቢይ በዓል', 'desc' => 'ንግሥት ዕሌኒ ቅዱስ መስቀሉን ቆፍራ ያገኘችበት መታሰቢያ', 'icon' => '✝️'],
            ['day' => 27, 'title' => 'መጋቢት መድኃኔዓለም', 'type' => 'ዓቢይ የጌታ በዓል', 'desc' => 'ጌታችን በመስቀል ላይ ተሰቅሎ ዓለምን ያዳነበት ታላቁ የስቅለት መታሰቢያ', 'icon' => '✝️'],
            ['day' => 29, 'title' => 'በዓለ ጽንሰት', 'type' => 'ዓቢይ የጌታ በዓል', 'desc' => 'እመቤታችን ጌታችንን በማኅፀኗ የፀነሰችበት የብስራት በዓል', 'icon' => '🌟'],
        ]
    ],
    8 => [
        'month' => 'ሚያዝያ',
        'feasts' => [
            ['day' => 23, 'title' => 'ቅዱስ ጊዮርጊስ (ሰማዕትነቱ / ዕረፍቱ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'ሊቀ ሰማዕታት ቅዱስ ጊዮርጊስ አክሊለ ሰማዕትነትን የተቀዳጀበት ታላቁ በዓል', 'icon' => '☦️'],
        ]
    ],
    9 => [
        'month' => 'ግንቦት',
        'feasts' => [
            ['day' => 1, 'title' => 'ልደታ ለማርያም (ግንቦት ልደታ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'የእመቤታችን የቅድስት ድንግል ማርያም የልደት በዓል', 'icon' => '🌸'],
            ['day' => 12, 'title' => 'ቅዱስ ሚካኤል ሊቀ መላእክት (ግንቦት ሚካኤል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'አፎሚያን ከዲያብሎስ እጅ ያዳነበት መታሰቢያ', 'icon' => '🕊️'],
            ['day' => 20, 'title' => 'ጽንሰታ ለማርያም', 'type' => 'ዓቢይ በዓል', 'desc' => 'ኢያቄምና ሐና እመቤታችንን የፀነሱበት መታሰቢያ', 'icon' => '🌸'],
            ['day' => 21, 'title' => 'አስተርእዮተ ማርያም', 'type' => 'ዓቢይ በዓል', 'desc' => 'እመቤታችን በደብረ ምጥማቅ በብርሃን ተገልጣ ለሕዝቡ የታየችበት', 'icon' => '🌸'],
        ]
    ],
    10 => [
        'month' => 'ሰኔ',
        'feasts' => [
            ['day' => 12, 'title' => 'ቅዱስ ሚካኤል (ሰኔ ሚካኤል)', 'type' => 'ዓቢይ በዓል', 'desc' => 'ቅዱስ ሚካኤል ባሕራንን ከሞት ያዳነበት ዓመታዊ በዓል', 'icon' => '🕊️'],
            ['day' => 20, 'title' => 'ሕንፀተ ቤተክርስቲያን', 'type' => 'ዓቢይ በዓል', 'desc' => 'በፊልጵስዩስ የመጀመሪያዋ የእመቤታችን ቤተክርስቲያን የታነጸችበት', 'icon' => '⛪'],
            ['day' => 30, 'title' => 'ቅዱስ ዮሐንስ መጥምቅ / ቅዱስ ማርቆስ', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'የቅዱስ ዮሐንስ መጥምቅ ልደትና የወንጌላዊው ቅዱስ ማርቆስ ዕረፍት', 'icon' => '🕊️'],
        ]
    ],
    11 => [
        'month' => 'ሐምሌ',
        'feasts' => [
            ['day' => 5, 'title' => 'ቅዱሳን ጴጥሮስ ወጳውሎስ', 'type' => 'ዓቢይ በዓል', 'desc' => 'የሐዋርያት መሪዎች ቅዱስ ጴጥሮስና ቅዱስ ጳውሎስ ሰማዕትነት / የሐዋርያት ጾም ፍጻሜ', 'icon' => '👑'],
            ['day' => 7, 'title' => 'ቅድስት ሥላሴ (ሐምሌ ሥላሴ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'አብርሃም ሥላሴን በድንኳኑ ያስተናገደበት ታላቅ ዓመታዊ በዓል', 'icon' => '✝️'],
            ['day' => 19, 'title' => 'ቅዱስ ቂርቆስና ቅድስት ኢየሉጣ', 'type' => 'ዓቢይ በዓል', 'desc' => 'ሕፃኑ ቅዱስ ቂርቆስና እናቱ ቅድስት ኢየሉጣ በእሳት ውስጥ ሰማዕት የሆኑበት', 'icon' => '👑'],
        ]
    ],
    12 => [
        'month' => 'ነሐሴ',
        'feasts' => [
            ['day' => 1, 'title' => 'ጾመ ፍልሰታ መጀመሪያ', 'type' => 'አጽዋማት', 'desc' => 'የእመቤታችን የፍልሰታ ጾም መግቢያ', 'icon' => '🌸'],
            ['day' => 13, 'title' => 'ደብረ ታቦር (ቡሄ / ብርሃነ መለኮት)', 'type' => 'ዓቢይ የጌታ በዓል', 'desc' => 'ጌታችን በደብረ ታቦር ብርሃነ መለኮቱን የገለጠበት ታላቅ በዓል', 'icon' => '🌟'],
            ['day' => 16, 'title' => 'ፍልሰታ ለማርያም (ዕርገተ ሥጋዋ)', 'type' => 'ዓቢይ በዓል', 'desc' => 'የእመቤታችን የቅድስት ድንግል ማርያም ሥጋዋ ወደ ሰማይ ያረገበት ታላቅ በዓል', 'icon' => '🌸'],
            ['day' => 24, 'title' => 'አቡነ ተክለ ሃይማኖት ዕረፍት', 'type' => 'ዓቢይ በዓል', 'desc' => 'የኢትዮጵያው ፀሐይ ጻድቁ አቡነ ተክለ ሃይማኖት በደብረ ሊባኖስ ያረፉበት', 'icon' => '🕯️'],
        ]
    ],
    13 => [
        'month' => 'ጳጉሜን',
        'feasts' => [
            ['day' => 3, 'title' => 'ቅዱስ ሩፋኤል ሊቀ መላእክት', 'type' => 'ዓቢይ በዓል', 'desc' => 'ሊቀ መላእክት ቅዱስ ሩፋኤል ዓመታዊ ክብረ በዓል (የአጸደ ትጉሃን ደብር ጠባቂ)', 'icon' => '🕊️'],
            ['day' => 6, 'title' => 'ጳጉሜን 6 (ዘመነ ዮሐንስ መታሰቢያ)', 'type' => 'ዓመታዊ መታሰቢያ', 'desc' => 'በዘመነ ዮሐንስ የሚከበረው ስድስተኛዋ የጳጉሜን ዕለት', 'icon' => '🌟'],
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ወርሃዊ ዝክረ ቅዱሳንና ዓመታዊ በዓላት መዝገብ | አጸደ ትጉሃን ሰንበት ትምህርት ቤት</title>
    <link rel="icon" href="images/icon.png" type="image/png">
    <style>
        :root {
            --brown-deep: #5A2D0C;
            --brown-dark: #78350F;
            --gold-primary: #D97706;
            --gold-light: #FDE68A;
            --gold-pale: #FFFBEB;
            --bg-page: #FBF9F5;
            --text-dark: #1E293B;
            --border-light: #E2E8F0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', 'Nyala', 'Abyssinica SIL', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-dark);
            line-height: 1.5;
            padding-bottom: 60px;
        }

        /* Screen Toolbar (Hidden when printing) */
        .screen-toolbar {
            background: #FFFFFF;
            border-bottom: 2px solid #E5E7EB;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-print {
            background: linear-gradient(135deg, #B45309, #78350F);
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(120, 53, 15, 0.25);
        }
        .btn-print:hover {
            background: #92400E;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: #FFFFFF;
            border: 1.5px solid #CBD5E1;
            color: #475569;
        }
        .btn-outline:hover {
            border-color: #78350F;
            color: #78350F;
        }

        /* Main Printable Document Wrapper */
        .doc-wrapper {
            max-width: 980px;
            margin: 24px auto;
            background: #FFFFFF;
            padding: 36px 40px;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.05);
        }

        /* Church Ornate Header */
        .doc-header {
            text-align: center;
            border-bottom: 3px double #B45309;
            padding-bottom: 20px;
            margin-bottom: 24px;
            position: relative;
        }

        .cross-symbol {
            font-size: 34px;
            color: #B45309;
            margin-bottom: 6px;
            display: block;
        }

        .church-title {
            font-size: 24px;
            font-weight: 900;
            color: var(--brown-deep);
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .doc-registry-title {
            font-size: 18px;
            font-weight: 800;
            color: #B45309;
            margin-bottom: 8px;
        }

        .doc-subtitle {
            font-size: 13.5px;
            font-weight: 600;
            color: #64748B;
        }

        /* Metadata Banner */
        .meta-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            background: #FFFBEB;
            border: 1.5px solid #FDE68A;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 28px;
            font-size: 13px;
        }

        .meta-item strong {
            color: #78350F;
            display: block;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-item span {
            font-weight: 700;
            color: #1E293B;
        }

        /* Section Heading */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #78350F;
            padding-bottom: 8px;
            margin-top: 32px;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown-deep);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-badge {
            font-size: 12px;
            font-weight: 700;
            background: #FEF3C7;
            color: #92400E;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid #FCD34D;
        }

        /* Tables */
        .orthodox-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .orthodox-table th {
            background: #78350F;
            color: #FFFFFF;
            font-weight: 700;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid #5A2D0C;
        }

        .orthodox-table td {
            padding: 9px 8px;
            border: 1px solid #E2E8F0;
            vertical-align: middle;
        }

        .orthodox-table tbody tr:nth-child(even) {
            background: #FAF8F5;
        }

        .orthodox-table tbody tr:hover {
            background: #FFFBEB;
        }

        .day-col {
            text-align: center;
            width: 60px;
            font-weight: 800;
        }
        .day-col strong {
            font-size: 15px;
            color: var(--brown-deep);
            display: block;
            line-height: 1.1;
        }
        .day-col small {
            font-size: 11px;
            color: #92400E;
        }

        .patron-badge {
            display: inline-block;
            background: #FEF3C7;
            border: 1.5px solid #F59E0B;
            color: #92400E;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11.5px;
        }

        .check-col {
            width: 90px;
            text-align: center;
        }

        .check-box-label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #059669;
            background: #ECFDF5;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #A7F3D0;
        }

        .month-sub-header {
            background: #FEF3C7;
            border: 1.5px solid #FCD34D;
            border-left: 6px solid #B45309;
            padding: 8px 14px;
            font-weight: 800;
            font-size: 15px;
            color: #78350F;
            margin-top: 20px;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Sign-off Seal Box */
        .approval-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 2px dashed #CBD5E1;
            break-inside: avoid;
        }

        .approval-card {
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 16px;
            background: #FAF8F5;
        }

        .approval-card h4 {
            font-size: 14px;
            color: var(--brown-deep);
            margin-bottom: 12px;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 6px;
        }

        .sign-row {
            margin-bottom: 8px;
            font-size: 13px;
        }
        .sign-line {
            display: inline-block;
            width: 65%;
            border-bottom: 1px dotted #888;
            margin-left: 6px;
        }

        .seal-box {
            width: 110px;
            height: 110px;
            border: 2px dashed #B45309;
            border-radius: 50%;
            margin: 12px auto 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #B45309;
            background: #FFFDF7;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #FFFFFF !important;
                color: #000000 !important;
                padding: 0 !important;
            }
            .screen-toolbar {
                display: none !important;
            }
            .doc-wrapper {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            .orthodox-table th {
                background: #78350F !important;
                color: #FFFFFF !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .month-sub-header {
                background: #FEF3C7 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .meta-strip {
                background: #FFFBEB !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .page-break {
                page-break-before: always;
                break-before: page;
            }
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            thead {
                display: table-header-group;
            }
            @page {
                size: A4 portrait;
                margin: 12mm 10mm 15mm 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN NAVIGATION / PRINT TOOLBAR -->
    <div class="screen-toolbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="calendar_view.php" class="btn btn-outline" title="ወደ ካላንደር ገጽ ተመለስ">
                ← ወደ ቀን መቁጠሪያ
            </a>
            <a href="index.php" class="btn btn-outline" title="ወደ ዳሽቦርድ ተመለስ">
                🏠 ዳሽቦርድ
            </a>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:13px; color:#64748B;">ለህትመት ወይም በPDF ለማውረድ፡</span>
            <button onclick="window.print()" class="btn btn-print">
                🖨️ በPDF አትም / Print PDF
            </button>
        </div>
    </div>

    <div class="doc-wrapper">
        
        <!-- CHURCH OFFICIAL HEADER -->
        <header class="doc-header">
            <span class="cross-symbol">☦️</span>
            <h1 class="church-title">አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
            <h2 class="doc-registry-title">የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ወርሃዊ ዝክረ ቅዱሳንና ዓመታዊ በዓላት መዝገብ</h2>
            <p class="doc-subtitle">ቀኖናዊ የበዓላትና አጽዋማት ማረጋገጫና ኦዲት ሰነድ (Official Canonical Feast Registry & Verification Audit Sheet)</p>
        </header>

        <!-- METADATA STRIP -->
        <div class="meta-strip">
            <div class="meta-item">
                <strong>የሰነድ መለያ ቁጥር</strong>
                <span>አት/ሰት/በዓላት/፳፻፲፱-01</span>
            </div>
            <div class="meta-item">
                <strong>የታተመበት ዓመተ ምሕረት</strong>
                <span><?php echo $current_year; ?> ዓ.ም (<?php echo toEthiopicYear($current_year); ?>)</span>
            </div>
            <div class="meta-item">
                <strong>ዘመነ ወንጌላዊ</strong>
                <span><?php echo getEvangelistName($current_year); ?></span>
            </div>
            <div class="meta-item">
                <strong>የሰነድ ሁኔታ</strong>
                <span style="color:#059669;">✓ ይፋዊና የጸደቀ ቀኖናዊ መዝገብ</span>
            </div>
        </div>

        <!-- SECTION 1: 30 DAILY COMMEMORATIVE SAINTS -->
        <section id="sectionDailySaints">
            <div class="section-header">
                <h3 class="section-title">
                    <span>📜 ክፍል ፩፡</span>
                    <span>ወርሃዊ የዕለታት ዝክረ ቅዱሳን (ከቀን ፩ እስከ ፴)</span>
                </h3>
                <span class="section-badge">፴ (30) ዕለታት</span>
            </div>
            <p style="font-size:13px; color:#555; margin-bottom:12px;">
                በኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ቀኖና መሠረት በየወሩ ከ1 እስከ 30 የሚዘከሩ ቅዱሳን፣ ጻድቃን፣ ሰማዕታትና መላእክት ዝርዝር መዝገብ።
            </p>

            <table class="orthodox-table">
                <thead>
                    <tr>
                        <th style="width:65px; text-align:center;">ዕለት</th>
                        <th>የዕለቱ ቅዱስ (Commemorated Saint)</th>
                        <th style="width:200px;">የአጸደ ትጉሃን ደብር ልዩ በዓል</th>
                        <th style="width:105px; text-align:center;">ማረጋገጫ</th>
                        <th style="width:140px;">የአስተዳደር ማስታወሻ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($d = 1; $d <= 30; $d++): 
                        $is_special = isset($church_special_feasts[$d]);
                        $special_info = $church_special_feasts[$d] ?? null;
                    ?>
                    <tr style="<?php echo $is_special ? 'background:#FFFDF0; font-weight:600;' : ''; ?>">
                        <td class="day-col">
                            <strong><?php echo $d; ?></strong>
                            <small><?php echo toEthiopicNumber($d); ?></small>
                        </td>
                        <td>
                            <div style="font-size:13.5px; color:#1E293B;">
                                <?php echo htmlspecialchars($daily_saints[$d]); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($is_special): ?>
                                <span class="patron-badge">
                                    <?php echo $special_info['icon']; ?> <?php echo htmlspecialchars($special_info['name']); ?>
                                </span>
                                <div style="font-size:10.5px; color:#92400E; margin-top:2px;">
                                    <?php echo htmlspecialchars($special_info['desc']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8; font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="check-col">
                            <span class="check-box-label">
                                <span>[ ✓ ]</span>
                                <span>ተካትቷል</span>
                            </span>
                        </td>
                        <td style="font-size:12px; color:#64748B;">
                            <?php echo $is_special ? 'ደማቅ ዝክር' : 'መደበኛ ወርሃዊ ዝክር'; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </section>

        <!-- PAGE BREAK FOR CLEAN PRINTING -->
        <div class="page-break"></div>

        <!-- SECTION 2: 13 MONTHS ANNUAL FEASTS -->
        <section id="sectionAnnualFeasts">
            <div class="section-header">
                <h3 class="section-title">
                    <span>🌟 ክፍል ፪፡</span>
                    <span>የ፲፫ቱ (13ቱ) አውደ አኅዋራት ዓመታዊ የቤተክርስቲያን በዓላት (ከመስከረም እስከ ጳጉሜን)</span>
                </h3>
                <span class="section-badge">፲፫ቱ ወራት</span>
            </div>
            <p style="font-size:13px; color:#555; margin-bottom:14px;">
                በዓመቱ ውስጥ በ13ቱም ወራት የሚከበሩ ዓበይትና ንዑሳን የጌታ በዓላት፣ የእመቤታችን፣ የቅዱሳን መላእክትና ጻድቃን ዓመታዊ ክብረ በዓላት ማረጋገጫ ሠንጠረዥ።
            </p>

            <?php foreach ($annual_feasts_by_month as $mNum => $mData): ?>
                <div class="month-sub-header">
                    <span>📅 ወርኃ <?php echo $mData['month']; ?> (ወር #<?php echo $mNum; ?>)</span>
                    <span style="font-size:12px; font-weight:700; color:#92400E;"><?php echo count($mData['feasts']); ?> ዓመታዊ በዓላት</span>
                </div>

                <table class="orthodox-table">
                    <thead>
                        <tr>
                            <th style="width:65px; text-align:center;">ቀን</th>
                            <th>የበዓሉ ስም እና መንፈሳዊ ታሪክ</th>
                            <th style="width:130px;">የበዓሉ ደረጃ</th>
                            <th style="width:105px; text-align:center;">ማረጋገጫ</th>
                            <th style="width:130px;">ማስታወሻ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mData['feasts'] as $f): ?>
                        <tr>
                            <td class="day-col">
                                <strong><?php echo $f['day']; ?></strong>
                                <small><?php echo toEthiopicNumber($f['day']); ?></small>
                            </td>
                            <td>
                                <div style="font-size:13.5px; font-weight:700; color:#1E293B;">
                                    <?php echo $f['icon']; ?> <?php echo htmlspecialchars($f['title']); ?>
                                </div>
                                <div style="font-size:12px; color:#555; margin-top:2px;">
                                    <?php echo htmlspecialchars($f['desc']); ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:11.5px; font-weight:700; color:#78350F; background:#FFFBEB; border:1px solid #FCD34D; padding:2px 7px; border-radius:4px;">
                                    <?php echo htmlspecialchars($f['type']); ?>
                                </span>
                            </td>
                            <td class="check-col">
                                <span class="check-box-label">
                                    <span>[ ✓ ]</span>
                                    <span>ተካትቷል</span>
                                </span>
                            </td>
                            <td style="font-size:12px; color:#666;">
                                የጸደቀ የቀን መቁጠሪያ
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </section>

        <!-- SECTION 3: OFFICIAL SIGN-OFF & STAMP -->
        <section class="approval-grid">
            <div class="approval-card">
                <h4>✍️ ያረጋገጠውና ያዘጋጀው የትምህርት ክፍል</h4>
                <div class="sign-row"><strong>ኃላፊ ስም፡</strong> <span class="sign-line"></span></div>
                <div class="sign-row"><strong>የሥራ ድርሻ፡</strong> የሰንበት ት/ቤት ትምህርት ክፍል ኃላፊ</div>
                <div class="sign-row"><strong>ፊርማ፡</strong> <span class="sign-line"></span></div>
                <div class="sign-row"><strong>ቀን፡</strong> _____ / _____ / <?php echo $current_year; ?> ዓ.ም</div>
            </div>

            <div class="approval-card">
                <h4>🏛️ የሰንበት ትምህርት ቤቱ አስተዳደር ማረጋገጫና ማህተም</h4>
                <div class="sign-row"><strong>የዋና ጸሐፊ/ሰብሳቢ ስም፡</strong> <span class="sign-line"></span></div>
                <div class="sign-row"><strong>ፊርማ፡</strong> <span class="sign-line"></span></div>
                <div class="seal-box">
                    የሰንበት ት/ቤቱ<br>ይፋዊ ማህተም<br>(Official Seal)
                </div>
            </div>
        </section>

        <!-- FOOTER NOTICE -->
        <footer style="margin-top:30px; text-align:center; font-size:11.5px; color:#888; border-top:1px solid #E2E8F0; padding-top:12px;">
            «አጸደ ትጉሃን ሰንበት ትምህርት ቤት» · የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተክርስቲያን ሕጋዊና ቀኖናዊ የበዓላት መዝገብ ሰነድ · <?php echo $current_year; ?> ዓ.ም
        </footer>
    </div>

</body>
</html>
