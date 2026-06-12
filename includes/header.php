<?php
$currentUser = currentUser();
$user_id = $currentUser['id'];
$db = getDB();

// Get all babies for this user
$stmt = $db->prepare("SELECT * FROM babies WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user_id]);
$babies = $stmt->fetchAll();

// Set first baby as active if none selected
if (!currentBabyId() && count($babies) > 0) {
    setActiveBaby($babies[0]['id']);
}
$activeBabyId = currentBabyId();
$activeBaby = null;
foreach ($babies as $b) {
    if ($b['id'] == $activeBabyId) { $activeBaby = $b; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <meta name="theme-color" content="#e8735a"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-title" content="BabyTracker"/>
  <title><?= $pageTitle ?? 'Baby Tracker' ?></title>
  <link rel="manifest" href="/baby-tracker/manifest.json"/>
  <link rel="apple-touch-icon" href="/baby-tracker/assets/icons/icon-192.png"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="/baby-tracker/assets/css/app.css"/>
  <?= $extraHead ?? '' ?>
</head>
<body>
