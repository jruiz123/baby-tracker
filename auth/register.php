<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) redirect('index.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);
            $success = 'Account created! You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <meta name="theme-color" content="#e8735a"/>
  <title>Register — Baby Tracker</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="/baby-tracker/assets/css/app.css"/>
  <link rel="stylesheet" href="/baby-tracker/assets/css/auth.css"/>
</head>
<body class="auth-body">

<div class="auth-wrap">

  <div class="auth-logo">
    <div class="auth-logo-icon">🍼</div>
    <h1>Baby Tracker</h1>
    <p>Track every moment of your little one</p>
  </div>

  <div class="auth-card">
    <h2 class="auth-title">Create account</h2>

    <?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert-success"><?= e($success) ?> <a href="login.php">Sign in →</a></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="field-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control"
          placeholder="e.g. Juan dela Cruz" value="<?= e($_POST['name'] ?? '') ?>" required/>
      </div>

      <div class="field-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
          placeholder="you@email.com" value="<?= e($_POST['email'] ?? '') ?>" required/>
      </div>

      <div class="field-group">
        <label class="form-label">Password</label>
        <div class="pass-wrap">
          <input type="password" name="password" class="form-control" id="passInput"
            placeholder="Min 6 characters" required/>
          <button type="button" class="pass-toggle" onclick="togglePass()">👁️</button>
        </div>
      </div>

      <div class="field-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm" class="form-control"
          placeholder="Repeat password" required/>
      </div>

      <button type="submit" class="btn-auth">Create Account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Sign in here</a></p>
  </div>

</div>

<script>
function togglePass() {
  const i = document.getElementById('passInput');
  i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
