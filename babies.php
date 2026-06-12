<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db      = getDB();
$user    = currentUser();
$user_id = $user['id'];
$error   = '';
$success = '';

// Handle switch active baby
if (isset($_GET['switch'])) {
    $bid = intval($_GET['switch']);
    $s   = $db->prepare("SELECT id FROM babies WHERE id=? AND user_id=?");
    $s->execute([$bid, $user_id]);
    if ($s->fetch()) { setActiveBaby($bid); }
    redirect('index.php');
}

// Handle delete baby
if (isset($_GET['delete'])) {
    $bid = intval($_GET['delete']);
    $s   = $db->prepare("DELETE FROM babies WHERE id=? AND user_id=?");
    $s->execute([$bid, $user_id]);
    if (currentBabyId() == $bid) $_SESSION['active_baby_id'] = null;
    redirect('babies.php');
}

// Handle add/edit baby
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender    = $_POST['gender'] ?? 'other';
    $edit_id   = intval($_POST['edit_id'] ?? 0);

    if (!$name) {
        $error = 'Baby name is required.';
    } else {
        if ($edit_id) {
            $s = $db->prepare("UPDATE babies SET name=?, birthdate=?, gender=? WHERE id=? AND user_id=?");
            $s->execute([$name, $birthdate ?: null, $gender, $edit_id, $user_id]);
            $success = 'Baby profile updated!';
        } else {
            $s = $db->prepare("INSERT INTO babies (user_id, name, birthdate, gender) VALUES (?,?,?,?)");
            $s->execute([$user_id, $name, $birthdate ?: null, $gender]);
            $newId = $db->lastInsertId();
            setActiveBaby($newId);
            $success = 'Baby added successfully!';
        }
    }
}

$stmt = $db->prepare("SELECT * FROM babies WHERE user_id=? ORDER BY name ASC");
$stmt->execute([$user_id]);
$babies = $stmt->fetchAll();

function ageString($birthdate) {
    if (!$birthdate) return '';
    $birth = new DateTime($birthdate);
    $now   = new DateTime();
    $diff  = $now->diff($birth);
    if ($diff->y > 0) return $diff->y . 'y ' . $diff->m . 'm old';
    if ($diff->m > 0) return $diff->m . ' months old';
    return $diff->d . ' days old';
}

$pageTitle = 'My Babies';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <a href="index.php" class="btn-back">←</a>
    <div>
      <h1>👶 My Babies</h1>
      <p>Manage baby profiles</p>
    </div>
  </div>
</div>

<div class="main-wrap">

  <?php if ($error): ?>
  <div class="alert-error mb-3"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert-success mb-3"><?= e($success) ?></div>
  <?php endif; ?>

  <!-- Baby List -->
  <?php foreach ($babies as $baby): ?>
  <div class="baby-card <?= currentBabyId() == $baby['id'] ? 'active' : '' ?>">
    <div class="baby-avatar"><?= $baby['gender'] === 'male' ? '👦' : ($baby['gender'] === 'female' ? '👧' : '🧒') ?></div>
    <div class="flex-grow-1">
      <div class="baby-name"><?= e($baby['name']) ?></div>
      <div class="baby-meta">
        <?= $baby['birthdate'] ? ageString($baby['birthdate']) . ' · ' . date('M d, Y', strtotime($baby['birthdate'])) : 'No birthdate set' ?>
      </div>
      <?php if (currentBabyId() == $baby['id']): ?>
      <span class="active-badge">✅ Active</span>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-column gap-1">
      <?php if (currentBabyId() != $baby['id']): ?>
      <a href="?switch=<?= $baby['id'] ?>" class="btn-icon btn-switch" title="Set Active">🔄</a>
      <?php endif; ?>
      <button class="btn-icon btn-edit" onclick="editBaby(<?= htmlspecialchars(json_encode($baby)) ?>)" title="Edit">✏️</button>
      <a href="?delete=<?= $baby['id'] ?>" class="btn-icon btn-del"
        onclick="return confirm('Delete <?= e($baby['name']) ?>? All logs will be deleted too.')" title="Delete">🗑️</a>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($babies)): ?>
  <div class="empty-state">
    <div class="empty-icon">👶</div>
    <p>No babies added yet.<br>Add your first baby below!</p>
  </div>
  <?php endif; ?>

  <!-- Add / Edit Form -->
  <div class="card mt-3">
    <div class="card-body">
      <p class="section-title" id="formTitle">➕ Add Baby</p>
      <form method="POST" novalidate>
        <input type="hidden" name="edit_id" id="editId" value="0"/>

        <div class="mb-3">
          <label class="form-label">Baby's Name</label>
          <input type="text" name="name" class="form-control" id="babyName" placeholder="e.g. Sofia" required/>
        </div>

        <div class="mb-3">
          <label class="form-label">Birthdate (optional)</label>
          <input type="date" name="birthdate" class="form-control" id="babyBirthdate"/>
        </div>

        <div class="mb-3">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select" id="babyGender">
            <option value="female">👧 Girl</option>
            <option value="male">👦 Boy</option>
            <option value="other">🧒 Other</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary w-100" id="submitBtn">Save Baby</button>
        <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="cancelBtn"
          onclick="cancelEdit()" style="display:none;border-radius:var(--radius-sm);font-weight:700">Cancel</button>
      </form>
    </div>
  </div>

</div>

<!-- Bottom nav -->
<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editBaby(baby) {
  document.getElementById('editId').value       = baby.id;
  document.getElementById('babyName').value     = baby.name;
  document.getElementById('babyBirthdate').value= baby.birthdate || '';
  document.getElementById('babyGender').value   = baby.gender || 'other';
  document.getElementById('formTitle').textContent = '✏️ Edit Baby';
  document.getElementById('submitBtn').textContent = 'Update Baby';
  document.getElementById('cancelBtn').style.display = 'block';
  document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
}
function cancelEdit() {
  document.getElementById('editId').value       = 0;
  document.getElementById('babyName').value     = '';
  document.getElementById('babyBirthdate').value= '';
  document.getElementById('babyGender').value   = 'female';
  document.getElementById('formTitle').textContent = '➕ Add Baby';
  document.getElementById('submitBtn').textContent = 'Save Baby';
  document.getElementById('cancelBtn').style.display = 'none';
}
</script>
</body>
</html>
