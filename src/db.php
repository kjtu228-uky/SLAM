<?php

use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * This page provides functions for accessing the database.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('vendor/autoload.php');

// Set the default application logging level
Util::$logLevel = LogLevel::Error;

require_once('config.php');

###
###  Return a connection to the database, return false if an error occurs
###

function open_db()
{
    try {
        $db = new PDO(DB_NAME, DB_USERNAME, DB_PASSWORD);
    } catch (PDOException $e) {
        $db = false;
        $_SESSION['error_message'] = "Database error {$e->getCode()}: {$e->getMessage()}";
    }

    return $db;
}

###
###  Check if a table exists
###

function tableExists($db, $name)
{
    $sql = "select 1 from {$name}";
    $query = $db->prepare($sql);
    try {
        $ok = $query->execute() !== false;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

###
###  Create any missing database tables (only for MySQL and SQLite databases)
###

function init_db($db)
{
    $dbType = '';
    $pos = strpos(DB_NAME, ':');
    if ($pos !== false) {
        $dbType = strtolower(substr(DB_NAME, 0, $pos));
    }

    $ok = true;
    $prefix = DB_TABLENAME_PREFIX;

    if (!tableExists($db, $prefix . DataConnector\DataConnector::PLATFORM_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' (' .
            'consumer_pk int(11) NOT NULL AUTO_INCREMENT, ' .
            'name varchar(50) NOT NULL, ' .
            'consumer_key varchar(256) DEFAULT NULL, ' .
            'secret varchar(1024) DEFAULT NULL, ' .
            'platform_id varchar(255) DEFAULT NULL, ' .
            'client_id varchar(255) DEFAULT NULL, ' .
            'deployment_id varchar(255) DEFAULT NULL, ' .
            'public_key text DEFAULT NULL, ' .
            'lti_version varchar(10) DEFAULT NULL, ' .
            'signature_method varchar(15) DEFAULT NULL, ' .
            'consumer_name varchar(255) DEFAULT NULL, ' .
            'consumer_version varchar(255) DEFAULT NULL, ' .
            'consumer_guid varchar(1024) DEFAULT NULL, ' .
            'profile text DEFAULT NULL, ' .
            'tool_proxy text DEFAULT NULL, ' .
            'settings text DEFAULT NULL, ' .
            'protected tinyint(1) NOT NULL, ' .
            'enabled tinyint(1) NOT NULL, ' .
            'enable_from datetime DEFAULT NULL, ' .
            'enable_until datetime DEFAULT NULL, ' .
            'last_access date DEFAULT NULL, ' .
            'created datetime NOT NULL, ' .
            'updated datetime NOT NULL, ' .
            'PRIMARY KEY (consumer_pk)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' ' .
                "ADD UNIQUE INDEX {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_' .
                'consumer_key_UNIQUE (consumer_key ASC)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' ' .
                "ADD UNIQUE INDEX {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_' .
                'platform_UNIQUE (platform_id ASC, client_id ASC, deployment_id ASC)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if ($ok && !tableExists($db, $prefix . DataConnector\DataConnector::NONCE_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . ' (' .
            'consumer_pk int(11) NOT NULL, ' .
            'value varchar(50) NOT NULL, ' .
            'expires datetime NOT NULL, ' .
            'PRIMARY KEY (consumer_pk, value)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::NONCE_TABLE_NAME . '_' .
                DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if ($ok && !tableExists($db, $prefix . DataConnector\DataConnector::ACCESS_TOKEN_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::ACCESS_TOKEN_TABLE_NAME . ' (' .
            'consumer_pk int(11) NOT NULL, ' .
            'scopes text NOT NULL, ' .
            'token varchar(2000) NOT NULL, ' .
            'expires datetime NOT NULL, ' .
            'created datetime NOT NULL, ' .
            'updated datetime NOT NULL, ' .
            'PRIMARY KEY (consumer_pk)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::ACCESS_TOKEN_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::ACCESS_TOKEN_TABLE_NAME . '_' .
                DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if (!tableExists($db, $prefix . DataConnector\DataConnector::CONTEXT_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' (' .
            'context_pk int(11) NOT NULL AUTO_INCREMENT, ' .
            'consumer_pk int(11) NOT NULL, ' .
            'lti_context_id varchar(255) NOT NULL, ' .
            'title varchar(255) DEFAULT NULL, ' .
            'type varchar(50) DEFAULT NULL, ' .
            'settings text DEFAULT NULL, ' .
            'created datetime NOT NULL, ' .
            'updated datetime NOT NULL, ' .
            'PRIMARY KEY (context_pk)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_' .
                DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' ' .
                "ADD INDEX {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_' .
                'consumer_id_IDX (consumer_pk ASC)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if ($ok && !tableExists($db, $prefix . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (' .
            'resource_link_pk int(11) AUTO_INCREMENT, ' .
            'context_pk int(11) DEFAULT NULL, ' .
            'consumer_pk int(11) DEFAULT NULL, ' .
            'title varchar(255) DEFAULT NULL, ' .
            'lti_resource_link_id varchar(255) NOT NULL, ' .
            'settings text, ' .
            'primary_resource_link_pk int(11) DEFAULT NULL, ' .
            'share_approved tinyint(1) DEFAULT NULL, ' .
            'created datetime NOT NULL, ' .
            'updated datetime NOT NULL, ' .
            'PRIMARY KEY (resource_link_pk)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
                DataConnector\DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
                DataConnector\DataConnector::CONTEXT_TABLE_NAME . '_FK1 FOREIGN KEY (context_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::CONTEXT_TABLE_NAME . ' (context_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
                DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (primary_resource_link_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
                "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
                'consumer_pk_IDX (consumer_pk ASC)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
                "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
                'context_pk_IDX (context_pk ASC)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if ($ok && !tableExists($db, $prefix . DataConnector\DataConnector::USER_RESULT_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' (' .
            'user_result_pk int(11) AUTO_INCREMENT, ' .
            'resource_link_pk int(11) NOT NULL, ' .
            'lti_user_id varchar(255) NOT NULL, ' .
            'lti_result_sourcedid varchar(1024) NOT NULL, ' .
            'created datetime NOT NULL, ' .
            'updated datetime NOT NULL, ' .
            'PRIMARY KEY (user_result_pk)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . '_' .
                DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . ' ' .
                "ADD INDEX {$prefix}" . DataConnector\DataConnector::USER_RESULT_TABLE_NAME . '_' .
                'resource_link_pk_IDX (resource_link_pk ASC)';
            $ok = $db->exec($sql) !== false;
        }
    }

    if ($ok && !tableExists($db, $prefix . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME)) {
        $sql = "CREATE TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' (' .
            'share_key_id varchar(32) NOT NULL, ' .
            'resource_link_pk int(11) NOT NULL, ' .
            'auto_approve tinyint(1) NOT NULL, ' .
            'expires datetime NOT NULL, ' .
            'PRIMARY KEY (share_key_id)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $ok = $db->exec($sql) !== false;
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
                "ADD CONSTRAINT {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
                DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
                "REFERENCES {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
            $ok = $db->exec($sql) !== false;
        }
        if ($ok) {
            $sql = "ALTER TABLE {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
                "ADD INDEX {$prefix}" . DataConnector\DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
                'resource_link_pk_IDX (resource_link_pk ASC)';
            $ok = $db->exec($sql) !== false;
        }
    }

	if ($ok && !tableExists($db, "{$prefix}log")) {
		$sql = "CREATE TABLE {$prefix}log (" .
            'user_id varchar(250) NOT NULL, ' .
            'action tinyint(3) unsigned NOT NULL, ' .
            'tool_id int(11) NOT NULL, ' .
            'changed_at timestamp NOT NULL DEFAULT current_timestamp(), ' .
            'result int(11) NOT NULL, ' .
            'course_number int(11) DEFAULT NULL ' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
		$ok = $db->exec($sql) !== false;
	}
	
	if ($ok && !tableExists($db, "{$prefix}tools")) {
		// id, consumer_pk, canvas_id, visible, dependency, config, user_notice, support_info
		$sql = "CREATE TABLE {$prefix}tools (" .
			'id int(11) NOT NULL AUTO_INCREMENT, ' .
			'consumer_pk int(11) NOT NULL, ' .
			'canvas_id varchar(32), ' .
			'dependency int(11) DEFAULT NULL, ' .
			'visible tinyint(1) NOT NULL DEFAULT 1, ' .
			'config longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)), ' .
			'user_notice varchar(1000) DEFAULT NULL, ' .
			'support_info varchar(1000) DEFAULT NULL, ' .
			'PRIMARY KEY (id)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8';
		$ok = $db->exec($sql) !== false;
	}
	
    return $ok;
}

/**
 * Get the list of tools that have been configured for this platform.
 * Optionally, only retrieve tools that are marked as visible.
 *
 * @return array.
 */
function getToolConfigs($platform, $onlyVisible = false) {
	$db = open_db();
	$platformId = $platform->getRecordId();
	$sql = "SELECT * FROM " . DB_TABLENAME_PREFIX . "tools WHERE consumer_pk = :platform_id";
	$sql .= $onlyVisible?" AND visible >= 0":"";
	$sql .= " ORDER BY lower(config)";
Util::logError($sql);
	$statement = $db->prepare($sql);
	$statement->bindParam("platform_id", $platformId, PDO::PARAM_INT); // PDO::PARAM_STR if replacing string
	$statement->execute();
	$tools = $statement->fetchAll(PDO::FETCH_ASSOC);
	$db = null;
	return $tools;
}

/**
 * Get the configuration for the specified LTI registration.
 * If it doesn't exist in the database, add it.
 *
 * @return array.
 */
function getToolConfig($platform, $registration, $configuredTools = null) {
	if (!isset($registration['id'])) return false;
	$db = false;
	if (isset($configuredTools) && is_array($configuredTools) && count($configuredTools)>0) {
		foreach ($configuredTools as $configuredTool) {
			if ($registration['id'] == $configuredTool['canvas_id']) {
				$config = $configuredTool;
				break;
			}
		}
	} else {
		$db = open_db();
		$platformId = $platform->getRecordId();
		$sql = "SELECT * FROM " . DB_TABLENAME_PREFIX . "tools WHERE consumer_pk = :platform_id AND canvas_id = :canvas_id";
		$statement = $db->prepare($sql);
		$statement->bindParam("platform_id", $platformId, PDO::PARAM_INT); // PDO::PARAM_STR if replacing string
		$statement->bindParam("canvas_id", $registration['id'], PDO::PARAM_INT); // PDO::PARAM_STR if replacing string
		$statement->execute();
		$config = $statement->fetch(PDO::FETCH_ASSOC);
	}
	if ($config) {
		$registration['canvas_id'] = $registration['id'];
		$registration['id'] = $config['id'];
		$registration['dependency'] = $config['dependency'];
		$registration['visible'] = $config['visible'];
		$registration['config'] = json_decode($config['config'], true);
		$registration['user_notice'] = $config['user_notice'];
		$registration['support_info'] = $config['support_info'];
	} else {
		if (!$db) $db = open_db();
		$sql = "INSERT INTO " . DB_TABLENAME_PREFIX . "tools (consumer_pk, canvas_id, visible) VALUES ";
		$sql .= "(:platform_id, :canvas_id, 0)";
		$statement = $db->prepare($sql);
		$statement->bindParam("platform_id", $platformId, PDO::PARAM_INT); // PDO::PARAM_STR if replacing string
		$statement->bindParam("canvas_id", $registration['id'], PDO::PARAM_STR); // PDO::PARAM_STR if replacing string
		$statement->execute();
		$registration['canvas_id'] = $registration['id'];
		try {
			$registration['id'] = $db->lastInsertId();
		} catch (Exception $e) {
			return false;
		}
	}
	$db = null;
	return $registration;
}

/**
 * Update the configuration for the specified LTI registration.
 *
 * @return boolean.
 */
function setToolConfig($platform, $toolConfig) {
	if (!isToolAdmin($platform)) return false;
	if (!isset($platform)) return false;
	if (!isset($toolConfig) || !is_array($toolConfig) || !isset($toolConfig['id'])) return false;
	$platformId = $platform->getRecordId();
	$updatedFields = array();
	if (isset($toolConfig['visible']) && $toolConfig['visible']) $updatedFields[] = "visible = true";
	else $updatedFields[] = "visible = false";
	
	$db = open_db();
	$sql = "UPDATE ". DB_TABLENAME_PREFIX . "tools SET ";
	$sql .= implode(",", $updatedFields);
	$sql .= " WHERE id = :tool_id AND consumer_pk = :platform_id";
	$statement = $db->prepare($sql);
	$statement->bindParam("tool_id", $toolConfig['id'], PDO::PARAM_INT);
	$statement->bindParam("platform_id", $platformId, PDO::PARAM_INT);
	$statement->execute();
	return $statement->rowCount();
	if ($statement->rowCount() > 0) {
		$db = null;
		return true;
	}
	$db = null;
	return false;
}
?>
