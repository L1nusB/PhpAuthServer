<?php
require_once '../common/auth.php';

$auth = new EnvironmentAccess();
if (!$auth->canAccessEnvironment('dev')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// Continue with environment-specific code
?>