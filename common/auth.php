<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

        if (!$this->ldap) {
            die("Could not connect to LDAP server.");
        }

        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0);

        $this->user = $_SERVER['PHP_AUTH_USER'];
        
        // Cache user's groups if not already in session
        if (!isset($_SESSION['user_groups'])) {
            $this->cacheUserGroups();
        }
    }

    private function cacheUserGroups() {
        $userDN = $this->user . "@linus.com";  // AD requires full UPN format
        $password = $_SERVER['PHP_AUTH_PW'];   // User password from Apache

        // Bind to LDAP with user credentials
        $bind = @ldap_bind($this->ldap, $userDN, $password);

        if (!$bind) {
            die("LDAP bind failed: " . ldap_error($this->ldap));
        }

        // Perform LDAP search to get user information
        $search = ldap_search($this->ldap, "DC=linus,DC=com", "(sAMAccountName={$this->user})", ["memberOf"]);

        if (!$search) {
            die("LDAP search failed: " . ldap_error($this->ldap));
        }

        $entries = ldap_get_entries($this->ldap, $search);

        if ($entries['count'] == 0) {
            die("No user found in LDAP.");
        }

        // Extract user groups
        $_SESSION['user_groups'] = [];

        if (isset($entries[0]['memberof'])) {
            for ($i = 0; $i < $entries[0]['memberof']['count']; $i++) {
                // Extract the CN (Common Name) from the full DN
                preg_match('/CN=([^,]+)/', $entries[0]['memberof'][$i], $matches);
                if (!empty($matches[1])) {
                    $_SESSION['user_groups'][] = $matches[1];
                }
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