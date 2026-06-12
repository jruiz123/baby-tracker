<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /baby-tracker/auth/login.php');
        exit;
    }
}

function currentUser() {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'email'=> $_SESSION['user_email']?? '',
    ];
}

function currentBabyId() {
    return $_SESSION['active_baby_id'] ?? null;
}

function setActiveBaby($babyId) {
    $_SESSION['active_baby_id'] = $babyId;
}

function logout() {
    session_destroy();
    header('Location: /baby-tracker/auth/login.php');
    exit;
}

function redirect($path) {
    header('Location: /baby-tracker/' . ltrim($path, '/'));
    exit;
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
