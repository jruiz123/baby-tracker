<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db      = getDB();
$user    = currentUser();
$user_id = $user['id'];

$stmt = $db->prepare("SELECT * FROM babies WHERE user_id=? ORDER BY name ASC");
$stmt->execute([$user_id]);
$babies = $stmt->fetchAll();
if (empty($babies)) redirect('babies.php');
if (!currentBabyId()) setActiveBaby($babies[0]['id']);
$activeBabyId = currentBabyId();
$activeBaby   = null;
foreach ($babies as $b) { if ($b['id'] == $activeBabyId) { $activeBaby = $b; break; } }

// Filters
$filterCat  = $_GET['cat']  ?? 'all';
$filterDate = $_GET['date'] ?? '';

$where  = "l.baby_id=? AND l.user_id=?";
$params = [$activeBabyId, $user_id];

if ($filterCat !== 'all') {
    $where   .= " AND l.category=?";
    $params[] = $filterCat;
}
if ($filterDate) {
    $where   .= " AND DATE(l.logged_at)=?";
    $params[] = $filterDate;
}

$stmt = $db->prepare("
    SELECT l.*,
      lt.temperature, lt.unit as temp_unit, lt.symptoms,
      ld.type as diaper_type,
      lf.type as feed_type, lf.amount, lf.unit as feed_unit, lf.food,
      ls.duration_minutes, ls.quality,
      lm.medicine_name, lm.dose, lm.unit as med_unit
    FROM logs l
    LEFT JOIN log_temperature lt ON lt.log_id=l.id
    LEFT JOIN log_diaper ld ON ld.log_id=l.id
    LEFT JOIN log_feeding lf ON lf.log_id=l.id
    LEFT JOIN log_sleep ls ON ls.log_id=l.id
    LEFT JOIN log_medicine lm ON lm.log_id=l.id
    WHERE $where
    ORDER BY l.logged_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

function catIcon($c) { return ['temperature'=>'🌡️','diaper'=>'💩','feeding'=>'🍼','sleep'=>'😴','play'=>'🎮','medicine'=>'💊','note'=>'📝'][$c]??'📋'; }
function logSummary($log) {
    switch ($log['category']) {
        case 'temperature': return $log['temperature'] ? $log['temperature'].'°'.$log['temp_unit'] : '';
        case 'diaper':      return ucfirst($log['diaper_type']??'');
        case 'feeding':     return ucfirst($log['feed_type']??'').($log['amount']?' · '.$log['amount'].' '.($log['feed_unit']??''):'').($log['food']?' · '.$log['food']:'');
        case 'sleep':       return $log['duration_minutes'] ? floor($log['duration_minutes']/60).'h '.($log['duration_minutes']%60).'m · '.ucfirst($log['quality']??'') : '';
        case 'medicine':    return ($log['medicine_name']??'').($log['dose']?' '.$log['dose'].' '.($log['med_unit']??''):'');
        default: return '';
    }
}

$pageTitle = 'History';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <a href="index.php" class="btn-back">←</a>
    <div>
      <h1>📋 History</h1>
      <p><?= e($activeBaby['name'] ?? '') ?></p>
    </div>
  </div>
</div>

<div class="main-wrap">

  <!-- Category Filter -->
  <div class="filter-bar">
    <?php
    $cats = ['all'=>'All','temperature'=>'🌡️ Temp','diaper'=>'💩 Diaper','feeding'=>'🍼 Feeding','sleep'=>'😴 Sleep','play'=>'🎮 Play','medicine'=>'💊 Medicine','note'=>'📝 Note'];
    foreach ($cats as $val => $label):
      $active = $filterCat === $val;
      $url    = '?cat=' . $val . ($filterDate ? '&date='.$filterDate : '');
    ?>
    <a href="<?= $url ?>" class="filter-pill <?= $active?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Date Filter -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="d-flex align-items-center gap-2">
        <input type="date" class="form-control" id="dateFilter" value="<?= e($filterDate) ?>"
          style="font-size:0.88rem;padding:8px 12px"/>
        <button class="btn btn-primary" style="width:auto;padding:8px 16px;font-size:0.88rem"
          onclick="applyDate()">Filter</button>
        <?php if ($filterDate): ?>
        <a href="?cat=<?= e($filterCat) ?>" class="btn btn-outline-secondary"
          style="border-radius:var(--radius-sm);font-weight:700;padding:8px 14px;font-size:0.88rem;white-space:nowrap">Clear</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Count -->
  <p class="text-muted fw-700 mb-3" style="font-size:0.82rem"><?= count($logs) ?> entries found</p>

  <!-- Logs -->
  <?php if (empty($logs)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <p>No entries found.<br>Try a different filter.</p>
  </div>
  <?php else: ?>
  <?php
  $lastDate = '';
  foreach ($logs as $log):
    $logDate = date('Y-m-d', strtotime($log['logged_at']));
    $summary = logSummary($log);
    $time    = date('h:i A', strtotime($log['logged_at']));
  ?>
  <?php if ($logDate !== $lastDate): $lastDate = $logDate; ?>
  <div class="d-flex align-items-center gap-2 mb-2 mt-3">
    <div style="height:1px;flex:1;background:var(--border)"></div>
    <span style="font-size:0.75rem;font-weight:800;color:var(--muted);white-space:nowrap">
      <?= date('D, M d Y', strtotime($log['logged_at'])) ?>
    </span>
    <div style="height:1px;flex:1;background:var(--border)"></div>
  </div>
  <?php endif; ?>

  <div class="log-item <?= e($log['category']) ?>">
    <div class="d-flex justify-content-between align-items-start">
      <span class="cat-chip cat-<?= e($log['category']) ?>"><?= catIcon($log['category']) ?> <?= ucfirst($log['category']) ?></span>
      <div class="d-flex gap-1">
        <a href="log.php?edit=<?= $log['id'] ?>" class="btn-icon">✏️</a>
        <a href="log.php?delete=<?= $log['id'] ?>" class="btn-icon btn-del"
          onclick="return confirm('Delete this entry?')">🗑️</a>
      </div>
    </div>
    <?php if ($summary): ?>
    <div class="log-detail mt-2"><?= e($summary) ?></div>
    <?php endif; ?>
    <div class="log-time">🕐 <?= $time ?></div>
    <?php if ($log['notes']): ?>
    <div class="log-notes">📝 <?= e($log['notes']) ?></div>
    <?php endif; ?>
    <?php if ($log['alarm_enabled']): ?>
    <div class="mt-2">
      <span class="countdown-chip" style="<?= $log['alarm_triggered']?'background:#f0f0f0;border-color:#ddd;color:#999':'' ?>">
        <?= $log['alarm_triggered'] ? '✅ Alarm done' : '⏰ Alarm set' ?>
      </span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function applyDate() {
  const d   = document.getElementById('dateFilter').value;
  const cat = new URLSearchParams(location.search).get('cat') || 'all';
  location.href = '?cat=' + cat + (d ? '&date=' + d : '');
}
</script>
</body>
</html>
