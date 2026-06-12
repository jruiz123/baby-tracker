<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            redirect('index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <meta name="theme-color" content="#e8735a"/>
  <title>Login — Baby Tracker</title>
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
    <h2 class="auth-title">Welcome back</h2>

    <?php if ($error): ?>
    <div class="alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="field-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
          placeholder="you@email.com" value="<?= e($_POST['email'] ?? '') ?>" required/>
      </div>

      <div class="field-group">
        <label class="form-label">Password</label>
        <div class="pass-wrap">
          <input type="password" name="password" class="form-control" id="passInput"
            placeholder="Your password" required/>
          <button type="button" class="pass-toggle" onclick="togglePass()">👁️</button>
        </div>
      </div>

      <button type="submit" class="btn-auth">Sign In</button>
    </form>

    <p class="auth-switch">Don't have an account? <a href="register.php">Register here</a></p>
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
