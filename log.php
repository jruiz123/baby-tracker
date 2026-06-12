<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db      = getDB();
$user    = currentUser();
$user_id = $user['id'];

// Get active baby
$stmt = $db->prepare("SELECT * FROM babies WHERE user_id=? ORDER BY name ASC");
$stmt->execute([$user_id]);
$babies = $stmt->fetchAll();
if (empty($babies)) redirect('babies.php');
if (!currentBabyId()) setActiveBaby($babies[0]['id']);
$activeBabyId = currentBabyId();
$activeBaby   = null;
foreach ($babies as $b) { if ($b['id'] == $activeBabyId) { $activeBaby = $b; break; } }

$error   = '';
$success = '';
$editLog = null;

// Load for edit
$editId = intval($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $db->prepare("
        SELECT l.*, lt.temperature, lt.unit as temp_unit, lt.symptoms,
          ld.type as diaper_type, ld.color as diaper_color,
          lf.type as feed_type, lf.amount, lf.unit as feed_unit, lf.food,
          ls.duration_minutes, ls.quality,
          lm.medicine_name, lm.dose, lm.unit as med_unit
        FROM logs l
        LEFT JOIN log_temperature lt ON lt.log_id=l.id
        LEFT JOIN log_diaper ld ON ld.log_id=l.id
        LEFT JOIN log_feeding lf ON lf.log_id=l.id
        LEFT JOIN log_sleep ls ON ls.log_id=l.id
        LEFT JOIN log_medicine lm ON lm.log_id=l.id
        WHERE l.id=? AND l.user_id=?
    ");
    $stmt->execute([$editId, $user_id]);
    $editLog = $stmt->fetch();
}

// Handle delete
if (isset($_GET['delete']) && intval($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM logs WHERE id=? AND user_id=?");
    $stmt->execute([intval($_GET['delete']), $user_id]);
    redirect('history.php');
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category      = $_POST['category'] ?? '';
    $logged_at     = $_POST['logged_at'] ?? date('Y-m-d H:i:s');
    $notes         = trim($_POST['notes'] ?? '');
    $alarm_enabled = isset($_POST['alarm_enabled']) ? 1 : 0;
    $alarm_minutes = $alarm_enabled ? intval($_POST['alarm_minutes'] ?? 240) : 240;
    if ($alarm_minutes === -1) $alarm_minutes = intval($_POST['alarm_custom'] ?? 240);

    $validCats = ['temperature','diaper','feeding','sleep','play','medicine','note'];
    if (!in_array($category, $validCats)) { $error = 'Please select a category.'; }

    if (!$error) {
        $db->beginTransaction();
        try {
            if ($editId) {
                $stmt = $db->prepare("UPDATE logs SET category=?,logged_at=?,notes=?,alarm_enabled=?,alarm_minutes=? WHERE id=? AND user_id=?");
                $stmt->execute([$category, $logged_at, $notes, $alarm_enabled, $alarm_minutes, $editId, $user_id]);
                $logId = $editId;
                // Delete old detail
                foreach (['log_temperature','log_diaper','log_feeding','log_sleep','log_medicine'] as $t) {
                    $db->prepare("DELETE FROM $t WHERE log_id=?")->execute([$logId]);
                }
            } else {
                $stmt = $db->prepare("INSERT INTO logs (baby_id,user_id,category,logged_at,notes,alarm_enabled,alarm_minutes) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$activeBabyId, $user_id, $category, $logged_at, $notes, $alarm_enabled, $alarm_minutes]);
                $logId = $db->lastInsertId();
            }

            // Insert category detail
            switch ($category) {
                case 'temperature':
                    $symptoms = implode(',', $_POST['symptoms'] ?? []);
                    $stmt = $db->prepare("INSERT INTO log_temperature (log_id,temperature,unit,symptoms) VALUES (?,?,?,?)");
                    $stmt->execute([$logId, floatval($_POST['temperature']), $_POST['temp_unit']??'C', $symptoms]);
                    break;
                case 'diaper':
                    $stmt = $db->prepare("INSERT INTO log_diaper (log_id,type,color) VALUES (?,?,?)");
                    $stmt->execute([$logId, $_POST['diaper_type']??'wet', $_POST['diaper_color']??'']);
                    break;
                case 'feeding':
                    $stmt = $db->prepare("INSERT INTO log_feeding (log_id,type,amount,unit,food) VALUES (?,?,?,?,?)");
                    $stmt->execute([$logId, $_POST['feed_type']??'formula', floatval($_POST['feed_amount']??0)?:null, $_POST['feed_unit']??'ml', trim($_POST['feed_food']??'')]);
                    break;
                case 'sleep':
                    $stmt = $db->prepare("INSERT INTO log_sleep (log_id,duration_minutes,quality) VALUES (?,?,?)");
                    $stmt->execute([$logId, intval($_POST['sleep_duration']??0)?:null, $_POST['sleep_quality']??'good']);
                    break;
                case 'medicine':
                    $stmt = $db->prepare("INSERT INTO log_medicine (log_id,medicine_name,dose,unit) VALUES (?,?,?,?)");
                    $stmt->execute([$logId, trim($_POST['med_name']??''), floatval($_POST['med_dose']??0)?:null, $_POST['med_unit']??'ml']);
                    break;
            }

            $db->commit();
            redirect('index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

$now = date('Y-m-d\TH:i');
$sel = $editLog['category'] ?? ($_GET['cat'] ?? '');
$pageTitle = ($editId ? 'Edit' : 'Log') . ' Activity';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <a href="index.php" class="btn-back">←</a>
    <div>
      <h1><?= $editId ? '✏️ Edit Log' : '➕ Log Activity' ?></h1>
      <p><?= e($activeBaby['name'] ?? '') ?></p>
    </div>
  </div>
</div>

<div class="main-wrap">

  <?php if ($error): ?>
  <div class="alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="logForm" novalidate>

    <!-- Category Selector -->
    <div class="card">
      <div class="card-body">
        <label class="form-label">Category</label>
        <div class="cat-grid">
          <?php
          $cats = [
            ['temperature','🌡️','Temp'],
            ['diaper','💩','Diaper'],
            ['feeding','🍼','Feeding'],
            ['sleep','😴','Sleep'],
            ['play','🎮','Play'],
            ['medicine','💊','Medicine'],
            ['note','📝','Note'],
          ];
          foreach ($cats as [$val,$icon,$label]):
          ?>
          <div class="cat-btn <?= $sel===$val?'selected':'' ?>" onclick="selectCat('<?= $val ?>')" data-cat="<?= $val ?>">
            <span class="cat-icon"><?= $icon ?></span>
            <span class="cat-label"><?= $label ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="category" id="categoryInput" value="<?= e($sel) ?>" required/>
      </div>
    </div>

    <!-- Date & Time -->
    <div class="card">
      <div class="card-body">
        <label class="form-label">📅 Date &amp; Time</label>
        <input type="datetime-local" name="logged_at" class="form-control"
          value="<?= $editLog ? date('Y-m-d\TH:i', strtotime($editLog['logged_at'])) : $now ?>" required/>
      </div>
    </div>

    <!-- ── Temperature Fields ── -->
    <div class="card detail-panel" id="panel-temperature" style="display:none">
      <div class="card-body">
        <label class="form-label">🌡️ Temperature</label>
        <div class="d-flex gap-2 mb-3">
          <input type="number" name="temperature" class="form-control flex-grow-1"
            placeholder="e.g. 37.8" step="0.1" min="30" max="45"
            value="<?= e($editLog['temperature'] ?? '') ?>"/>
          <select name="temp_unit" class="form-select" style="width:80px;flex-shrink:0">
            <option value="C" <?= ($editLog['temp_unit']??'C')==='C'?'selected':'' ?>>°C</option>
            <option value="F" <?= ($editLog['temp_unit']??'')==='F'?'selected':'' ?>>°F</option>
          </select>
        </div>
        <label class="form-label">Symptoms</label>
        <?php
        $symList  = ['🥶 Shivering','🔥 Sweating','😴 Lethargic','😭 Fussy/Crying','🤧 Runny Nose','😮 Coughing','🤢 Vomiting','💩 Diarrhea','😐 Alert','😊 Active &amp; Playful'];
        $selSyms  = $editLog ? explode(',', $editLog['symptoms'] ?? '') : [];
        foreach ($symList as $s):
          $val = trim(strip_tags(html_entity_decode($s)));
          $id  = 'sym_' . md5($val);
        ?>
        <input type="checkbox" class="symptom-check" name="symptoms[]" id="<?= $id ?>" value="<?= e($val) ?>"
          <?= in_array($val, $selSyms)?'checked':'' ?>/>
        <label class="symptom-label" for="<?= $id ?>"><?= $s ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Diaper Fields ── -->
    <div class="card detail-panel" id="panel-diaper" style="display:none">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">💩 Diaper Type</label>
          <select name="diaper_type" class="form-select">
            <?php foreach(['wet'=>'💧 Wet','solid'=>'💩 Solid','mixed'=>'🌀 Mixed','dry'=>'✅ Dry'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editLog['diaper_type']??'')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Color (optional)</label>
          <input type="text" name="diaper_color" class="form-control" placeholder="e.g. yellow, green"
            value="<?= e($editLog['diaper_color'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <!-- ── Feeding Fields ── -->
    <div class="card detail-panel" id="panel-feeding" style="display:none">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">🍼 Feeding Type</label>
          <select name="feed_type" class="form-select">
            <?php foreach(['breast'=>'🤱 Breastfeed','formula'=>'🍼 Formula','solid'=>'🥣 Solid Food','water'=>'💧 Water','snack'=>'🍌 Snack'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editLog['feed_type']??'')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label">Amount (optional)</label>
            <input type="number" name="feed_amount" class="form-control" placeholder="e.g. 120" step="0.5" min="0"
              value="<?= e($editLog['amount'] ?? '') ?>"/>
          </div>
          <div class="col-5">
            <label class="form-label">Unit</label>
            <select name="feed_unit" class="form-select">
              <?php foreach(['ml','oz','g','tbsp'] as $u): ?>
              <option value="<?= $u ?>" <?= ($editLog['feed_unit']??'ml')===$u?'selected':'' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label">Food Item (optional)</label>
          <input type="text" name="feed_food" class="form-control" placeholder="e.g. rice cereal, banana"
            value="<?= e($editLog['food'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <!-- ── Sleep Fields ── -->
    <div class="card detail-panel" id="panel-sleep" style="display:none">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">⏱️ Duration (minutes)</label>
          <input type="number" name="sleep_duration" class="form-control" placeholder="e.g. 90 for 1.5 hrs" min="0"
            value="<?= e($editLog['duration_minutes'] ?? '') ?>"/>
          <small class="text-muted fw-600">60 = 1 hour, 90 = 1.5 hours, 120 = 2 hours</small>
        </div>
        <div>
          <label class="form-label">😴 Sleep Quality</label>
          <select name="sleep_quality" class="form-select">
            <?php foreach(['good'=>'😊 Good','restless'=>'😶 Restless','poor'=>'😞 Poor'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editLog['quality']??'good')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- ── Play (no extra fields) ── -->
    <div class="card detail-panel" id="panel-play" style="display:none">
      <div class="card-body">
        <p class="text-muted fw-700 mb-0">🎮 Just add notes below to describe the play activity!</p>
      </div>
    </div>

    <!-- ── Medicine Fields ── -->
    <div class="card detail-panel" id="panel-medicine" style="display:none">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">💊 Medicine Name</label>
          <input type="text" name="med_name" class="form-control" placeholder="e.g. Paracetamol, Ibuprofen"
            value="<?= e($editLog['medicine_name'] ?? '') ?>"/>
        </div>
        <div class="row g-2">
          <div class="col-7">
            <label class="form-label">Dose (optional)</label>
            <input type="number" name="med_dose" class="form-control" placeholder="e.g. 2.5" step="0.1" min="0"
              value="<?= e($editLog['dose'] ?? '') ?>"/>
          </div>
          <div class="col-5">
            <label class="form-label">Unit</label>
            <select name="med_unit" class="form-select">
              <?php foreach(['ml','mg','tablet','drop','tsp'] as $u): ?>
              <option value="<?= $u ?>" <?= ($editLog['med_unit']??'ml')===$u?'selected':'' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Note (no extra fields) ── -->
    <div class="card detail-panel" id="panel-note" style="display:none">
      <div class="card-body">
        <p class="text-muted fw-700 mb-0">📝 Use the notes field below to write your observation.</p>
      </div>
    </div>

    <!-- Notes (shared) -->
    <div class="card">
      <div class="card-body">
        <label class="form-label">📝 Notes (optional)</label>
        <textarea name="notes" class="form-control" rows="2"
          placeholder="Any observations or additional details..."><?= e($editLog['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Alarm -->
    <div class="card">
      <div class="card-body">
        <div class="alarm-toggle-wrap">
          <label for="alarmToggle">⏰ Set Reminder Alarm</label>
          <input type="checkbox" class="form-check-input" name="alarm_enabled" id="alarmToggle"
            <?= ($editLog['alarm_enabled']??0)?'checked':'' ?> onchange="toggleAlarm(this.checked)"/>
        </div>
        <div id="alarmOptions" style="display:<?= ($editLog['alarm_enabled']??0)?'block':'none' ?>">
          <label class="form-label mt-2">Remind me in</label>
          <select name="alarm_minutes" class="form-select" id="alarmSelect" onchange="toggleCustomAlarm(this.value)">
            <option value="60"  <?= ($editLog['alarm_minutes']??240)==60 ?'selected':'' ?>>1 hour</option>
            <option value="120" <?= ($editLog['alarm_minutes']??240)==120?'selected':'' ?>>2 hours</option>
            <option value="180" <?= ($editLog['alarm_minutes']??240)==180?'selected':'' ?>>3 hours</option>
            <option value="240" <?= ($editLog['alarm_minutes']??240)==240?'selected':'' ?>>4 hours (default)</option>
            <option value="360" <?= ($editLog['alarm_minutes']??240)==360?'selected':'' ?>>6 hours</option>
            <option value="480" <?= ($editLog['alarm_minutes']??240)==480?'selected':'' ?>>8 hours</option>
            <option value="-1">Custom...</option>
          </select>
          <div id="customAlarm" style="display:none;margin-top:8px">
            <input type="number" name="alarm_custom" class="form-control" placeholder="Minutes (e.g. 90)" min="5" max="1440"/>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" class="btn-primary mb-2"><?= $editId ? '💾 Update Entry' : '💾 Save Entry' ?></button>
    <?php if ($editId): ?>
    <a href="?delete=<?= $editId ?>" class="btn btn-outline-danger w-100"
      onclick="return confirm('Delete this log entry?')"
      style="border-radius:var(--radius-sm);font-weight:700;padding:12px">🗑️ Delete Entry</a>
    <?php endif; ?>

  </form>
</div>

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectCat(cat) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.toggle('selected', b.dataset.cat === cat));
  document.getElementById('categoryInput').value = cat;
  document.querySelectorAll('.detail-panel').forEach(p => p.style.display = 'none');
  const panel = document.getElementById('panel-' + cat);
  if (panel) panel.style.display = 'block';
}
function toggleAlarm(checked) {
  document.getElementById('alarmOptions').style.display = checked ? 'block' : 'none';
}
function toggleCustomAlarm(val) {
  document.getElementById('customAlarm').style.display = val === '-1' ? 'block' : 'none';
}
// Init panels on load
document.addEventListener('DOMContentLoaded', () => {
  const cat = document.getElementById('categoryInput').value;
  if (cat) selectCat(cat);
});
</script>
</body>
</html>
