<?php
/**
 *
 * DB-IP.com database query and management class
 *
 * Copyright (C) 2017 db-ip.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

class DBIP_Exception extends Exception {

}

class DBIP {

	const TYPE_COUNTRY = 1;
	const TYPE_CITY = 2;
	const TYPE_LOCATION = 3;
	const TYPE_ISP = 4;
	const TYPE_FULL = 5;

	private $db;

	public function __construct(PDO $db) {
		$this->db = $db;
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function Import_From_CSV($filename, $type, $table_name = "dbip_lookup", $progress_callback = null) {
		switch ($type) {
			case self::TYPE_COUNTRY:
			$fields = array("ip_start", "ip_end", "country");
			break;
			case self::TYPE_CITY:
			$fields = array("ip_start", "ip_end", "country", "stateprov", "city");
			break;
			case self::TYPE_LOCATION:
			$fields = array("ip_start", "ip_end", "country", "stateprov", "district", "city", "zipcode", "latitude", "longitude", "geoname_id", "timezone_offset", "timezone_name");
			break;
			case self::TYPE_ISP:
			$fields = array("ip_start", "ip_end", "country", "isp_name", "connection_type", "organization_name");
			break;
			case self::TYPE_FULL:
			$fields = array("ip_start", "ip_end", "country", "stateprov", "district", "city", "zipcode", "latitude", "longitude", "geoname_id", "timezone_offset", "timezone_name", "isp_name", "connection_type", "organization_name");
			break;
			default:
			throw new DBIP_Exception("invalid database type");
		}
		if (!file_exists($filename)) {
			throw new DBIP_Exception("file {$filename} does not exists");
		}
		$q = $this->Prepare_Import($table_name, $fields);
		if (substr($filename, -3) === ".gz") {
			$openname = "compress.zlib://" . $filename;
		} else {
			$openname = $filename;
		}
		$f = @fopen($openname, "r");
		if (!is_resource($f)) {
			throw new DBIP_Exception("cannot open {$filename} for reading");
		}
		$nrecs = 0;
		while ($r = fgetcsv($f, 1024, ",", '"', "\0")) {
			foreach ($r as $k => $v) {
				if (!isset($fields[$k])) {
					throw new DBIP_Exception("invalid CSV record for {$r[0]}");
				}
				switch ($fields[$k]) {
					case "connection_type":
					$r[$k] = $v ?: null;
					break;
					case "latitude":
					case "longitude":
					case "timezone_offset":
					$r[$k] = (float)$v;
					break;
					case "isp_name":
					case "organization_name":
					$r[$k] = substr($v, 0, 240);
					break;
					case "city":
					case "district":
					case "stateprov":
					$r[$k] = substr($v, 0, 80);
					break;
					case "zipcode":
					$r[$k] = substr($v, 0, 20);
					break;
					case "timezone_name":
					$r[$k] = substr($v, 0, 64);
					break;
					case "geoname_id":
					if (!$r[$k]) $r[$k] = null;
					break;
					default:
				}
			}
			$r[] = self::Addr_Type($r[0]);
			$r[0] = inet_pton($r[0]);
			$r[1] = inet_pton($r[1]);
			$this->Import_Row($q, $r);
			if ((($nrecs % 100) === 0) && is_callable($progress_callback)) {
				call_user_func($progress_callback, $nrecs);
			}
			$nrecs++;
		}
		fclose($f);
		$this->Finalize_Import();
		return $nrecs;
	}

	public function Lookup($addr, $table_name = "dbip_lookup") {
		if ($ret = $this->Do_Lookup($table_name, self::Addr_Type($addr), inet_pton($addr))) {
			$ret->ip_start = inet_ntop($ret->ip_start);
			$ret->ip_end = inet_ntop($ret->ip_end);
			return $ret;
		} else {
			throw new DBIP_Exception("address not found");
		}
	}

	protected function Prepare_Import($table_name, $fields) {
		try {
			if ($this->db->query("select count(*) as cnt from `{$table_name}`")->fetchObject()->cnt) {
				throw new DBIP_Exception("table {$table_name} is not empty");
			}
			$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->db->beginTransaction();
			$q = $this->db->prepare("insert into `{$table_name}` (" . implode(",", $fields) . ",addr_type) values (" . implode(",", array_fill(0, count($fields), "?")) . ",?)");
			return $q;
		} catch (PDOException $e) {
			throw new DBIP_Exception("database error: {$e->getMessage()}");
		}
	}

	protected function Finalize_Import() {
		$this->db->commit();
	}

	protected function Import_Row($res, $row) {
		return $res->execute($row);
	}

	protected function Do_Lookup($table_name, $addr_type, $addr_start) {
		$q = $this->db->prepare("select * from `{$table_name}` where addr_type = ? and ip_start <= ? order by ip_start desc limit 1");
		$q->execute(array($addr_type, $addr_start));
		return $q->fetchObject();
	}

	static private function Addr_Type($addr) {
		if (ip2long($addr) !== false) {
			return "ipv4";
		} else if (preg_match('/^[0-9a-fA-F:]+$/', $addr) && @inet_pton($addr)) {
			return "ipv6";
		}
		throw new DBIP_Exception("unknown address type for {$addr}");
	}

}

/**
 * DBIP MySQL class
 *
 * If you are using a MySQL database backend, please prefer the DBIP base class which uses the PDO mysql driver.
 * This is only meant to be used on PHP installations where PDO is disabled and the old mysql_* interface is available.
 */
