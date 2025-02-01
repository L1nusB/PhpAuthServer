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
        // $this->ldap = ldap_connect("ldap://ldc01.linus.com");
        $this->ldap = ldap_connect("ldap://ldc01.linus.com") or die("Could not connect to LDAP server.");

        $this->user = $_SERVER['PHP_AUTH_USER'];
        
        // Cache user's groups if not already in session
        if (!isset($_SESSION['user_groups'])) {
            $this->cacheUserGroups();
        }
    }

    private function cacheUserGroups() {
        $bind = @ldap_bind($this->ldap, $this->user . "@linus.com", $_SERVER['PHP_AUTH_PW']);
    
        if (!$bind) {
            die("LDAP bind failed: " . ldap_error($this->ldap));
        }
    
        $result = ldap_search($this->ldap, "DC=linus,DC=com", 
            "(sAMAccountName=" . $this->user . ")");
        
        if (!$result) {
            die("LDAP search failed: " . ldap_error($this->ldap));
        }
    
        // Extract and store groups
        $entries = ldap_get_entries($this->ldap, $result);
        if ($entries['count'] == 0) {
            die("No user found in LDAP.");
        }
    
        $_SESSION['user_groups'] = [];
        if (isset($entries[0]['memberof'])) {
            foreach ($entries[0]['memberof'] as $group) {
                $_SESSION['user_groups'][] = $group;
            }
        }
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