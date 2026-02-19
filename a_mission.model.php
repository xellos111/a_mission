<?php
/**
 * @class  a_missionModel
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module model class
 */
class a_missionModel extends a_mission
{
    /**
     * @brief Initialization
     */
    function init()
    {
    }

    /**
     * @brief Get active missions that match a specific trigger type
     * @param string $trigger_type
     * @return array List of mission objects
     */
    function getActiveMissionsByTrigger($trigger_type)
    {
        $oCacheHandler = CacheHandler::getInstance('object');
        $cache_key = 'a_mission:all_active_missions';
        $all_missions = false;

        if ($oCacheHandler->isSupport()) {
            $all_missions = $oCacheHandler->get($cache_key);
        }

        if ($all_missions === false) {
            $args = new stdClass();
            $args->is_active = 'Y';
            $output = executeQueryArray('a_mission.getAllActiveMissions', $args);
            if (!$output->toBool() || !$output->data) {
                $all_missions = [];
            } else {
                $all_missions = $output->data;
            }

            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $all_missions);
            }
        }

        $matched_missions = [];
        $debug_str = date('Y-m-d H:i:s') . " getActiveMissionsByTrigger($trigger_type)\n";
        
        foreach ($all_missions as $mission) {
            $has_str = (strpos($mission->conditions, $trigger_type) !== false) ? 'YES' : 'NO';
            $debug_str .= " - M: {$mission->title} ({$mission->mission_srl}) / Cond: {$mission->conditions} / Match: $has_str\n";
            
            if ($has_str === 'YES') {
                $matched_missions[] = $mission;
            }
        }
        
        file_put_contents(dirname(__FILE__) . '/debug_mission_filter.txt', $debug_str, FILE_APPEND);

        return $matched_missions;
    }

    /**
     * @brief Get user's progress for a specific mission
     */
    function getMissionProgress($mission_srl, $member_srl)
    {
        $args = new stdClass();
        $args->mission_srl = $mission_srl;
        $args->member_srl = $member_srl;
        $output = executeQuery('a_mission.getMissionProgress', $args);

        if ($output->toBool() && $output->data)
            return $output->data;
        return null;
        if ($output->toBool() && $output->data)
            return $output->data;
        return null;
    }

    /**
     * @brief Get Member Ticket Count
     */
    function getTicketCount($member_srl)
    {
        $args = new stdClass();
        $args->member_srl = $member_srl;
        $output = executeQuery('a_mission.getTicketCount', $args);
        
        if($output->data) {
             return (int)$output->data->ticket_count;
        }
        return 0;
    }
}
