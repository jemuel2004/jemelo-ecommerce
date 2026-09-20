<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Only admins may manage staff
if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    $_SESSION['staff_flash'] = ['msg' => 'Access denied.', 'type' => 'danger'];
    redirect(SITE_URL . '/admin/staff.php');
}

verify_csrf();

$db     = getDB();
$action = $_POST['action'] ?? '';

// Helper — redirect back with flash message
function staff_back(string $msg, string $type = 'success'): never {
    $_SESSION['staff_flash'] = ['msg' => $msg, 'type' => $type];
    redirect(SITE_URL . '/admin/staff.php');
}

switch ($action) {

    // ── Add new staff account ─────────────────────────────────
    case 'add':
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name']  ?? '');
        $email    = strtolower(trim($_POST['email']    ?? ''));
        $username = trim($_POST['username'] ?? '') ?: null;
        $phone    = trim($_POST['phone']    ?? '');
        $pwd      = $_POST['password'] ?? '';
        $conf     = $_POST['confirm']  ?? '';
        $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$first || !$last || !$email || !$pwd) {
            staff_back('All required fields must be filled.', 'danger');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) staff_back('Invalid email address.', 'danger');
        if (strlen($pwd) < 8) staff_back('Password must be at least 8 characters.', 'danger');
        if ($pwd !== $conf)   staff_back('Passwords do not match.', 'danger');
        if ($username && (strlen($username) < 3 || !preg_match('/^[\w.-]+$/', $username))) {
            staff_back('Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores.', 'danger');
        }

        // Check duplicate email
        $chk = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) staff_back('Email is already registered.', 'danger');

        // Check duplicate username
        if ($username) {
            $chk2 = $db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $chk2->execute([$username]);
            if ($chk2->fetch()) staff_back('Username is already taken.', 'danger');
        }

        $name   = trim("$first $last");
        $hash   = password_hash($pwd, PASSWORD_DEFAULT);
        $avatar = 'default.png';

        // Handle avatar upload
        if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $up = upload_image($_FILES['avatar'], 'avatars');
            if ($up) $avatar = $up;
        }

        $ins = $db->prepare(
            "INSERT INTO users (name, first_name, last_name, email, username, password, phone, avatar, role, status)
             VALUES (?,?,?,?,?,?,?,?,'staff',?)"
        );
        $ins->execute([$name, $first, $last, $email, $username, $hash, $phone, $avatar, $status]);
        staff_back("Staff account for $name has been created.");

    // ── Edit existing staff account ───────────────────────────
    case 'edit':
        $id       = (int)($_POST['id'] ?? 0);
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name']  ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $username = trim($_POST['username'] ?? '') ?: null;
        $phone    = trim($_POST['phone']  ?? '');
        $pwd      = $_POST['password'] ?? '';
        $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$id || !$first || !$last || !$email) staff_back('Required fields are missing.', 'danger');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) staff_back('Invalid email address.', 'danger');
        if ($username && (strlen($username) < 3 || !preg_match('/^[\w.-]+$/', $username))) {
            staff_back('Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores.', 'danger');
        }

        // Must be a staff account
        $st = $db->prepare("SELECT * FROM users WHERE id=? AND role='staff' LIMIT 1");
        $st->execute([$id]);
        $existing = $st->fetch();
        if (!$existing) staff_back('Staff account not found.', 'danger');

        // Duplicate email check (excluding self)
        $chk = $db->prepare('SELECT id FROM users WHERE email=? AND id!=? LIMIT 1');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) staff_back('Email is already used by another account.', 'danger');

        // Duplicate username check (excluding self)
        if ($username) {
            $chk2 = $db->prepare('SELECT id FROM users WHERE username=? AND id!=? LIMIT 1');
            $chk2->execute([$username, $id]);
            if ($chk2->fetch()) staff_back('Username is already taken by another account.', 'danger');
        }

        $name   = trim("$first $last");
        $avatar = $existing['avatar'];

        // Handle avatar upload
        if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $up = upload_image($_FILES['avatar'], 'avatars');
            if ($up) {
                if ($avatar && $avatar !== 'default.png') {
                    $old = UPLOAD_PATH . 'avatars/' . $avatar;
                    if (file_exists($old)) @unlink($old);
                }
                $avatar = $up;
            }
        }

        if ($pwd) {
            if (strlen($pwd) < 8) staff_back('New password must be at least 8 characters.', 'danger');
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $upd  = $db->prepare(
                "UPDATE users SET name=?, first_name=?, last_name=?, email=?, username=?, phone=?, avatar=?, status=?, password=? WHERE id=?"
            );
            $upd->execute([$name, $first, $last, $email, $username, $phone, $avatar, $status, $hash, $id]);
        } else {
            $upd = $db->prepare(
                "UPDATE users SET name=?, first_name=?, last_name=?, email=?, username=?, phone=?, avatar=?, status=? WHERE id=?"
            );
            $upd->execute([$name, $first, $last, $email, $username, $phone, $avatar, $status, $id]);
        }
        staff_back("Staff account for $name has been updated.");

    // ── Toggle active/inactive ────────────────────────────────
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) staff_back('Invalid request.', 'danger');

        $st = $db->prepare("SELECT name, status FROM users WHERE id=? AND role='staff' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) staff_back('Staff account not found.', 'danger');

        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $id]);
        staff_back("Staff account for {$row['name']} has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . '.');

    // ── Delete staff account ──────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) staff_back('Invalid request.', 'danger');

        $st = $db->prepare("SELECT name, avatar FROM users WHERE id=? AND role='staff' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) staff_back('Staff account not found.', 'danger');

        // Prevent deleting self
        if ($id === (int)$_SESSION['user_id']) staff_back('You cannot delete your own account.', 'danger');

        // Remove avatar file
        if ($row['avatar'] && $row['avatar'] !== 'default.png') {
            $path = UPLOAD_PATH . 'avatars/' . $row['avatar'];
            if (file_exists($path)) @unlink($path);
        }

        $db->prepare("DELETE FROM users WHERE id=? AND role='staff'")->execute([$id]);
        staff_back("Staff account for {$row['name']} has been deleted.");

    default:
        staff_back('Unknown action.', 'danger');
}
