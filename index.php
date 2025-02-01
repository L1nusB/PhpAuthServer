<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once 'common/auth.php';

$auth = new EnvironmentAccess();

// Show available environments based on user's access
$environments = [];
foreach (['prod', 'test', 'dev'] as $env) {
    if ($auth->canAccessEnvironment($env)) {
        $environments[] = $env;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Manager</title>
</head>
<body>
    <h1>Welcome to Database Manager</h1>
    <h2>Available Environments:</h2>
    <ul>
    <?php foreach ($environments as $env): ?>
        <li>
            <a href="/<?php echo $env; ?>/dashboard.php">
                <?php echo ucfirst($env); ?> Environment
            </a>
        </li>
    <?php endforeach; ?>
    </ul>
</body>
</html>