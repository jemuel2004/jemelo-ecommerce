<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(SITE_URL . '/customer/index.php');
}

$errors       = [];
$old          = [];
$show_success = false;
$success_name = '';
$success_avatar_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old    = $_POST;
    $result = register_user($_POST, $_FILES);
    if ($result['success']) {
        $success_name = $result['name'];
        $success_avatar_url = avatar_url($result['avatar']);
        $show_success = true;
        notify_admins(
            'new_customer',
            'New Customer Registered',
            $success_name . ' just created an account.',
            SITE_URL . '/admin/customers.php'
        );
    } else {
        $errors = $result['errors'] ?? [$result['message']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<style>
/* ── Register Page Layout ─────────────────────────────────── */
* { box-sizing: border-box; }
html, body { height: 100%; }
.reg-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0d1f42 0%, #1a3a6b 55%, #1e40af 100%);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 32px 16px;
}
.reg-wrap {
  display: grid;
  grid-template-columns: 380px 1fr;
  max-width: 960px;
  width: 100%;
  min-height: 640px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(0,0,0,.35);
}
/* Left branding panel */
.reg-left {
  background: linear-gradient(160deg, #1e3a8a 0%, #1d4ed8 50%, #2563eb 100%);
  padding: 48px 36px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.reg-left::before {
  content: '';
  position: absolute;
  width: 300px; height: 300px;
  background: rgba(255,255,255,.06);
  border-radius: 50%;
  top: -80px; right: -80px;
}
.reg-left::after {
  content: '';
  position: absolute;
  width: 200px; height: 200px;
  background: rgba(255,255,255,.04);
  border-radius: 50%;
  bottom: -50px; left: -50px;
}
.reg-logo {
  font-size: 1.6rem;
  font-weight: 800;
  margin-bottom: 32px;
  position: relative;
  z-index: 1;
}
.reg-logo span { color: #93c5fd; }
.reg-tagline {
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 12px;
  position: relative;
  z-index: 1;
}
.reg-sub {
  font-size: .9rem;
  color: rgba(255,255,255,.75);
  margin-bottom: 36px;
  position: relative;
  z-index: 1;
  line-height: 1.6;
}
.reg-perks { list-style: none; padding: 0; position: relative; z-index: 1; }
.reg-perks li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  font-size: .88rem;
  color: rgba(255,255,255,.88);
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.reg-perks li:last-child { border-bottom: none; }
.reg-perks .perk-icon {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: .78rem;
  flex-shrink: 0;
}
.reg-login-link {
  margin-top: 36px;
  font-size: .82rem;
  color: rgba(255,255,255,.65);
  position: relative;
  z-index: 1;
}
.reg-login-link a { color: #93c5fd; font-weight: 600; }

/* Right form panel */
.reg-right {
  background: #fff;
  padding: 40px 40px 40px;
  overflow-y: auto;
}
.reg-form-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 4px;
}
.reg-form-sub {
  font-size: .84rem;
  color: #64748b;
  margin-bottom: 28px;
}

/* Avatar upload */
.reg-avatar-wrap {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 24px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1.5px dashed #cbd5e1;
}
.reg-avatar-preview {
  width: 72px; height: 72px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e2e8f0;
  flex-shrink: 0;
  transition: border-color .2s;
}
.reg-avatar-info { flex: 1; min-width: 0; }
.reg-avatar-info strong { display: block; font-size: .88rem; color: #1e293b; margin-bottom: 2px; }
.reg-avatar-info span { font-size: .78rem; color: #94a3b8; }
.reg-avatar-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
  margin-top: 8px;
  white-space: nowrap;
}
.reg-avatar-btn:hover { background: #1d4ed8; }

/* Form fields */
.reg-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.reg-row-3 { grid-template-columns: 1fr 1fr 1fr; }
.reg-field { margin-bottom: 16px; }
.reg-label {
  display: block;
  font-size: .82rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 5px;
}
.reg-label .req { color: #ef4444; margin-left: 2px; }
.reg-input-wrap { position: relative; }
.reg-input-wrap .reg-icon {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: .82rem;
  pointer-events: none;
  transition: color .2s;
}
.reg-input-wrap textarea ~ .reg-icon { top: 14px; transform: none; }
.reg-input {
  width: 100%;
  padding: 10px 13px 10px 36px;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-family: inherit;
  font-size: .88rem;
  color: #0f172a;
  background: #fff;
  transition: border-color .2s, box-shadow .2s;
  outline: none;
}
.reg-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.reg-input.is-invalid { border-color: #ef4444; }
.reg-input.is-valid   { border-color: #10b981; }
.reg-input-wrap:focus-within .reg-icon { color: #2563eb; }
.reg-hint { font-size: .74rem; color: #94a3b8; margin-top: 4px; }
.reg-err  { font-size: .74rem; color: #ef4444; margin-top: 4px; display: none; }

/* Password field */
.reg-pw-toggle {
  position: absolute;
  right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 2px 4px;
  transition: color .2s;
}
.reg-pw-toggle:hover { color: #2563eb; }
.reg-input.has-toggle { padding-right: 42px; }

/* Password strength */
.pwd-strength-bar {
  height: 4px;
  border-radius: 4px;
  background: #e2e8f0;
  margin-top: 8px;
  overflow: hidden;
}
.pwd-strength-fill {
  height: 100%;
  border-radius: 4px;
  transition: width .35s ease, background .35s ease;
  width: 0;
}
.pwd-strength-text {
  font-size: .72rem;
  margin-top: 4px;
  font-weight: 600;
}

/* Terms */
.reg-terms {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 22px;
  padding: 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
}
.reg-terms input[type=checkbox] {
  width: 16px; height: 16px;
  accent-color: #2563eb;
  flex-shrink: 0;
  margin-top: 1px;
  cursor: pointer;
}
.reg-terms label {
  font-size: .82rem;
  color: #64748b;
  cursor: pointer;
  line-height: 1.5;
}
.reg-terms a { color: #2563eb; font-weight: 600; }

/* Submit button */
.reg-submit {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: inherit;
  font-size: .95rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  position: relative;
  overflow: hidden;
}
.reg-submit:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,.35); }
.reg-submit:active { transform: translateY(0); }

/* Error banner */
.reg-errors {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 20px;
}
.reg-errors ul { margin: 6px 0 0 16px; }
.reg-errors li { font-size: .82rem; color: #dc2626; margin-bottom: 3px; }

/* ── Mobile responsive ───────────────────────────────────── */
@media (max-width: 720px) {
  .reg-wrap { grid-template-columns: 1fr; min-height: auto; }
  .reg-left { display: none; }
  .reg-right { padding: 32px 22px; }
  .reg-row, .reg-row-3 { grid-template-columns: 1fr; gap: 0; }
}
@media (max-width: 400px) {
  .reg-page { padding: 16px 12px; }
  .reg-right { padding: 24px 16px; }
}
</style>
</head>
<body>
<div class="reg-page">
  <div class="reg-wrap">

    <!-- Left branding panel -->
    <div class="reg-left">
      <div class="reg-logo"><i class="fas fa-shopping-bag"></i> Jeme<span>lo</span></div>
      <div class="reg-tagline">Join millions of happy shoppers!</div>
      <p class="reg-sub">Create your free account and start shopping the best deals online.</p>
      <ul class="reg-perks">
        <li>
          <div class="perk-icon"><i class="fas fa-shield-alt"></i></div>
          Secure &amp; encrypted transactions
        </li>
        <li>
          <div class="perk-icon"><i class="fas fa-truck"></i></div>
          Free shipping on orders ₱500+
        </li>
        <li>
          <div class="perk-icon"><i class="fas fa-undo-alt"></i></div>
          Easy 30-day returns
        </li>
        <li>
          <div class="perk-icon"><i class="fas fa-bolt"></i></div>
          Exclusive member-only deals
        </li>
        <li>
          <div class="perk-icon"><i class="fas fa-headset"></i></div>
          24/7 customer support chat
        </li>
      </ul>
      <div class="reg-login-link">
        Already have an account? <a href="<?= SITE_URL ?>/customer/index.php?modal=login">Sign in here</a>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="reg-right">
      <h1 class="reg-form-title">Create your account</h1>
      <p class="reg-form-sub">Fill in the details below — it only takes a minute.</p>

      <?php if ($errors): ?>
      <div class="reg-errors">
        <strong style="color:#dc2626;font-size:.85rem"><i class="fas fa-exclamation-circle"></i> Please fix the following:</strong>
        <ul>
          <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" action="" enctype="multipart/form-data" id="reg-form" novalidate>
        <?= csrf_field() ?>

        <!-- Profile picture -->
        <div class="reg-avatar-wrap">
          <img src="<?= SITE_URL ?>/assets/images/default-avatar.svg" alt="Avatar preview"
               id="avatar-preview" class="reg-avatar-preview">
          <div class="reg-avatar-info">
            <strong>Profile Picture</strong>
            <span>JPEG, PNG or WebP — max 3 MB</span>
            <br>
            <button type="button" class="reg-avatar-btn" onclick="document.getElementById('avatar-input').click()">
              <i class="fas fa-camera"></i> Choose Photo
            </button>
          </div>
          <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>

        <!-- Name row — 3 columns -->
        <div class="reg-row reg-row-3">
          <div class="reg-field">
            <label class="reg-label" for="first_name">First Name <span class="req">*</span></label>
            <div class="reg-input-wrap">
              <i class="fas fa-user reg-icon"></i>
              <input type="text" name="first_name" id="first_name" class="reg-input"
                     placeholder="Juan" value="<?= e($old['first_name'] ?? '') ?>"
                     autocomplete="given-name" required>
            </div>
            <span class="reg-err" id="err-first_name"></span>
          </div>
          <div class="reg-field">
            <label class="reg-label" for="middle_name">
              Middle Name
              <span style="font-weight:400;color:#94a3b8;font-size:.72rem">(optional)</span>
            </label>
            <div class="reg-input-wrap">
              <i class="fas fa-user reg-icon"></i>
              <input type="text" name="middle_name" id="middle_name" class="reg-input"
                     placeholder="Santos" value="<?= e($old['middle_name'] ?? '') ?>"
                     autocomplete="additional-name">
            </div>
          </div>
          <div class="reg-field">
            <label class="reg-label" for="last_name">Last Name <span class="req">*</span></label>
            <div class="reg-input-wrap">
              <i class="fas fa-user reg-icon"></i>
              <input type="text" name="last_name" id="last_name" class="reg-input"
                     placeholder="dela Cruz" value="<?= e($old['last_name'] ?? '') ?>"
                     autocomplete="family-name" required>
            </div>
            <span class="reg-err" id="err-last_name"></span>
          </div>
        </div>

        <!-- Username -->
        <div class="reg-field">
          <label class="reg-label" for="username">Username <span class="req">*</span></label>
          <div class="reg-input-wrap">
            <i class="fas fa-at reg-icon"></i>
            <input type="text" name="username" id="username" class="reg-input"
                   placeholder="juandelacruz123" value="<?= e($old['username'] ?? '') ?>"
                   autocomplete="username" pattern="[a-zA-Z0-9_.]+" required>
          </div>
          <span class="reg-hint">Letters, numbers, dots and underscores only.</span>
          <span class="reg-err" id="err-username"></span>
        </div>

        <!-- Email -->
        <div class="reg-field">
          <label class="reg-label" for="reg-email">Email Address <span class="req">*</span></label>
          <div class="reg-input-wrap">
            <i class="fas fa-envelope reg-icon"></i>
            <input type="email" name="email" id="reg-email" class="reg-input"
                   placeholder="juan@example.com" value="<?= e($old['email'] ?? '') ?>"
                   autocomplete="email" required>
          </div>
          <span class="reg-err" id="err-email"></span>
        </div>

        <!-- Birthdate & Mobile -->
        <div class="reg-row">
          <div class="reg-field">
            <label class="reg-label" for="birthdate">Birthdate <span class="req">*</span></label>
            <div class="reg-input-wrap">
              <i class="fas fa-calendar-alt reg-icon"></i>
              <input type="date" name="birthdate" id="birthdate" class="reg-input"
                     value="<?= e($old['birthdate'] ?? '') ?>"
                     max="<?= date('Y-m-d', strtotime('-13 years')) ?>" required>
            </div>
            <span class="reg-err" id="err-birthdate"></span>
          </div>
          <div class="reg-field">
            <label class="reg-label" for="phone">Mobile Number <span class="req">*</span></label>
            <div class="reg-input-wrap">
              <i class="fas fa-phone reg-icon"></i>
              <input type="tel" name="phone" id="phone" class="reg-input"
                     placeholder="09XX XXX XXXX" value="<?= e($old['phone'] ?? '') ?>"
                     autocomplete="tel" required>
            </div>
            <span class="reg-err" id="err-phone"></span>
          </div>
        </div>

        <!-- Address -->
        <div class="reg-field">
          <label class="reg-label" for="address">Complete Address <span class="req">*</span></label>
          <div class="reg-input-wrap" style="position:relative">
            <i class="fas fa-map-marker-alt reg-icon" style="top:14px;transform:none"></i>
            <textarea name="address" id="address" class="reg-input" rows="2"
                      placeholder="Street, Barangay, City, Province, ZIP"
                      autocomplete="street-address" required
                      style="padding-left:36px;resize:vertical"><?= e($old['address'] ?? '') ?></textarea>
          </div>
          <span class="reg-err" id="err-address"></span>
        </div>

        <!-- Password -->
        <div class="reg-field">
          <label class="reg-label" for="reg-password">Password <span class="req">*</span></label>
          <div class="reg-input-wrap">
            <i class="fas fa-lock reg-icon"></i>
            <input type="password" name="password" id="reg-password" class="reg-input has-toggle"
                   placeholder="At least 8 characters" autocomplete="new-password" required>
            <button type="button" class="reg-pw-toggle" data-target="reg-password" aria-label="Toggle password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <!-- Strength meter -->
          <div class="pwd-strength-bar"><div class="pwd-strength-fill" id="pwd-fill"></div></div>
          <div class="pwd-strength-text" id="pwd-text" style="color:#94a3b8">Enter a password</div>
          <span class="reg-err" id="err-password"></span>
        </div>

        <!-- Confirm password -->
        <div class="reg-field">
          <label class="reg-label" for="reg-confirm">Confirm Password <span class="req">*</span></label>
          <div class="reg-input-wrap">
            <i class="fas fa-lock reg-icon"></i>
            <input type="password" name="confirm" id="reg-confirm" class="reg-input has-toggle"
                   placeholder="Repeat your password" autocomplete="new-password" required>
            <button type="button" class="reg-pw-toggle" data-target="reg-confirm" aria-label="Toggle password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <span class="reg-err" id="err-confirm"></span>
        </div>

        <!-- Terms -->
        <div class="reg-terms">
          <input type="checkbox" name="agree" id="agree" required>
          <label for="agree">
            I agree to Jemelo's <a href="#" target="_blank">Terms of Service</a> and
            <a href="#" target="_blank">Privacy Policy</a>. I confirm I am at least 13 years old.
          </label>
        </div>

        <button type="submit" class="reg-submit" id="reg-submit-btn">
          <i class="fas fa-user-plus"></i> Create My Account
        </button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:.82rem;color:#94a3b8">
        Already have an account? <a href="<?= SITE_URL ?>/customer/index.php?modal=login" style="color:#2563eb;font-weight:600">Sign in</a>
      </div>
    </div>

  </div>
</div>

<?php if ($show_success): ?>
<style>
/* ── Registration Success Overlay ───────────────────────────── */
.reg-success-overlay {
  position: fixed; inset: 0;
  background: linear-gradient(135deg, #0d1f42 0%, #1a3a6b 55%, #1e40af 100%);
  z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  animation: rso-in .5s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes rso-in { from { opacity:0; } to { opacity:1; } }
.reg-success-card {
  text-align: center;
  padding: 48px 40px 40px;
  max-width: 440px;
  width: 100%;
  animation: rsc-in .55s .1s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes rsc-in { from { opacity:0; transform:translateY(40px) scale(.92); } to { opacity:1; transform:none; } }
/* Checkmark ring */
.reg-success-ring {
  width: 100px; height: 100px;
  margin: 0 auto 20px;
  position: relative;
}
.reg-success-ring svg {
  position: absolute; inset: 0; width: 100%; height: 100%;
}
.reg-success-ring .ring-track { stroke: rgba(255,255,255,.18); }
.reg-success-ring .ring-fill  {
  stroke: #4ade80;
  stroke-dasharray: 283;
  stroke-dashoffset: 283;
  animation: ring-draw .9s .25s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes ring-draw { to { stroke-dashoffset: 0; } }
/* Avatar inside ring */
.reg-success-avatar {
  position: absolute;
  inset: 12px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #4ade80;
  animation: avatar-pop .5s .8s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes avatar-pop { from { transform:scale(.6); opacity:0; } to { transform:scale(1); opacity:1; } }
/* Checkmark tick (shown when no avatar) */
.reg-success-check {
  position: absolute;
  inset: 12px;
  border-radius: 50%;
  background: rgba(74,222,128,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 2.4rem;
  color: #4ade80;
  animation: avatar-pop .5s .8s cubic-bezier(.34,1.56,.64,1) both;
}
/* Text */
.reg-success-title {
  font-size: 1.6rem; font-weight: 800;
  color: #fff; margin-bottom: 6px;
}
.reg-success-sub {
  font-size: .9rem; color: rgba(255,255,255,.7);
  margin-bottom: 4px;
}
.reg-success-name {
  font-size: 1.05rem; font-weight: 700;
  color: #93c5fd; margin-bottom: 28px;
}
/* Progress bar */
.reg-success-bar-wrap {
  background: rgba(255,255,255,.15);
  border-radius: 99px; height: 5px;
  margin-bottom: 14px; overflow: hidden;
}
.reg-success-bar {
  height: 100%;
  background: #4ade80;
  border-radius: 99px;
  width: 100%;
  animation: bar-drain 4s .6s linear forwards;
}
@keyframes bar-drain { to { width: 0%; } }
.reg-success-timer {
  font-size: .8rem; color: rgba(255,255,255,.55);
  margin-bottom: 20px;
}
/* Buttons */
.reg-success-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 28px;
  background: #fff; color: #1e3a8a;
  border: none; border-radius: 99px;
  font-family: inherit; font-size: .9rem; font-weight: 700;
  cursor: pointer; text-decoration: none;
  transition: all .2s;
  box-shadow: 0 4px 14px rgba(0,0,0,.25);
}
.reg-success-btn:hover { background: #eff6ff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.3); }
</style>

<div class="reg-success-overlay" id="reg-success-overlay">
  <div class="reg-success-card">
    <div class="reg-success-ring">
      <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="45" stroke-width="5" class="ring-track"/>
        <circle cx="50" cy="50" r="45" stroke-width="5" class="ring-fill"
                stroke-linecap="round" transform="rotate(-90 50 50)"/>
      </svg>
      <?php if ($success_avatar_url && strpos($success_avatar_url, 'default-avatar') === false): ?>
      <img src="<?= e($success_avatar_url) ?>" alt="Profile" class="reg-success-avatar">
      <?php else: ?>
      <div class="reg-success-check"><i class="fas fa-check"></i></div>
      <?php endif; ?>
    </div>

    <div class="reg-success-title">Account Created!</div>
    <div class="reg-success-sub">Welcome to Jemelo,</div>
    <div class="reg-success-name"><?= e($success_name) ?></div>

    <div class="reg-success-bar-wrap">
      <div class="reg-success-bar" id="reg-success-bar"></div>
    </div>
    <div class="reg-success-timer" id="reg-success-timer">Redirecting to sign in in <strong>4</strong>s…</div>

    <a href="<?= SITE_URL ?>/customer/index.php?modal=login&registered=1" class="reg-success-btn" id="reg-signin-btn">
      <i class="fas fa-sign-in-alt"></i> Sign In Now
    </a>
  </div>
</div>

<script>
(function () {
  var secs   = 4;
  var timer  = document.getElementById('reg-success-timer');
  var strong = timer.querySelector('strong');
  var dest   = '<?= SITE_URL ?>/customer/index.php?modal=login&registered=1';

  var iv = setInterval(function () {
    secs--;
    if (strong) strong.textContent = secs;
    if (secs <= 0) {
      clearInterval(iv);
      window.location.href = dest;
    }
  }, 1000);

  // Clicking "Sign In Now" also clears the timer
  document.getElementById('reg-signin-btn').addEventListener('click', function () {
    clearInterval(iv);
  });
})();
</script>
<?php endif; ?>

<script src="<?= SITE_URL ?>/assets/js/main.js" defer></script>
<script>
// ── Avatar preview ───────────────────────────────────────────
document.getElementById('avatar-input').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  if (file.size > 3 * 1024 * 1024) {
    alert('Image must be under 3 MB.'); this.value = ''; return;
  }
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('avatar-preview');
    img.style.opacity = '0';
    img.style.transition = '.2s';
    setTimeout(function() { img.src = e.target.result; img.style.opacity = '1'; }, 150);
  };
  reader.readAsDataURL(file);
});

// ── Password visibility toggle ───────────────────────────────
document.querySelectorAll('.reg-pw-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
    const inp  = document.getElementById(this.dataset.target);
    const icon = this.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else                         { inp.type = 'password'; icon.className = 'fas fa-eye'; }
  });
});

// ── Password strength meter ──────────────────────────────────
const pwdInput = document.getElementById('reg-password');
const pwdFill  = document.getElementById('pwd-fill');
const pwdText  = document.getElementById('pwd-text');
const strength = [
  { label: 'Very Weak', color: '#ef4444', w: '20%' },
  { label: 'Weak',      color: '#f97316', w: '40%' },
  { label: 'Fair',      color: '#eab308', w: '60%' },
  { label: 'Good',      color: '#22c55e', w: '80%' },
  { label: 'Strong',    color: '#10b981', w: '100%' },
];
pwdInput.addEventListener('input', function() {
  const v = this.value;
  if (!v) { pwdFill.style.width = '0'; pwdText.textContent = 'Enter a password'; pwdText.style.color = '#94a3b8'; return; }
  let score = 0;
  if (v.length >= 8)                      score++;
  if (v.length >= 12)                     score++;
  if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
  if (/[0-9]/.test(v))                    score++;
  if (/[^A-Za-z0-9]/.test(v))            score++;
  score = Math.min(score - 1, 4);
  if (score < 0) score = 0;
  const s = strength[score];
  pwdFill.style.width = s.w;
  pwdFill.style.background = s.color;
  pwdText.textContent = s.label;
  pwdText.style.color = s.color;
});

// ── Client-side validation (real-time) ──────────────────────
function showErr(id, msg) {
  const el = document.getElementById('err-' + id);
  const inp = document.getElementById(id) || document.querySelector('[name=' + id + ']');
  if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
  if (inp) { inp.classList.toggle('is-invalid', !!msg); inp.classList.toggle('is-valid', !msg && !!inp.value); }
}

document.getElementById('reg-form').addEventListener('submit', function(e) {
  let valid = true;
  const val = function(id) { const el = document.getElementById(id) || document.querySelector('[name="' + id + '"]'); return el ? el.value.trim() : ''; };

  if (!val('first_name'))  { showErr('first_name', 'First name is required.'); valid = false; } else showErr('first_name', '');
  if (!val('last_name'))   { showErr('last_name',  'Last name is required.');  valid = false; } else showErr('last_name', '');

  const uname = val('username');
  if (!uname)              { showErr('username', 'Username is required.'); valid = false; }
  else if (!/^[a-zA-Z0-9_.]+$/.test(uname)) { showErr('username', 'Only letters, numbers, dots and underscores.'); valid = false; }
  else showErr('username', '');

  const email = val('reg-email');
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showErr('email', 'Enter a valid email address.'); valid = false; } else showErr('email', '');

  if (!val('birthdate'))   { showErr('birthdate', 'Birthdate is required.'); valid = false; } else showErr('birthdate', '');
  if (!val('phone'))       { showErr('phone',     'Mobile number is required.'); valid = false; } else showErr('phone', '');
  if (!val('address'))     { showErr('address',   'Address is required.'); valid = false; } else showErr('address', '');

  const pwd = document.getElementById('reg-password').value;
  if (pwd.length < 8)     { showErr('password', 'Password must be at least 8 characters.'); valid = false; } else showErr('password', '');

  const conf = document.getElementById('reg-confirm').value;
  if (pwd !== conf)        { showErr('confirm', 'Passwords do not match.'); valid = false; } else showErr('confirm', '');

  if (!document.getElementById('agree').checked) {
    alert('Please agree to the Terms of Service and Privacy Policy.'); valid = false;
  }

  if (!valid) { e.preventDefault(); this.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  else { document.getElementById('reg-submit-btn').disabled = true; document.getElementById('reg-submit-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…'; }
});

// Real-time per-field validation
['first_name','last_name'].forEach(function(id) {
  const el = document.getElementById(id);
  if (el) el.addEventListener('blur', function() { showErr(id, this.value.trim() ? '' : (id.replace('_',' ').replace(/^\w/,s=>s.toUpperCase())) + ' is required.'); });
});
</script>
</body>
</html>
