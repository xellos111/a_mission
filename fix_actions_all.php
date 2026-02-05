<?php
/**
 * Fix Action Registration
 * Registers procAMissionAdminUpdateTicket and others
 */
define('__XE__', true);
require_once('../../config/config.inc.php');
$oContext = Context::getInstance();
$oContext->init();

$actions = [
    'procAMissionAdminUpdateTicket' => 'controller',
    'dispAMissionAdminLogList' => 'view'
];

echo "<h3>Fixing Actions...</h3>";

$oModuleController = getController('module');
$oDB = DB::getInstance();
$db_info = Context::getDBInfo();
$prefix = $db_info->master_db['db_prefix'];
$table_name = $prefix . 'action_forward';

foreach($actions as $act => $type) {
    echo "Checking <b>$act</b>...<br>";

    // 1. Standard Insert
    $args = new stdClass();
    $args->module_srl = 0; 
    $args->module = 'a_mission';
    $args->act = $act;
    $args->type = $type;
    $args->target_module = 'a_mission'; 
    $output = executeQuery('module.insertModuleAction', $args);
    
    // 2. Legacy Table Fix
    $check_query = "SELECT count(*) as count FROM $table_name WHERE act = '$act'";
    $result = $oDB->_query($check_query);
    if($result) {
        $row = $oDB->_fetch($result);
        if($row->count == 0) {
            $insert = "INSERT INTO $table_name (act, module, type) VALUES ('$act', 'a_mission', '$type')";
            $oDB->_query($insert);
            echo "- Legacy Insert: Done<br>";
        } else {
            echo "- Legacy: OK<br>";
        }
    }
}

// 3. Clear Cache
$oCacheHandler = CacheHandler::getInstance('object');
if($oCacheHandler->isSupport()) {
     $oCacheHandler->invalidateGroupKey('module_actions');
     echo "<hr>Cache Cleared.";
}

echo "<h3>DONE! Try 'Give Ticket' again.</h3>";
?>
