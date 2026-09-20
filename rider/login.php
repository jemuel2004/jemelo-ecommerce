<?php
require_once __DIR__ . '/../includes/auth.php';

// Already logged in as rider → go to dashboard
if (!empty($_SESSION['user_id']) && $_SESSION['user_role'] === 'rider') {
    redirect(SITE_URL . '/rider/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        if ($_SESSION['user_role'] !== 'rider') {
            // Correct logout and reject non-rider
            session_destroy();
            $error = 'This portal is for riders only. Please use the correct login.';
        } else {
            redirect(SITE_URL . '/rider/index.php');
        }
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rider Login — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rider.css">
</head>
<body class="rider-login-body">

<div class="rider-login-card">
  <div class="rider-login-logo">
    <div class="logo-icon"><i class="fas fa-motorcycle"></i></div>
    <h2>Rider Portal</h2>
    <p>Sign in to your Jemelo delivery account</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Email or Username</label>
      <div style="position:relative">
        <i class="fas fa-user" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.8rem"></i>
        <input type="text" name="email" class="form-control" style="padding-left:36px"
               placeholder="Enter your email or username" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="username">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <div style="position:relative">
        <i class="fas fa-lock" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.8rem"></i>
        <input type="password" name="password" class="form-control" style="padding-left:36px"
               placeholder="••••••••" required autocomplete="current-password" id="rider-pw">
        <button type="button" onclick="const i=document.getElementById('rider-pw');i.type=i.type==='password'?'text':'password'"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px">
      <i class="fas fa-sign-in-alt"></i> Sign In
    </button>
  </form>

  <div style="text-align:center;margin-top:20px;font-size:.8rem;color:var(--text-muted)">
    <i class="fas fa-shield-alt" style="color:var(--rider-primary)"></i>
    Secure rider portal — not a customer account
  </div>
  <div style="text-align:center;margin-top:10px">
    <a href="<?= SITE_URL ?>/customer/index.php" style="font-size:.78rem;color:var(--rider-primary)">
      &larr; Go to Customer Shop
    </a>
  </div>
</div>

</body>
</html>