class DBIP_MySQL extends DBIP {

	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	protected function Prepare_Import($table_name, $fields) {
		$q = mysql_query("select count(*) as cnt from `{$table_name}`", $this->db);
		$r = mysql_fetch_object($q);
		mysql_free_result($q);
		if ($r->cnt) {
			throw new DBIP_Exception("table {$table_name} is not empty");
		}
		return array($table_name, $fields);
	}

	protected function Finalize_Import() {
	}

	protected function Import_Row($res, $row) {
		$vals = array();
		foreach ($row as $val) {
			$vals[] = "'" . mysql_real_escape_string($val) . "'";
		}
		if (!mysql_query("insert into `{$res[0]}` (" . implode(",", $res[1]) . ",addr_type) values (" . implode(",", $vals) . ")", $this->db)) {
			throw new DBIP_Exception("database error: cannot insert row");
		}
		return true;
	}

	protected function Do_Lookup($table_name, $addr_type, $addr_start) {
		$q = mysql_query("select * from `{$table_name}` where addr_type = '{$addr_type}' and ip_start <= '" . mysql_real_escape_string($addr_start) . "' order by ip_start desc limit 1", $this->db);
		$r = mysql_fetch_object($q);
		mysql_free_result($q);
		return $r;
	}

}

/**
 * DBIP MySQLI class
 *
 * If you are using a MySQL database backend, please prefer the DBIP base class which uses the PDO mysql driver.
 * This is only meant to be used on PHP installations where PDO is disabled and the old mysqli_* interface is available.
 */
class DBIP_MySQLI extends DBIP {

	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	protected function Prepare_Import($table_name, $fields) {
		$q = mysqli_query($this->db, "select count(*) as cnt from `{$table_name}`");
		$r = mysqli_fetch_object($q);
		mysqli_free_result($q);
		if ($r->cnt) {
			throw new DBIP_Exception("table {$table_name} is not empty");
		}
		return array($table_name, $fields);
	}

	protected function Finalize_Import() {
	}

	protected function Import_Row($res, $row) {
		$vals = array();
		foreach ($row as $val) {
			$vals[] = "'" . mysqli_real_escape_string($this->db, $val) . "'";
		}
		if (!mysqli_query($this->db, "insert into `{$res[0]}` (" . implode(",", $res[1]) . ",addr_type) values (" . implode(",", $vals) . ")")) {
			throw new DBIP_Exception("database error: cannot insert row");
		}
		return true;
	}

	protected function Do_Lookup($table_name, $addr_type, $addr_start) {
		$q = mysqli_query($this->db, "select * from `{$table_name}` where addr_type = '{$addr_type}' and ip_start <= '" . mysqli_real_escape_string($this->db, $addr_start) . "' order by ip_start desc limit 1");
		$r = mysqli_fetch_object($q);
		mysqli_free_result($q);
		return $r;
	}

}