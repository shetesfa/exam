<?php
/**
 * mobile_nav.php - Shared mobile-responsive navigation
 * 
 * Usage: include this file inside <body> right after opening tag
 * Requires: $conn, session, pwa_head.php (for CSS + JS)
 * 
 * The caller sets $nav_active = 'dashboard_admin' etc. before including.
 */

$nav_role = $_SESSION['role'] ?? '';
$nav_student = isset($_SESSION['student_id']);
$nav_name = $_SESSION['user_name'] ?? ($_SESSION['student_name'] ?? 'User');
$nav_active = $nav_active ?? '';

// Build nav items based on role
$nav_items = [];

if ($nav_role === 'admin') {
    $nav_items = [
        ['url' => 'dashboard_admin.php',             'icon' => '🏠', 'label' => 'ዳሽቦርድ',               'key' => 'dashboard_admin',             'category' => 'ዋና ማዕከል'],
        ['url' => 'manage_classes.php',               'icon' => '📚', 'label' => 'ክፍሎች',                 'key' => 'manage_classes',               'category' => 'ተማሪዎችና መምህራን'],
        ['url' => 'manage_students.php',              'icon' => '👥', 'label' => 'ተማሪዎች',                'key' => 'manage_students',              'category' => 'ተማሪዎችና መምህራን'],
        ['url' => 'manage_teachers.php',              'icon' => '👨‍🏫', 'label' => 'መምህራን',               'key' => 'manage_teachers',              'category' => 'ተማሪዎችና መምህራን'],
        ['url' => 'manage_assignments.php',           'icon' => '📋', 'label' => 'ክፍል ምደባ',             'key' => 'manage_assignments',           'category' => 'ተማሪዎችና መምህራን'],
        ['url' => 'semester.php',                     'icon' => '📅', 'label' => 'ሴሚስተር',                'key' => 'semester',                     'category' => 'የትምህርት ሂደት'],
        ['url' => 'class_locks.php',                  'icon' => '🔒', 'label' => 'ክፍል መቆለፊያ',          'key' => 'class_locks',                  'category' => 'የትምህርት ሂደት'],
        ['url' => 'attendance_submitter_assign.php',  'icon' => '📋', 'label' => 'የክፍል አቴንዳንስ አባላት', 'key' => 'attendance_submitter_assign',  'category' => 'አቴንዳንስና ክትትል'],
        ['url' => 'attendance_days_control.php',      'icon' => '📅', 'label' => 'የትምህርት ቀናት',         'key' => 'attendance_days_control',      'category' => 'አቴንዳንስና ክትትል'],
        ['url' => 'attendance_controller.php',        'icon' => '📊', 'label' => 'የአቴንዳንስ መቆጣጠሪያ',     'key' => 'attendance_controller',        'category' => 'አቴንዳንስና ክትትል'],
        ['url' => 'teacher_marks_viewer.php',         'icon' => '👁️', 'label' => 'የመምህራን ውጤት',         'key' => 'teacher_marks_viewer',         'category' => 'ውጤትና ምዘና'],
        ['url' => 'print_results.php',                'icon' => '🖨️', 'label' => 'ውጤት ማተሚያ',           'key' => 'print_results',                'category' => 'ውጤትና ምዘና'],
        ['url' => 'manage_users.php',                 'icon' => '👤', 'label' => 'ተጠቃሚዎች',              'key' => 'manage_users',                 'category' => 'ተጠቃሚዎችና ስርዓት'],
        ['url' => 'admin_settings.php',               'icon' => '⚙️', 'label' => 'ቅንብሮች',                'key' => 'admin_settings',               'category' => 'ተጠቃሚዎችና ስርዓት'],
        
        // ተጨማሪ የስርዓት ገጾች
        ['url' => 'lesson_plan_admin_review.php',     'icon' => '📝', 'label' => 'የዕቅድ ግምገማ',           'key' => 'lesson_plan_admin_review',     'category' => 'የትምህርት ሂደት'],
        ['url' => 'promotion.php',                    'icon' => '📈', 'label' => 'ደረጃ ማሳደግ',            'key' => 'promotion',                    'category' => 'የትምህርት ሂደት'],
        ['url' => 'calendar_admin.php',               'icon' => '📅', 'label' => 'ካሌንደር',                'key' => 'calendar_admin',               'category' => 'ካሌንደር'],
        ['url' => 'backup_admin.php',                 'icon' => '💾', 'label' => 'ምትኬ',                   'key' => 'backup_admin',                 'category' => 'ስርዓት አስተዳደር'],
        ['url' => 'audit_log_admin.php',              'icon' => '📜', 'label' => 'የክትትል መዝገብ',           'key' => 'audit_log_admin',              'category' => 'ስርዓት አስተዳደር'],
    ];
    $logout_url = 'logout.php';
    $pwd_url = 'admin_change_password.php';
} elseif ($nav_role === 'teacher') {
    $teacher_user_id = intval($_SESSION['user_id'] ?? 0);
    $nav_semester = function_exists('getCurrentSemester') ? getCurrentSemester($conn) : null;
    $nav_semester_id = $nav_semester ? intval($nav_semester['id']) : 0;
    $can_plan = function_exists('teacherCanCreatePlan') ? teacherCanCreatePlan($conn, $teacher_user_id) : true;

    // Show only the single relevant attendance page for the teacher:
    // Children teachers (ህፃናት) record attendance via dashboard_attendance.php
    // Youth teachers (ወጣቶች) view attendance via teacher_attendance_view.php
    $can_mark = false;
    if (isset($conn) && $teacher_user_id > 0) {
        $can_mark = function_exists('teacherCanWriteAttendance') 
            ? teacherCanWriteAttendance($conn, $teacher_user_id, $nav_semester_id) 
            : (function_exists('canMarkAttendance') && canMarkAttendance($conn, $teacher_user_id, $nav_semester_id));
        if (!$can_mark) {
            $divCheck = dbFetchOne(
                $conn,
                "SELECT COUNT(*) as cnt FROM teacher_class tc
                 JOIN classes c ON tc.class_id = c.id
                 LEFT JOIN grades g ON c.grade_id = g.id
                 WHERE tc.teacher_id = ? AND (g.division_id = 1 OR c.id BETWEEN 7 AND 12)",
                "i",
                [$teacher_user_id]
            );
            $can_mark = ($divCheck && intval($divCheck['cnt']) > 0);
        }
    }

    $attendance_item = $can_mark
        ? ['url' => 'dashboard_attendance.php',    'icon' => '📅', 'label' => 'አቴንዳንስ', 'key' => 'dashboard_attendance']
        : ['url' => 'teacher_attendance_view.php', 'icon' => '📅', 'label' => 'አቴንዳንስ', 'key' => 'teacher_attendance_view'];

    $nav_items = [
        ['url' => 'dashboard_teacher.php',        'icon' => '🏠', 'label' => 'ዳሽቦርድ',      'key' => 'dashboard_teacher'],
        ['url' => 'teacher_marks_viewer.php',     'icon' => '📊', 'label' => 'ውጤቶች',        'key' => 'teacher_marks_viewer'],
        $attendance_item,
        ['url' => 'teacher_marking_scheme.php',   'icon' => '📐', 'label' => 'የውጤት መስፈርት',   'key' => 'teacher_marking_scheme'],
        ['url' => 'teacher_profile.php',          'icon' => '👤', 'label' => 'መረጃዬ',        'key' => 'teacher_profile'],
    ];
    if ($can_plan) {
        $nav_items[] = ['url' => 'lesson_plan_editor.php', 'icon' => '📝', 'label' => 'የትምህርት ዕቅድ', 'key' => 'lesson_plan_editor'];
    }
    $nav_items[] = ['url' => 'calendar_view.php', 'icon' => '📅', 'label' => 'ካሌንደር', 'key' => 'calendar_view'];
    $logout_url = 'logout.php';
    $pwd_url = 'change_password.php';
} elseif ($nav_role === 'attendance_submitter') {
    $nav_items = [
        ['url' => 'dashboard_attendance.php',     'icon' => '🏠', 'label' => 'ዳሽቦርድ',      'key' => 'dashboard_attendance'],
        ['url' => 'calendar_view.php',            'icon' => '📅', 'label' => 'ካሌንደር',       'key' => 'calendar_view'],
    ];
    $logout_url = 'logout.php';
    $pwd_url = 'change_password.php';
} elseif ($nav_student) {
    $nav_items = [
        ['url' => 'dashboard_student.php',        'icon' => '🏠', 'label' => 'ዳሽቦርድ',      'key' => 'dashboard_student'],
        ['url' => 'student_change_pin.php',       'icon' => '🔑', 'label' => 'ፒን ቀይር',     'key' => 'student_change_pin'],
        ['url' => 'calendar_view.php',            'icon' => '📅', 'label' => 'ካሌንደር',       'key' => 'calendar_view'],
    ];
    $logout_url = 'student_logout.php';
    $pwd_url = 'student_change_pin.php';
} else {
    $nav_items = [];
    $logout_url = 'index.php';
    $pwd_url = '';
}
?>
<!-- SITE HEADER -->
<header class="site-header">
  <div class="header-content">
    <div class="logo-area">
      <img src="/exam/images/icon.png" alt="Logo" class="logo-img" 
           onerror="this.style.display='none'">
      <div class="site-title">
        <h1>አጸደ ትጉሃን ሰንበት ትምህርት ቤት</h1>
        <p><?php echo htmlspecialchars($nav_name); ?> | <?php
          echo $nav_role === 'admin' ? 'አስተዳዳሪ' :
               ($nav_role === 'teacher' ? 'መምህር' :
               ($nav_role === 'attendance_submitter' ? 'ጸሐፊ' :
               ($nav_student ? 'ተማሪ' : '')));
        ?></p>
      </div>
    </div>
    <div class="header-actions">
      <?php
      $header_notif_count = 0;
      if (isset($conn) && function_exists('getUnreadNotificationCount')) {
          $check_uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (!empty($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0);
          if ($check_uid > 0) {
              $header_notif_count = getUnreadNotificationCount($conn, $check_uid);
          }
      }
      ?>
      <a href="notifications.php" class="btn btn-sm btn-header-notif" style="position:relative; display:inline-flex; align-items:center; gap:5px;" title="ማሳወቂያዎች">
        <span>🔔</span>
        <span class="btn-text">ማሳወቂያ</span>
        <?php if ($header_notif_count > 0): ?>
          <span style="position:absolute; top:-6px; right:-6px; background:#EF4444; color:#fff; font-size:10px; font-weight:bold; border-radius:50%; min-width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; padding:2px;"><?php echo $header_notif_count; ?></span>
        <?php endif; ?>
      </a>
      <?php if ($pwd_url): ?>
      <a href="<?php echo $pwd_url; ?>" class="btn btn-sm btn-success" title="የይለፍ ቃል ቀይር" style="display:inline-flex; align-items:center; gap:5px;">
        <span>🔒</span> <span class="btn-text">ቀይር</span>
      </a>
      <?php endif; ?>
      <a href="<?php echo $logout_url; ?>" class="btn btn-sm btn-logout" title="ይውጡ" style="display:inline-flex; align-items:center; gap:6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span class="btn-text">ይውጡ</span>
      </a>
    </div>
  </div>
</header>

<!-- NAVIGATION -->
<?php if (!empty($nav_items)): ?>
<nav class="site-nav" id="site-nav">
  <div class="nav-inner">
    <!-- Desktop horizontal nav -->
    <div class="nav-links-desktop">
      <?php foreach ($nav_items as $item): ?>
      <a href="<?php echo $item['url']; ?>" 
         class="nav-link<?php echo ($nav_active === $item['key']) ? ' active' : ''; ?>">
        <?php echo $item['icon']; ?> <?php echo $item['label']; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <!-- Mobile hamburger button -->
    <button id="hamburger-btn" class="hamburger-btn" 
            onclick="toggleMobileNav()" 
            aria-label="Toggle navigation" aria-expanded="false"
            style="position:absolute;top:8px;right:14px;">
      <span class="ham-line"></span>
      <span class="ham-line"></span>
      <span class="ham-line"></span>
    </button>
    <!-- Mobile drawer -->
    <div id="nav-drawer" class="nav-drawer">
      <?php 
      $current_cat = '';
      foreach ($nav_items as $item): 
          if (!empty($item['category']) && $item['category'] !== $current_cat):
              $current_cat = $item['category'];
      ?>
      <div style="font-size:11px; font-weight:700; color:var(--brown-dark); padding:10px 8px 4px; margin-top:4px; border-bottom:1px solid var(--gold-pale); letter-spacing:0.5px;">
        📌 <?php echo htmlspecialchars($current_cat); ?>
      </div>
      <?php endif; ?>
      <a href="<?php echo $item['url']; ?>" 
         class="nav-link<?php echo ($nav_active === $item['key']) ? ' active' : ''; ?>">
        <?php echo $item['icon']; ?> <?php echo $item['label']; ?>
      </a>
      <?php endforeach; ?>
      <hr style="border-color:var(--gold-pale);margin:6px 0;">
      <?php if ($pwd_url): ?>
      <a href="<?php echo $pwd_url; ?>" class="nav-link">🔒 የይለፍ ቃል ቀይር</a>
      <?php endif; ?>
      <button type="button" class="nav-link btn-drawer-dark-toggle" onclick="toggleDarkMode()" style="width:100%; text-align:left; border:none; background:none; cursor:pointer; font-family:inherit;">
        <span id="drawerDarkIcon">🌙</span> <span id="drawerDarkText">የጨለማ ገጽታ</span>
      </button>
      <a href="<?php echo $logout_url; ?>" class="nav-link" style="color:var(--error-red); display:flex; align-items:center; gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span>ይውጡ</span>
      </a>
    </div>
  </div>
</nav>
<?php endif; ?>

<!-- PWA INSTALL BANNER -->
<div id="pwa-install-banner">
  <p>📱 <strong>አፕሊኬሽን</strong> ወደ ስልክዎ ያውርዱ!</p>
  <button id="pwa-install-btn" onclick="installPWA()">⬇️ ጫን</button>
  <button id="pwa-dismiss-btn" onclick="dismissPWA()" aria-label="Dismiss">✕</button>
</div>