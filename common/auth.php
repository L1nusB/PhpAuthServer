<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


class EnvironmentAccess {
    private $ldap;
    private $user;

    // 🔹 Replace these with actual service account credentials
    private $bindUser = "sa_ldap@linus.com";  // Dummy user for initial bind
    private $bindPassword = "Test123";
    
    private $accessMatrix = [
        'Developer' => ['prod', 'test', 'dev'],
        'Analyst' => ['prod', 'test'],
        'Department' => ['prod'],
        'Admin' => ['prod', 'test', 'dev']
    ];

    public function __construct() {
        $this->ldap = ldap_connect("ldap://ldc01.linus.com") or die("Could not connect to LDAP server.");

        if (!$this->ldap) {
            die("Could not connect to LDAP server.");
        }

        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0);

        $this->user = $_SERVER['PHP_AUTH_USER'];

        if (!isset($_SESSION['user_groups'])) {
            $this->cacheUserGroups();
        }
    }

    private function cacheUserGroups() {
        // 🔹 Step 1: Bind with the service account
        $bind = @ldap_bind($this->ldap, $this->bindUser, $this->bindPassword);

        if (!$bind) {
            die("Initial LDAP bind failed: " . ldap_error($this->ldap));
        }

        // 🔹 Step 2: Find the actual DN of the user
        $search = ldap_search($this->ldap, "DC=linus,DC=com", "(sAMAccountName={$this->user})", ["dn", "memberOf"]);
        if (!$search) {
            die("LDAP search failed: " . ldap_error($this->ldap));
        }

        $entries = ldap_get_entries($this->ldap, $search);

        if ($entries['count'] == 0) {
            die("User not found in LDAP.");
        }

        $userDN = $entries[0]['dn'];  // Extract user's full DN

        // 🔹 Step 3: Rebind with the user's actual credentials
        $password = $_SERVER['PHP_AUTH_PW'];
        $userBind = @ldap_bind($this->ldap, $userDN, $password);

        if (!$userBind) {
            die("User authentication failed: " . ldap_error($this->ldap));
        }

        // 🔹 Step 4: Extract and store user groups
        $_SESSION['user_groups'] = [];

        if (isset($entries[0]['memberof'])) {
            for ($i = 0; $i < $entries[0]['memberof']['count']; $i++) {
                preg_match('/CN=([^,]+)/', $entries[0]['memberof'][$i], $matches);
                if (!empty($matches[1])) {
                    $_SESSION['user_groups'][] = $matches[1];
                }
            }
        }
    }

    public function canAccessEnvironment($env) {
        foreach ($_SESSION['user_groups'] as $group) {
            if (isset($this->accessMatrix[$group]) && in_array($env, $this->accessMatrix[$group])) {
                return true;
            }
        }
        return false;
    }
}