<?php
/**
 * DB-IP Reader
 *
 * Released under the MIT license
 */
namespace DbIpGeo;

// Include the DB-IP class
require "dbip.class.php";

use PDO;
use DBIP;

class Reader
{
    protected $db = null;
    protected $locale = null;

    const PDO_CONNECT        = "%s:host=%s;dbname=%s";
    const MESSAGE_DB_NOT_SET = 'DB not set';

    public function __construct($db, $locale = 'en') {
        $this->db = $db;
        $locales = ['en', 'ru'];
        $this->locale = ((in_array($locale, $locales)) ? $locale : 'en');
    }

    public function getGeo($ip)
    {
        if(is_array($this->db)) {
            if($this->isArrayKeysExists(['type', 'host', 'db', 'user', 'password'], $this->db)) {
                try {
                    $db = null;
                    $dbip = null;
                    $inf = null;
                    if(is_array($this->db['db'])) {
                        if($this->isArrayKeysExists(['name'], $this->db['db'])){
                            $db = new PDO(sprintf(self::PDO_CONNECT, $this->db['type'], $this->db['host'], $this->db['db']['name']), $this->db['user'], $this->db['password']);
                        } else {
                            return self::MESSAGE_DB_NOT_SET;
                        }
                    } else {
                        $db = new PDO(sprintf(self::PDO_CONNECT, $this->db['type'], $this->db['host'], $this->db['db']), $this->db['user'], $this->db['password']);
                    }
                    // Instanciate a new DBIP object with the database connection
                    $dbip = new DBIP($db);
                    if(isset($this->db['db']['city'])) {
                        $inf = $dbip->Lookup($ip, $this->db['db']['city']);
                    } else {
                        $inf = $dbip->Lookup($ip);
                    }
                    if((ip2long($ip) >= 167772160 && ip2long($ip) <= 184549375)
                        || (ip2long($ip) >= 2886729728 && ip2long($ip) <= 2887778303)
                        || (ip2long($ip) >= 3232235520 && ip2long($ip) <= 3232301055)) { //networks classes A,B,C
                        $data['country'] = 'LO';
                        $data['region'] = 'Local Network';
                        $data['city'] = 'Local Network';
                    } elseif((ip2long($ip) >= 2130706432 && ip2long($ip) <= 2147483647)) {
                        $data['country'] = 'LO';
                        $data['region'] = 'Loopback';
                        $data['city'] = 'Loopback';
                    } else {
                        $data['country'] = ($inf->country == 'ZZ') ? 'UN' : $inf->country;
                        $data['region'] = ($inf->stateprov == '') ? 'Unknown' : $inf->stateprov;
                        $data['city'] = ($inf->city == '') ? 'Unknown' : $inf->city;
                    }
                    if(isset($this->db['db']['isp'])) {
                        try {
                            $inf = $dbip->Lookup($ip, $this->db['db']['isp']);
                            $data['isp'] = ($inf->isp_name == '') ? 'Unknown' : $inf->isp_name;
                            $data['organization'] = ($inf->organization_name == '') ? 'Unknown' : $inf->organization_name;
                        } catch (\Exception $e) {
                            $data['isp'] = null;
                            $data['organization'] = null;
                        }
                    }
                } catch(\Exception $e) {
                    $data = $e->getMessage();
                }
                
            } else {
                $data = self::MESSAGE_DB_NOT_SET;
            }
        } else {
            $data = self::MESSAGE_DB_NOT_SET;
        }
        return $data;
    }

    protected function isArrayKeysExists(array $keys, array $arr)
    {
       return !array_diff_key(array_flip($keys), $arr);
    }
}
