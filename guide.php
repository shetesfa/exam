<?php
/**
 * guide.php - የአጸደ ትጉሃን ሰንበት ት/ቤት ሲስተም አጭር የመመሪያ ሊንክ (Short Link Redirect)
 * ወደ ይፋዊው የቴሌግራፍ የተጠቃሚ መመሪያ በቀጥታ ያስተላልፋል።
 */

$telegraph_guide_url = "https://telegra.ph/%E1%8B%A8%E1%8A%A0%E1%8C%B8%E1%8B%B0-%E1%89%B5%E1%8C%89%E1%88%83%E1%8A%95-%E1%88%B0%E1%8A%95%E1%89%A0%E1%89%B5-%E1%89%B5%E1%89%A4%E1%89%B5-%E1%88%B2%E1%88%B5%E1%89%B0%E1%88%9D-%E1%8B%A8%E1%89%B0%E1%8C%A0%E1%89%83%E1%88%9A-%E1%88%98%E1%88%98%E1%88%AA%E1%8B%AB-%E1%8B%A8%E1%89%B0%E1%88%9F%E1%88%8B-%E1%88%9B%E1%88%B5%E1%89%B0%E1%88%AD-%E1%8B%95%E1%89%B5%E1%88%9D-11-03-2";

// 302 Temporary Redirect to Telegraph Guide
header("Location: " . $telegraph_guide_url, true, 302);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($telegraph_guide_url); ?>">
    <title>የተጠቃሚ መመሪያ - አጸደ ትጉሃን ሰንበት ት/ቤት</title>
</head>
<body style="font-family: sans-serif; text-align: center; padding: 40px;">
    <h2>ወደ የተጠቃሚ መመሪያ በማስተላለፍ ላይ...</h2>
    <p>ገጹ በራሱ ካልተከፈተ <a href="<?php echo htmlspecialchars($telegraph_guide_url); ?>">እዚህ ይጫኑ</a>።</p>
</body>
</html>
