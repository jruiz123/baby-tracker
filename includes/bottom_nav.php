<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="bottom-nav">
  <a href="/baby-tracker/index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
    <span class="nav-icon">🏠</span><span>Home</span>
  </a>
  <a href="/baby-tracker/log.php" class="nav-item <?= $currentPage === 'log' ? 'active' : '' ?>">
    <span class="nav-icon">➕</span><span>Log</span>
  </a>
  <a href="/baby-tracker/history.php" class="nav-item <?= $currentPage === 'history' ? 'active' : '' ?>">
    <span class="nav-icon">📋</span><span>History</span>
  </a>
  <a href="/baby-tracker/calendar.php" class="nav-item <?= $currentPage === 'calendar' ? 'active' : '' ?>">
    <span class="nav-icon">📅</span><span>Calendar</span>
  </a>
  <a href="/baby-tracker/babies.php" class="nav-item <?= $currentPage === 'babies' ? 'active' : '' ?>">
    <span class="nav-icon">👶</span><span>Baby</span>
  </a>
</nav>
