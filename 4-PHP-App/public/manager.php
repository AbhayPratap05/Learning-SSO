<?php
require __DIR__ . '/auth.php';
$isAuthed = isset($_SESSION['user']) && !empty($_SESSION['user']['sub']);
include('template.php');

requireLogin();
requireClientRole('manager');
?>

<div class="container mt-5">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['username']) ?></h1>
    <p>This is the <strong>manager dashboard</strong></p>
</div>