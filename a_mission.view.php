<?php
/**
 * @class  a_missionView
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module view class
 */
class a_missionView extends a_mission
{
    /**
     * @brief Initialization
     */
    function init()
    {
        $this->setTemplatePath($this->module_path . 'tpl');
    }

    /**
     * @brief User Dashboard (Mission List & Status)
     */
    function dispAMissionDashboard()
    {
        // 1. Check Login
        if (!Context::get('is_logged'))
            return new BaseObject(-1, 'msg_login_required');
        $obj = Context::get('logged_info');
        $member_srl = $obj->member_srl;

        $oModel = getModel('a_mission');

        // 2. Get All Active Missions
        $args = new stdClass();
        $args->is_active = 'Y';
        $output = executeQueryArray('a_mission.getAllActiveMissions', $args);
        $mission_list = $output->data;
        if (!$mission_list)
            $mission_list = [];

        // 3. Get User Progress & Merge
        foreach ($mission_list as $key => $mission) {
            // Parse Conditions
            $mission->conditions = json_decode($mission->conditions, true);

            // Get Progress
            $progress = $oModel->getMissionProgress($mission->mission_srl, $member_srl);
            if ($progress) {
                $mission->progress = $progress;
                $mission->current_data = json_decode($progress->progress_data, true);
            } else {
                $mission->progress = null;
                $mission->current_data = [];
            }

            $mission_list[$key] = $mission; // Save back
        }

        // 4. Get Ticket Balance
        $ticket_obj = executeQuery('a_mission.getTicketBalance', (object) ['member_srl' => $member_srl]);
        $ticket_count = $ticket_obj->data ? $ticket_obj->data->ticket_count : 0;

        Context::set('mission_list', $mission_list);
        Context::set('my_ticket_count', $ticket_count);

        $this->setTemplateFile('dashboard');
    }

    /**
     * @brief Game Screen
     */
    function dispAMissionGame()
    {
        // 1. Check Login
        if (!Context::get('is_logged'))
            return new BaseObject(-1, 'msg_login_required');

        // 2. Load Ticket Balance (for display)
        $obj = Context::get('logged_info');
        $ticket_obj = executeQuery('a_mission.getTicketBalance', (object) ['member_srl' => $obj->member_srl]);
        $ticket_count = $ticket_obj->data ? $ticket_obj->data->ticket_count : 0;

        Context::set('my_ticket_count', $ticket_count);

        $this->setTemplateFile('game_view');
    }
}
