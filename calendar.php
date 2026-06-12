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

// Current month/year
$year  = intval($_GET['y'] ?? date('Y'));
$month = intval($_GET['m'] ?? date('n'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12){ $month = 1;  $year++; }

$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get all log dates this month with categories
$stmt = $db->prepare("
    SELECT DATE(logged_at) as log_date, GROUP_CONCAT(DISTINCT category) as categories
    FROM logs
    WHERE baby_id=? AND YEAR(logged_at)=? AND MONTH(logged_at)=?
    GROUP BY DATE(logged_at)
");
$stmt->execute([$activeBabyId, $year, $month]);
$logDates = [];
foreach ($stmt->fetchAll() as $row) {
    $logDates[$row['log_date']] = explode(',', $row['categories']);
}

// Logs for selected date
$stmt = $db->prepare("
    SELECT l.*,
      lt.temperature, lt.unit as temp_unit,
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
    WHERE l.baby_id=? AND DATE(l.logged_at)=?
    ORDER BY l.logged_at ASC
");
$stmt->execute([$activeBabyId, $selectedDate]);
$selectedLogs = $stmt->fetchAll();

function catIcon($c) { return ['temperature'=>'🌡️','diaper'=>'💩','feeding'=>'🍼','sleep'=>'😴','play'=>'🎮','medicine'=>'💊','note'=>'📝'][$c]??'📋'; }
function logSummary($log) {
    switch ($log['category']) {
        case 'temperature': return $log['temperature'] ? $log['temperature'].'°'.$log['temp_unit'] : '';
        case 'diaper':      return ucfirst($log['diaper_type']??'');
        case 'feeding':     return ucfirst($log['feed_type']??'').($log['amount']?' · '.$log['amount'].' '.($log['feed_unit']??''):'').($log['food']?' · '.$log['food']:'');
        case 'sleep':       return $log['duration_minutes'] ? floor($log['duration_minutes']/60).'h '.($log['duration_minutes']%60).'m' : '';
        case 'medicine':    return $log['medicine_name']??'';
        default: return '';
    }
}

// Calendar math
$firstDay    = mktime(0,0,0,$month,1,$year);
$daysInMonth = date('t', $firstDay);
$startDow    = date('w', $firstDay); // 0=Sun
$today       = date('Y-m-d');
$prevM = $month - 1; $prevY = $year; if ($prevM < 1){ $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year; if ($nextM > 12){ $nextM = 1;  $nextY++; }

$pageTitle = 'Calendar';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <a href="index.php" class="btn-back">←</a>
    <div>
      <h1>📅 Calendar</h1>
      <p><?= e($activeBaby['name'] ?? '') ?></p>
    </div>
  </div>
</div>

<div class="main-wrap">

  <!-- Month nav -->
  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between">
        <a href="?y=<?= $prevY ?>&m=<?= $prevM ?>&date=<?= date('Y-m-d', mktime(0,0,0,$prevM,1,$prevY)) ?>"
          class="btn-icon" style="font-size:1.2rem;padding:8px 12px">‹</a>
        <span style="font-weight:800;font-size:1rem"><?= date('F Y', $firstDay) ?></span>
        <a href="?y=<?= $nextY ?>&m=<?= $nextM ?>&date=<?= date('Y-m-d', mktime(0,0,0,$nextM,1,$nextY)) ?>"
          class="btn-icon" style="font-size:1.2rem;padding:8px 12px">›</a>
      </div>

      <!-- Day headers -->
      <div class="cal-grid mt-3">
        <?php foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
        <div class="cal-header-day"><?= $d ?></div>
        <?php endforeach; ?>

        <!-- Empty cells before first day -->
        <?php for ($i = 0; $i < $startDow; $i++): ?>
        <div class="cal-day other-month"></div>
        <?php endfor; ?>

        <!-- Days -->
        <?php for ($d = 1; $d <= $daysInMonth; $d++):
          $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $isToday  = $dateStr === $today;
          $isSel    = $dateStr === $selectedDate;
          $hasLogs  = isset($logDates[$dateStr]);
          $classes  = implode(' ', array_filter(['cal-day', $isToday?'today':'', $isSel?'selected':'', $hasLogs?'has-logs':'']));
        ?>
        <a href="?y=<?= $year ?>&m=<?= $month ?>&date=<?= $dateStr ?>"
          class="<?= $classes ?>" style="text-decoration:none;color:inherit">
          <?= $d ?>
        </a>
        <?php endfor; ?>

        <!-- Fill remaining cells -->
        <?php
        $total = $startDow + $daysInMonth;
        $remainder = (7 - ($total % 7)) % 7;
        for ($i = 0; $i < $remainder; $i++):
        ?>
        <div class="cal-day other-month"></div>
        <?php endfor; ?>
      </div>

      <!-- Category legend for month -->
      <?php if (!empty($logDates)): ?>
      <div class="d-flex flex-wrap gap-1 mt-2">
        <?php
        $allCats = [];
        foreach ($logDates as $cats) foreach ($cats as $c) $allCats[$c] = true;
        foreach (array_keys($allCats) as $c):
        ?>
        <span class="cat-chip cat-<?= e($c) ?>" style="font-size:0.7rem;padding:3px 8px"><?= catIcon($c) ?> <?= ucfirst($c) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Selected date logs -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <p class="section-title mb-0">
      <?= date('D, M d', strtotime($selectedDate)) ?>
    </p>
    <a href="log.php" class="btn-icon" style="padding:8px 12px;font-size:0.82rem;font-weight:700;background:var(--primary);color:white">➕ Log</a>
  </div>

  <?php if (empty($selectedLogs)): ?>
  <div class="empty-state" style="padding:30px 20px">
    <div class="empty-icon" style="font-size:2.5rem">📋</div>
    <p>No activities on this day.</p>
  </div>
  <?php else: ?>
  <?php foreach ($selectedLogs as $log):
    $summary = logSummary($log);
    $time    = date('h:i A', strtotime($log['logged_at']));
  ?>
  <div class="log-item <?= e($log['category']) ?>">
    <div class="d-flex justify-content-between align-items-center">
      <span class="cat-chip cat-<?= e($log['category']) ?>"><?= catIcon($log['category']) ?> <?= ucfirst($log['category']) ?></span>
      <div class="d-flex gap-1">
        <a href="log.php?edit=<?= $log['id'] ?>" class="btn-icon">✏️</a>
        <a href="log.php?delete=<?= $log['id'] ?>"
          class="btn-icon btn-del" onclick="return confirm('Delete this entry?')">🗑️</a>
      </div>
    </div>
    <?php if ($summary): ?><div class="log-detail mt-2"><?= e($summary) ?></div><?php endif; ?>
    <div class="log-time">🕐 <?= $time ?></div>
    <?php if ($log['notes']): ?><div class="log-notes">📝 <?= e($log['notes']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
