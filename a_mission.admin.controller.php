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

        // 0. Explicitly handle is_active (default to Y if missing, but radio should send it)
        $args->is_active = (isset($args->is_active) && $args->is_active == 'N') ? 'N' : 'Y';

        // 1. Format Conditions to JSON
        // Expected input: condition_type[], condition_count[] arrays from form
        $conditions = [];
        if ($args->condition_type && is_array($args->condition_type)) {
            foreach ($args->condition_type as $key => $type) {
                if (!$type)
                    continue;
                $count = $args->condition_count[$key] ?? 1;
                $target_str = trim($args->condition_target[$key] ?? '');
                
                // Advanced Schema: always object with type
                $cond_data = [
                    'type' => $type,
                    'count' => (int)$count
                ];
                
                if ($target_str) {
                    $cond_data['target_mid'] = array_map('trim', explode(',', $target_str));
                }
                
                // Use unique key to allow multiple conditions of same type
                $unique_key = $type . '_' . $key; 
                $conditions[$unique_key] = $cond_data;
            }
        }
        $args->conditions = json_encode($conditions);

        // 2. Generate Mission Type String for Search (e.g., "login,write_doc")
        // Extract unique types from the conditions
        $types = [];
        foreach($conditions as $c) {
            $types[] = is_array($c) && isset($c['type']) ? $c['type'] : 'unknown';
        }
        $args->mission_type = implode(',', array_unique($types));

        // 3. Prepare Object for DB (Clean)
        $obj = new stdClass();
        $obj->mission_srl = $args->mission_srl;
        $obj->title = $args->title;
        $obj->description = $args->description ?? '';
        $obj->mission_type = $args->mission_type;
        $obj->conditions = $args->conditions;
        $obj->reward_tickets = (int)($args->reward_tickets ?? 1);
        $obj->reset_cycle = $args->reset_cycle ?? 'daily';
        
        // Strict check for is_active
        $raw_active = Context::get('is_active');
        $obj->is_active = ($raw_active === 'N') ? 'N' : 'Y';
        
        $obj->start_date = $args->start_date ?? null;
        $obj->end_date = $args->end_date ?? null;

        // 4. Insert or Update
        if ($obj->mission_srl) {
            $output = executeQuery('a_mission.updateMission', $obj);
        } else {
            $obj->mission_srl = getNextSequence();
            $output = executeQuery('a_mission.insertMission', $obj);
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
