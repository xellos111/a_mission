<?php
/**
 * @class  a_missionAdminController
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module admin controller class
 */
class a_missionAdminController extends a_mission
{
    /**
     * @brief Initialization
     */
    function init()
    {
    }

    /**
     * @brief Insert/Update Mission
     */
    function procAMissionAdminInsertMission()
    {
        $args = Context::getRequestVars();

        // 1. Format Conditions to JSON
        // Expected input: condition_type[], condition_count[] arrays from form
        $conditions = [];
        if ($args->condition_type && is_array($args->condition_type)) {
            foreach ($args->condition_type as $key => $type) {
                if (!$type)
                    continue;
                $count = $args->condition_count[$key] ?? 1;
                $target_str = trim($args->condition_target[$key] ?? '');
                
                if ($target_str) {
                    // Advanced Condition: {"count":1, "target_mid":["free"]}
                    $targets = array_map('trim', explode(',', $target_str));
                    $conditions[$type] = [
                        'count' => (int)$count,
                        'target_mid' => $targets
                    ];
                } else {
                    // Simple Condition: 1
                    $conditions[$type] = (int) $count;
                }
            }
        }
        $args->conditions = json_encode($conditions);

        // 2. Generate Mission Type String for Search (e.g., "login,write_doc")
        $args->mission_type = implode(',', array_keys($conditions));

        // 3. Insert or Update
        if ($args->mission_srl) {
            $output = executeQuery('a_mission.updateMission', $args);
        } else {
            $args->mission_srl = getNextSequence();
            $output = executeQuery('a_mission.insertMission', $args);
        }

        if (!$output->toBool())
            return $output;

        // 4. Clear Cache (Important for optimization)
        // 4. Clear Cache
        $oCacheHandler = CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $oCacheHandler->delete('a_mission:all_active_missions');
        }

        $this->setMessage('success_saved');
        if (!in_array(Context::getRequestMethod(), ['XMLRPC', 'JSON'])) {
            $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getUrl('act', 'dispAMissionAdminConfig', 'mission_srl', '');
            $this->setRedirectUrl($returnUrl);
        }
    }

    /**
     * @brief Delete Mission
     */
    function procAMissionAdminDeleteMission()
    {
        $mission_srl = Context::get('mission_srl');
        if (!$mission_srl)
            return new BaseObject(-1, 'msg_invalid_request');

        $args = new stdClass();
        $args->mission_srl = $mission_srl;
        $output = executeQuery('a_mission.deleteMission', $args);
        if (!$output->toBool())
            return $output;

        // Clear Cache
        $oCacheHandler = CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $oCacheHandler->delete('a_mission:all_active_missions');
        }

        $this->setMessage('success_deleted');
        $this->setRedirectUrl(getUrl('act', 'dispAMissionAdminConfig', 'mission_srl', ''));
    }
    /**
     * @brief Manual Ticket Update
     */
    function procAMissionAdminUpdateTicket()
    {
        $member_srl = Context::get('target_member_srl');
        $amount = (int)Context::get('amount');
        $mode = Context::get('update_mode'); // 'add' or 'set' (Only add supported via addTicket for now, can expand)
        
        if(!$member_srl) return new BaseObject(-1, 'msg_invalid_request');
        if($amount == 0) return new BaseObject(-1, 'msg_invalid_request');
        
        if($mode == 'minus') {
            $amount = $amount * -1;
        }
        
        // Use Main Controller to ensure consistency
        $oController = getController('a_mission');
        
        // Check if member exists? (addTicket handles it)
        $message = ($mode == 'add') ? 'Admin Grant' : 'Admin Revoke';
        $oController->addTicket($member_srl, $amount, $message);
        
        $this->setMessage('success_updated');
        
        if(!in_array(Context::getRequestMethod(), ['XMLRPC', 'JSON'])) {
            $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getUrl('act', 'dispAMissionAdminMemberList');
            $this->setRedirectUrl($returnUrl);
        }
    }
}
