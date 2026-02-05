<?php
/**
 * Fix Log Access Permission
 * Force registers the dispAMissionAdminLogList action
 */
define('__XE__', true);
require_once('../../config/config.inc.php');
$oContext = Context::getInstance();
$oContext->init();

$act = 'dispAMissionAdminLogList';
echo "Registering $act...<br>";

// 1. Module Controller Insert
$oModuleController = getController('module');
$args = new stdClass();
$args->module_srl = 0; 
$args->module = 'a_mission';
$args->act = $act;
$args->type = 'view';
$args->target_module = 'a_mission'; 
$output = executeQuery('module.insertModuleAction', $args);

if($output->toBool()) {
    echo "Standard Insert: Success<br>";
} else {
    echo "Standard Insert: " . $output->getMessage() . "<br>";
}

// 2. Legacy/Prefix Fix (Just in case)
$db_info = Context::getDBInfo();
$prefix = $db_info->master_db['db_prefix'];
$table_name = $prefix . 'action_forward';

$oDB = DB::getInstance();
// Check if exists
$query = "SELECT count(*) as count FROM $table_name WHERE act = '$act'";
$result = $oDB->_query($query);
if($result) {
    $row = $oDB->_fetch($result);
    if($row->count == 0) {
        $insert = "INSERT INTO $table_name (act, module, type) VALUES ('$act', 'a_mission', 'view')";
        $oDB->_query($insert);
        echo "Legacy Insert: Success<br>";
    } else {
        echo "Legacy Insert: Already Exists<br>";
    }
}

// 3. Clear Cache
$oCacheHandler = CacheHandler::getInstance('object');
if($oCacheHandler->isSupport()) {
     $oCacheHandler->invalidateGroupKey('module_actions');
     echo "Cache Cleared.<hr>";
}

echo "<h3>DONE! Try clicking this: <a href='/index.php?module=admin&act=$act'>[Go to Log List]</a></h3>";
?>
