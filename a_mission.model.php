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
        foreach ($all_missions as $mission) {
            // Check if triggers are present in conditions JSON or string
            // We check if the requested trigger_type is mentioned in the conditions string (simplified check)
            // or if it matches specific keys if we decoded it (but strpos is faster for pre-check).
            if (strpos($mission->conditions, $trigger_type) !== false) {
                $matched_missions[] = $mission;
            }
        }

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
    }
}
