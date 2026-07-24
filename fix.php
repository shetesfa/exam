<?php
session_start();
require_once 'db.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix_duplicates'])) {
    // Find duplicates by name and class_id
    $duplicates_query = "SELECT name, class_id, COUNT(*) as count, MIN(id) as keep_id
                         FROM students 
                         GROUP BY name, class_id 
                         HAVING COUNT(*) > 1";
    $duplicates_result = mysqli_query($conn, $duplicates_query);
    
    $deleted_count = 0;
    while ($row = mysqli_fetch_assoc($duplicates_result)) {
        $name_escaped = mysqli_real_escape_string($conn, $row['name']);
        $class_id = $row['class_id'];
        $keep_id = $row['keep_id'];
        
        // Delete duplicates, keeping the oldest record
        $delete_query = "DELETE FROM students 
                        WHERE name = '$name_escaped' 
                        AND class_id = $class_id 
                        AND id != $keep_id";
        
        if (mysqli_query($conn, $delete_query)) {
            $deleted_count += mysqli_affected_rows($conn);
        }
    }
    
    $message = "ተደጋጋሚ ተማሪዎች ተወግደዋል! (Removed $deleted_count duplicate students!)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>ተደጋጋሚዎችን አስተካክል | Fix Duplicates</title>
    <style>
        :root {
            --brown-dark: #8B4513;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --success-green: #10B981;
            --error-red: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #FAF9F6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            border: 3px solid var(--gold-primary);
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        h1 {
            color: var(--brown-dark);
            margin-bottom: 15px;
        }

        p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .success {
            background: #D1FAE5;
            color: #065F46;
            border: 2px solid var(--success-green);
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
            border: 2px solid var(--error-red);
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-fix {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
            width: 100%;
        }

        .btn-fix:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218,165,32,0.3);
        }

        .btn-back {
            background: var(--brown-dark);
            color: white;
            margin-top: 15px;
        }

        .btn-back:hover {
            background: #A52A2A;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🧹</div>
        <h1>ተደጋጋሚ ተማሪዎችን አስተካክል</h1>
        <p>ይህ በውሂብ ጎታ ውስጥ ያሉ ተደጋጋሚ ተማሪዎችን ያስወግዳል።<br>
        የመጀመሪያውን መዝገብ በመተው ተደጋጋሚዎቹን ይሰርዛል።</p>

        <?php if ($message): ?>
        <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('እርግጠኛ ነዎት? ይህ ክዋኔ ሊቀለበስ አይችልም!')">
            <button type="submit" name="fix_duplicates" class="btn btn-fix">
                🧹 አሁን አስተካክል / Fix Now
            </button>
        </form>

        <a href="promotion.php" class="btn btn-back" style="display: inline-block; margin-top: 15px;">
            ← ወደ ደረጃ ሰንጠረዥ
        </a>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>