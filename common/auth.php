<?php
session_start();

class EnvironmentAccess {
    private $ldap;
    private $user;
    
    private $accessMatrix = [
        'Developer' => ['prod', 'test', 'dev'],
        'Analyst' => ['prod', 'test'],
        'Department' => ['prod'],
        'Admin' => ['prod', 'test', 'dev']
    ];

    public function __construct() {
        // Connect to LDAP and cache user info
        $this->ldap = ldap_connect("ldap://ldc01.linus.com");
        $this->user = $_SERVER['PHP_AUTH_USER'];
        
        // Cache user's groups if not already in session
        if (!isset($_SESSION['user_groups'])) {
            $this->cacheUserGroups();
        }
    }

    private function cacheUserGroups() {
        // Perform LDAP search to get user's groups
        $bind = ldap_bind($this->ldap, $this->user . "@linus.com", $_SERVER['PHP_AUTH_PW']);
        $result = ldap_search($this->ldap, "DC=linus,DC=com", 
            "(sAMAccountName=" . $this->user . ")");
        // Store groups in session
        $_SESSION['user_groups'] = [/* extracted groups */];
    }

    public function canAccessEnvironment($env) {
        foreach ($_SESSION['user_groups'] as $group) {
            if (isset($this->accessMatrix[$group]) && 
                in_array($env, $this->accessMatrix[$group])) {
                return true;
            }
        }
        return false;
    }
}