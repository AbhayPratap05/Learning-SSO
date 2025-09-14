<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$isAuthed = isset($_SESSION['user']) && !empty($_SESSION['user']['sub']);
include("template.php");

?>

<div class="container mt-5">
    <h2>Home</h2>
    <?php if ($isAuthed): ?>
        <h5>Welcome, <span class="text-info"><?= htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'user', ENT_QUOTES) ?></span>!</h5>
        <h4 class="mt-5">Role-Based Access Control (RBAC)</h4>
        <table class="table mt-4">
            <thead>
                <tr>
                    <th scope="col">Endpoint</th>
                    <th scope="col">Role Required</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <a
                            class="text-info"
                            href="/call-api.php">Call API</a>
                    </td>
                    <td class="text-warning">
                        Admin (Realm-role)
                    </td>
                </tr>
                <tr>
                    <td>
                        <a
                            class="text-info"
                            href="/manager.php">Manager Dashboard</a>
                    </td>
                    <td class="text-warning">Manager (Client role)</td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <h5>You are not logged in. Please log in with Keycloak.</h5>
    <?php endif; ?>
</div>