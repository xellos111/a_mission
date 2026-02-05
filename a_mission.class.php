<?php
/**
 * @class  a_mission
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module high class
 */
class a_mission extends ModuleObject
{
    /**
     * @brief Module installation
     * Create tables if they don't exist
     */
    function moduleInstall()
    {
        // 1. Register Triggers
        $this->_registerTriggers();
        
        // 2. Force Register Actions (for legacy/compatibility)
        $this->_manualActionRegistration();
        
        return new BaseObject();
    }

    /**
     * @brief check update
     */
    /**
     * @brief check update
     */
    function checkUpdate()
    {
        $oModuleModel = getModel('module');

        // 1. Check if the main Admin Action is registered
        // Use executeQuery instead of getModuleAction which might not exist in old versions
        $args = new stdClass();
        $args->act = 'dispAMissionAdminConfig';
        $output = executeQuery('module.getModuleAction', $args);
        if(!$output->data) return true;

        // 2. Check Triggers
        if(!$oModuleModel->getTrigger('member.doLogin', 'a_mission', 'controller', 'triggerAfterLogin', 'after')) return true;
        if(!$oModuleModel->getTrigger('document.insertDocument', 'a_mission', 'controller', 'triggerAfterInsertDocument', 'after')) return true;
        if(!$oModuleModel->getTrigger('comment.insertComment', 'a_mission', 'controller', 'triggerAfterInsertComment', 'after')) return true;
        if(!$oModuleModel->getTrigger('document.updateVotedCount', 'a_mission', 'controller', 'triggerDocumentVoted', 'after')) return true;

        return false;
    }

    /**
     * @brief update module
     */
    function moduleUpdate()
    {
        // 1. Register Triggers
        $this->_registerTriggers();
        
        // 2. Force Register Actions
        $this->_manualActionRegistration();

        return new BaseObject(0, 'success_updated');
    }
    
    /**
     * @brief Register Triggers (Helper)
     */
    private function _registerTriggers()
    {
        $oModuleModel = getModel('module');
        $oModuleController = getController('module');
        
        $triggers = [
            ['member.doLogin', 'triggerAfterLogin'],
            ['document.insertDocument', 'triggerAfterInsertDocument'],
            ['comment.insertComment', 'triggerAfterInsertComment'],
            ['document.updateVotedCount', 'triggerDocumentVoted']
        ];
        
        foreach($triggers as $trigger) {
            if(!$oModuleModel->getTrigger($trigger[0], 'a_mission', 'controller', $trigger[1], 'after')) {
                $oModuleController->insertTrigger($trigger[0], 'a_mission', 'controller', $trigger[1], 'after');
            }
        }
    }

    /**
     * @brief Manually Register Actions if missing (Fix for Legacy/Prefix issues)
     */
    private function _manualActionRegistration()
    {
        // List of essential actions
        $actions = [
            'dispAMissionAdminConfig' => 'view',
            'dispAMissionAdminInsert' => 'view',
            'dispAMissionDashboard' => 'view',
            'dispAMissionGame' => 'view',
            'procAMissionAdminInsertMission' => 'controller',
            'procAMissionAdminDeleteMission' => 'controller',
            'procAMissionPlayGame' => 'controller',
            'dispAMissionAdminMemberList' => 'view',
            'dispAMissionAdminStats' => 'view',
            'dispAMissionAdminLogList' => 'view', // Added as per instruction
            'procAMissionAdminUpdateTicket' => 'controller'
        ];
        
        // 1. Get DB Prefix securely
        $prefix = 'rx_'; // Default fallback
        if(class_exists('Context') && method_exists('Context', 'getDBInfo')) {
            $db_info = Context::getDBInfo();
            if(is_object($db_info)) {
                 if(isset($db_info->master_db['db_prefix'])) $prefix = $db_info->master_db['db_prefix'];
                 elseif(isset($db_info->db_prefix)) $prefix = $db_info->db_prefix;
            }
        }

        $db = DB::getInstance();
        
        foreach($actions as $act => $type) {
            // 2. Check if already registered (Standard)
            // Use module_srl=0 for global/admin actions check
            $args = new stdClass();
            $args->act = $act;
            $output = executeQuery('module.getModuleAction', $args);
            if($output->data && $output->data->module == 'a_mission') continue;
            
            // 3. Try Standard Insert
            $args = new stdClass();
            $args->module_srl = 0; // CRITICAL: Standalone/Admin actions use 0
            $args->module = 'a_mission';
            $args->act = $act;
            $args->type = $type;
            $args->target_module = 'a_mission'; 
            executeQuery('module.insertModuleAction', $args);
            
            // 4. Verify if Standard Insert worked
            $args_check = new stdClass();
            $args_check->act = $act;
            $output = executeQuery('module.getModuleAction', $args_check);
            if($output->data && $output->data->module == 'a_mission') continue;
            
            // 5. Fallback: Legacy Table (action_forward)
            // Try known legacy table variants explicitly
            $candidates = [
                $prefix . 'action_forward',
                'xe_action_forward', 
                'rx_action_forward'
            ];
            $candidates = array_unique($candidates);

            foreach($candidates as $table_name) {
                // Check if table exists
                $check_table = $db->_query("SHOW TABLES LIKE '$table_name'");
                if($check_table && $db->_fetch($check_table)) {
                     // Table exists, check if action exists
                    $check_query = "SELECT count(*) as count FROM $table_name WHERE act = '$act'";
                    $result = $db->_query($check_query);
                    if($result) {
                        $row = $db->_fetch($result);
                        if($row->count == 0) {
                            $insert_query = "INSERT INTO $table_name (act, module, type) VALUES ('$act', 'a_mission', '$type')";
                            $db->_query($insert_query);
                        }
                    }
                    // If we found the table and processed it, stop looking for other tables
                    break;
                }
            }
        }
        
        // 6. Clear Cache
        $oCacheHandler = CacheHandler::getInstance('object');
        if($oCacheHandler->isSupport()) {
             $oCacheHandler->invalidateGroupKey('module_actions');
        }
    }

    /**
     * @brief re-generate the cache file
     */
    function recompileCache()
    {
    }
}
