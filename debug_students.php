<?php
require_once 'db.php';
requireAdmin();

if (!APP_DEBUG) {
    http_response_code(404);
    die('Not found.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pin'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $student_id = intval($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $hashed_pin = password_hash('123', PASSWORD_DEFAULT);
            dbExecute(
                $conn,
                "INSERT INTO student_logins (student_id, pin, first_login, login_attempts, locked_until) 
                 VALUES (?, ?, 1, 0, NULL)
                 ON DUPLICATE KEY UPDATE pin = VALUES(pin), first_login = 1, login_attempts = 0, locked_until = NULL",
                "is",
                [$student_id, $hashed_pin]
            );
            $msg = "PIN reset to 123 for Student ID #$student_id!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        table { background: white; border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #8B4513; color: white; }
        tr:hover { background: #FFF8DC; }
        .good { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
        .btn { 
            padding: 6px 12px; background: #FFD700; color: #8B4513; 
            border: none; border-radius: 5px; cursor: pointer; font-weight: bold; 
        }
    </style>
</head>
<body>
    <h1>🔍 Student Login Debug</h1>
    
    <h2>All Students with Login Status</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Student Name</th>
            <th>Class</th>
            <th>Has PIN?</th>
            <th>First Login?</th>
            <th>Attempts</th>
            <th>Locked?</th>
            <th>Action</th>
        </tr>
        <?php
        $query = "SELECT s.id, s.name, c.name as class, 
                  sl.pin, sl.first_login, sl.login_attempts, sl.locked_until
                  FROM students s
                  JOIN classes c ON s.class_id = c.id
                  LEFT JOIN student_logins sl ON s.id = sl.student_id
                  ORDER BY c.name, s.name";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $has_pin = !empty($row['pin']) ? '<span class="good">✅ Yes</span>' : '<span class="bad">❌ No PIN</span>';
            $first_login = $row['first_login'] ? 'Yes (needs change)' : 'No';
            $locked = ($row['locked_until'] && strtotime($row['locked_until']) > time()) ? '<span class="bad">🔒 Locked</span>' : 'OK';
            
            // Test PIN verification
            $pin_test = password_verify('123', $row['pin']);
            $pin_status = $pin_test ? '<span class="good">✅ 123 works</span>' : '<span class="bad">❌ 123 fails</span>';
            
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td><strong>{$row['name']}</strong></td>";
            echo "<td>{$row['class']}</td>";
            echo "<td>$has_pin $pin_status</td>";
            echo "<td>$first_login</td>";
            echo "<td>{$row['login_attempts']}</td>";
            echo "<td>$locked</td>";
            $csrfHtml = csrfField();
            echo "<td>
                <form method='POST' style='display:inline;'>
                    $csrfHtml
                    <input type='hidden' name='student_id' value='{$row['id']}'>
                    <button type='submit' name='reset_pin' class='btn' onclick='return confirm(\"Reset PIN to 123?\")'>🔄 Reset PIN</button>
                </form>
            </td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <div style="margin-top: 20px; padding: 15px; background: #FFF8DC; border-radius: 8px;">
        <strong>📋 Instructions:</strong><br>
        1. All students should have PIN = <strong>123</strong><br>
        2. Click "Reset PIN" if any student doesn't have one<br>
        3. Students login with: <strong>Full Name + 123</strong><br>
        4. <a href="student_login.php" target="_blank">Open Student Login Page →</a>
    </div>
</body>
</html>