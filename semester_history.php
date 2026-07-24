<?php
session_start();
require_once 'db.php';
requireAdmin();

$semester_id = isset($_SESSION['view_semester_id']) ? $_SESSION['view_semester_id'] : (isset($_GET['id']) ? $_GET['id'] : 0);

if(!$semester_id) {
    header("Location: semester.php");
    exit();
}

// Get semester info
$semester_query = "SELECT * FROM semesters WHERE id = $semester_id";
$semester_result = mysqli_query($conn, $semester_query);
$semester = mysqli_fetch_assoc($semester_result);

if(!$semester) {
    header("Location: semester.php");
    exit();
}

// Get teacher assignments for this semester
$assignments_query = "SELECT tc.*, u.name as teacher_name, c.name as class_name
                      FROM teacher_class tc
                      JOIN users u ON tc.teacher_id = u.id
                      JOIN classes c ON tc.class_id = c.id
                      WHERE tc.semester_id = $semester_id
                      ORDER BY c.name";
$assignments = mysqli_query($conn, $assignments_query);

// Get marks for this semester
$marks_query = "SELECT s.name as student_name, c.name as class_name,
                m.assignment, m.mid, m.final, m.total
                FROM marks m
                JOIN students s ON m.student_id = s.id
                JOIN classes c ON m.class_id = c.id
                WHERE m.semester_id = $semester_id
                ORDER BY c.name, s.name";
$marks = mysqli_query($conn, $marks_query);

// Group marks by class
$class_marks = [];
while($mark = mysqli_fetch_assoc($marks)) {
    $class_marks[$mark['class_name']][] = $mark;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images\icon.png">
    <title>ሴሚስተር ታሪክ | አጸደ ትጉሃን </title>
    <style>
        :root {
            --brown-dark: #8B4513;
            --brown-medium: #A52A2A;
            --gold-primary: #FFD700;
            --gold-dark: #DAA520;
            --gold-pale: #FFF8DC;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #FAF9F6;
        }

        .header {
            background: linear-gradient(135deg, #8B4513 0%, #A52A2A 100%);
            color: white;
            padding: 20px 30px;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--gold-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--brown-dark);
        }

        .title h1 {
            font-size: 20px;
            color: var(--gold-primary);
        }

        .nav {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-links {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 10px 20px;
            color: var(--brown-dark);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 600;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--gold-pale);
            color: var(--gold-dark);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .semester-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 3px solid var(--gold-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .semester-header h1 {
            color: var(--brown-dark);
            font-size: 28px;
        }

        .semester-header p {
            color: #666;
            margin-top: 5px;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gold-pale);
        }

        .section-header h2 {
            color: var(--brown-dark);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700 0%, #DAA520 100%);
            color: #8B4513;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--gold-pale);
            color: var(--brown-dark);
            padding: 15px;
            text-align: left;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #E2E8F0;
        }

        tr:hover {
            background: #FEF9E7;
        }

        .class-badge {
            background: var(--gold-primary);
            color: var(--brown-dark);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .total-row {
            background: #FEF9E7;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .semester-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-area">
                <div class="logo-icon">⛪</div>
                <div class="title">
                    <h1>ሴሚስተር ታሪክ</h1>
                    <p>Semester History</p>
                </div>
            </div>
            <a href="semester.php" class="btn btn-primary">← ወደ ሴሚስተር አስተዳደር</a>
        </div>
    </div>

    <div class="nav">
        <div class="nav-links">
            <a href="dashboard_admin.php" class="nav-link">🏠 ዳሽቦርድ</a>
            <a href="manage_classes.php" class="nav-link">📚 ክፍሎች</a>
            <a href="manage_students.php" class="nav-link">👥 ተማሪዎች</a>
            <a href="manage_teachers.php" class="nav-link">👨‍🏫 መምህራን</a>
            <a href="manage_assignments.php" class="nav-link">📋 ክፍል ምደባ</a>
            <a href="semester.php" class="nav-link">📅 ሴሚስተር</a>
            <a href="class_locks.php" class="nav-link active">🔒 ክፍል መቆለፊያ</a>
            <a href="semester_history.php" class="nav-link active">📜 ታሪክ</a>
            <a href="print_results.php" class="nav-link">🖨️ ውጤት ማተሚያ</a>
        </div>
    </div>

    <div class="container">
        <div class="semester-header">
            <div>
                <h1><?php echo htmlspecialchars($semester['name']); ?></h1>
                <p>
                    <span>📅 <?php echo date('F d, Y', strtotime($semester['start_date'])); ?></span>
                    <?php if($semester['end_date']): ?>
                    <span> - <?php echo date('F d, Y', strtotime($semester['end_date'])); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <span class="class-badge"><?php echo $semester['status'] == 'active' ? 'ንቁ' : 'ዝግ'; ?> ሴሚስተር</span>
        </div>

        <!-- Teacher Assignments -->
        <div class="section">
            <div class="section-header">
                <h2><span>👨‍🏫</span> የመምህራን ምደባ</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ክፍል</th>
                            <th>መምህር</th>
                            <th>ሁኔታ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($assignments && mysqli_num_rows($assignments) > 0): ?>
                            <?php while($assignment = mysqli_fetch_assoc($assignments)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                                <td>
                                    <?php if($assignment['locked']): ?>
                                    <span style="color: #EF4444;">🔒 ተቆልፏል</span>
                                    <?php else: ?>
                                    <span style="color: #10B981;">🔓 ክፍት</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 30px;">
                                    ለዚህ ሴሚስተር ምንም ምደባ የለም
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Student Marks -->
        <div class="section">
            <div class="section-header">
                <h2><span>📊</span> የተማሪዎች ውጤት</h2>
            </div>

            <?php if(!empty($class_marks)): ?>
                <?php foreach($class_marks as $class_name => $marks): ?>
                <h3 style="color: var(--brown-dark); margin: 30px 0 15px;"><?php echo $class_name; ?></h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ተማሪ</th>
                                <th>Assignment</th>
                                <th>Mid</th>
                                <th>Final</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach($marks as $mark): 
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                                <td><?php echo formatMark($mark['assignment']); ?></td>
                                <td><?php echo formatMark($mark['mid']); ?></td>
                                <td><?php echo formatMark($mark['final']); ?></td>
                                <td><strong><?php echo formatMark($mark['total']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 50px;">
                    <span style="font-size: 40px; display: block; margin-bottom: 15px;">📭</span>
                    <p>ለዚህ ሴሚስተር ምንም ውጤት የለም</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="print_results.php?semester_id=<?php echo $semester_id; ?>" class="btn btn-primary">
                🖨️ የዚህን ሴሚስተር ውጤት አትም
            </a>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>