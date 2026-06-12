<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db      = getDB();
$user    = currentUser();
$user_id = $user['id'];

// Get babies
$stmt = $db->prepare("SELECT * FROM babies WHERE user_id=? ORDER BY name ASC");
$stmt->execute([$user_id]);
$babies = $stmt->fetchAll();

if (empty($babies)) {
    redirect('babies.php');
}

// Set active baby
if (!currentBabyId()) setActiveBaby($babies[0]['id']);
$activeBabyId = currentBabyId();
$activeBaby   = null;
foreach ($babies as $b) { if ($b['id'] == $activeBabyId) { $activeBaby = $b; break; } }

// Handle baby switch from dropdown
if (isset($_GET['switch'])) {
    setActiveBaby(intval($_GET['switch']));
    redirect('index.php');
}

// Get today's logs
$today = date('Y-m-d');
$stmt  = $db->prepare("
    SELECT l.*, 
      lt.temperature, lt.unit, lt.symptoms,
      ld.type as diaper_type,
      lf.type as feed_type, lf.amount, lf.unit as feed_unit, lf.food,
      ls.duration_minutes, ls.quality,
      lm.medicine_name, lm.dose, lm.unit as med_unit
    FROM logs l
    LEFT JOIN log_temperature lt ON lt.log_id = l.id
    LEFT JOIN log_diaper ld ON ld.log_id = l.id
    LEFT JOIN log_feeding lf ON lf.log_id = l.id
    LEFT JOIN log_sleep ls ON ls.log_id = l.id
    LEFT JOIN log_medicine lm ON lm.log_id = l.id
    WHERE l.baby_id=? AND DATE(l.logged_at)=?
    ORDER BY l.logged_at DESC
");
$stmt->execute([$activeBabyId, $today]);
$todayLogs = $stmt->fetchAll();

// Stats last 7 days
$stmt = $db->prepare("SELECT category, COUNT(*) as cnt FROM logs WHERE baby_id=? AND logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY category");
$stmt->execute([$activeBabyId]);
$stats = [];
foreach ($stmt->fetchAll() as $row) $stats[$row['category']] = $row['cnt'];

// Active alarms
$stmt = $db->prepare("SELECT l.*, lt.temperature, lt.unit FROM logs l LEFT JOIN log_temperature lt ON lt.log_id=l.id WHERE l.baby_id=? AND l.alarm_enabled=1 AND l.alarm_triggered=0 ORDER BY l.logged_at DESC");
$stmt->execute([$activeBabyId]);
$activeAlarms = $stmt->fetchAll();

$pageTitle = 'Home — Baby Tracker';

function catIcon($cat) {
    return ['temperature'=>'🌡️','diaper'=>'💩','feeding'=>'🍼','sleep'=>'😴','play'=>'🎮','medicine'=>'💊','note'=>'📝'][$cat] ?? '📋';
}
function catLabel($cat) {
    return ucfirst($cat);
}
function logSummary($log) {
    switch ($log['category']) {
        case 'temperature': return $log['temperature'] ? $log['temperature'].'°'.$log['unit'] : '';
        case 'diaper':      return ucfirst($log['diaper_type'] ?? '');
        case 'feeding':     return ucfirst($log['feed_type'] ?? '') . ($log['amount'] ? ' · '.$log['amount'].' '.($log['feed_unit']??'') : '') . ($log['food'] ? ' · '.$log['food'] : '');
        case 'sleep':       return $log['duration_minutes'] ? floor($log['duration_minutes']/60).'h '.($log['duration_minutes']%60).'m · '.ucfirst($log['quality']??'') : '';
        case 'medicine':    return ($log['medicine_name']??'') . ($log['dose'] ? ' '.$log['dose'].' '.($log['med_unit']??'') : '');
        default:            return '';
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Header -->
<div class="app-header">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1>🍼 Baby Tracker</h1>
      <p>Hello, <?= e($user['name']) ?> 👋</p>
      <?php if ($activeBaby): ?>
      <a href="babies.php" class="baby-switcher">
        <span><?= $activeBaby['gender']==='male'?'👦':($activeBaby['gender']==='female'?'👧':'🧒') ?></span>
        <span><?= e($activeBaby['name']) ?></span>
        <?php if (count($babies) > 1): ?><span style="opacity:0.7">▾</span><?php endif; ?>
      </a>
      <?php endif; ?>
    </div>
    <a href="auth/logout.php" class="user-menu-btn" style="margin-top:4px">
      👤 Logout
    </a>
  </div>
</div>

<div class="main-wrap">

  <!-- Baby switcher dropdown if multiple -->
  <?php if (count($babies) > 1): ?>
  <div class="card mb-3" style="margin-top:4px">
    <div class="card-body py-2">
      <div class="d-flex gap-2 overflow-auto" style="scrollbar-width:none">
        <?php foreach ($babies as $b): ?>
        <a href="?switch=<?= $b['id'] ?>" class="filter-pill <?= $b['id']==$activeBabyId?'active':'' ?>">
          <?= $b['gender']==='male'?'👦':($b['gender']==='female'?'👧':'🧒') ?> <?= e($b['name']) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Alarm banners -->
  <div id="alarmBanners"></div>

  <!-- Active alarms JS data -->
  <script>
    const activeAlarmsData = <?= json_encode($activeAlarms) ?>;
  </script>

  <!-- 7-day Stats -->
  <div class="card">
    <div class="card-body">
      <p class="section-title">📊 Last 7 Days</p>
      <div class="row g-2">
        <?php
        $statItems = [
          ['temperature','🌡️','Temp'],
          ['feeding','🍼','Feeds'],
          ['sleep','😴','Sleeps'],
          ['diaper','💩','Diapers'],
        ];
        foreach ($statItems as [$cat,$icon,$label]):
        ?>
        <div class="col-3">
          <div class="stat-box">
            <div style="font-size:1.4rem"><?= $icon ?></div>
            <div class="stat-val"><?= $stats[$cat] ?? 0 ?></div>
            <div class="stat-lbl"><?= $label ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Quick Log -->
  <a href="log.php" class="btn-primary d-block text-center text-decoration-none mb-3" style="padding:14px">
    ➕ &nbsp;Log Activity
  </a>

  <!-- Today's Logs -->
  <p class="section-title">📋 Today's Logs</p>

  <?php if (empty($todayLogs)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <p>No activities logged today.<br>Tap <strong>Log Activity</strong> to start!</p>
  </div>
  <?php else: ?>
  <?php foreach ($todayLogs as $log):
    $summary = logSummary($log);
    $time    = date('h:i A', strtotime($log['logged_at']));
  ?>
  <div class="log-item <?= e($log['category']) ?>">
    <div class="d-flex justify-content-between align-items-start">
      <div class="d-flex align-items-center gap-2">
        <span class="cat-chip cat-<?= e($log['category']) ?>"><?= catIcon($log['category']) ?> <?= catLabel($log['category']) ?></span>
      </div>
      <div class="d-flex gap-1">
        <a href="log.php?edit=<?= $log['id'] ?>" class="btn-icon">✏️</a>
      </div>
    </div>
    <?php if ($summary): ?>
    <div class="log-detail mt-2"><?= e($summary) ?></div>
    <?php endif; ?>
    <div class="log-time">🕐 <?= $time ?></div>
    <?php if ($log['notes']): ?>
    <div class="log-notes">📝 <?= e($log['notes']) ?></div>
    <?php endif; ?>
    <?php if ($log['alarm_enabled'] && !$log['alarm_triggered']): ?>
    <div class="mt-2">
      <span class="countdown-chip" id="alarm-<?= $log['id'] ?>">⏰ Calculating...</span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/baby-tracker/assets/js/alarm.js"></script>
</body>
</html>
