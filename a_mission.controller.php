<?php
/**
 * @class  a_missionController
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module controller class
 */
class a_missionController extends a_mission
{
    /**
     * @brief Initialization
     */
    function init()
    {
    }

    /**
     * @brief Trigger: After Login
     */
    function triggerAfterLogin($obj)
    {
        if (!$obj->member_srl)
            return new BaseObject();
        return $this->checkMission('login', $obj->member_srl);
    }

    /**
     * @brief Trigger: After Document Insert
     */
    function triggerAfterInsertDocument($obj)
    {
        if (!$obj->member_srl)
            return new BaseObject();
        $extra_vars = ['module_srl' => $obj->module_srl];
        return $this->checkMission('write_doc', $obj->member_srl, $extra_vars);
    }

    /**
     * @brief Trigger: After Comment Insert
     */
    function triggerAfterInsertComment($obj)
    {
        if (!$obj->member_srl)
            return new BaseObject();
        $extra_vars = ['module_srl' => $obj->module_srl];
        return $this->checkMission('write_comment', $obj->member_srl, $extra_vars);
    }

    /**
     * @brief Trigger: After Vote
     */
    function triggerDocumentVoted($obj)
    {
        // $obj contains: document_srl, member_srl (voter), point (1 or -1)
        if (!$obj->member_srl)
            return new BaseObject();

        // Only count up-votes (recommendations)
        if ($obj->point > 0) {
            return $this->checkMission('vote_up', $obj->member_srl);
        }
        return new BaseObject();
    }

    /**
     * @brief Core Mission Check Logic (Multi-Condition Handler)
     * @param string $trigger_type Trigger identifier (login, write_doc, etc.)
     * @param int $member_srl User ID
     * @param array $extra_condition Context variables
     */
    function checkMission($trigger_type, $member_srl, $extra_condition = [])
    {
        $oModel = getModel('a_mission');

        // 1. Get Active Missions that include this trigger_type (Text Search / Cache)
        // Implementation Note: Model should search WHERE mission_type LIKE %trigger_type%
        $mission_list = $oModel->getActiveMissionsByTrigger($trigger_type);
        if (!$mission_list)
            return new BaseObject();

        $today = date('Ymd');
        $now = date('YmdHis');

        foreach ($mission_list as $mission) {
            // Parse Conditions (JSON)
            // Format: {"login": {"target":1}, "write_doc": {"target":3, "module_srl":[1,2]} }
            // OR Simple key-value: {"login": 1, "write_doc": 3}
            $conditions = json_decode($mission->conditions, true);
            if (!$conditions)
                continue;

            // Check if this mission actually cares about the current trigger
            if (!isset($conditions[$trigger_type]))
                continue;

            // 2. Load Progress
            $progress = $oModel->getMissionProgress($mission->mission_srl, $member_srl);
            $is_new = false;
            if (!$progress) {
                $progress = new stdClass();
                $progress->mission_srl = $mission->mission_srl;
                $progress->member_srl = $member_srl;
                $progress->progress_data = '{}';
                $progress->is_completed = 'N';
                $progress->last_updated = '';
                $is_new = true;
            }

            // 3. Reset Check (Daily)
            if ($mission->reset_cycle == 'daily' && substr($progress->last_updated, 0, 8) != $today) {
                $progress->progress_data = '{}';
                $progress->is_completed = 'N';
            }

            // 4. If Completed, Skip
            if ($progress->is_completed == 'Y')
                continue;

            // Decode Current Progress
            $current_data = json_decode($progress->progress_data, true);
            if (!$current_data)
                $current_data = [];

            // 5. Update Specific Condition
            $target_val = $conditions[$trigger_type];
            // Handle simple integer target (Legacy/Simple mode)
            if (is_numeric($target_val))
                $target_req = $target_val;
            else
                $target_req = $target_val['count'] ?? 1;

            // Check Extra Constraints (Board ID, etc.)
            if (is_array($target_val) && isset($extra_condition['module_srl']) && isset($target_val['module_srl'])) {
                if (!in_array($extra_condition['module_srl'], $target_val['module_srl']))
                    continue;
            }

            // Increment Count
            if (!isset($current_data[$trigger_type]))
                $current_data[$trigger_type] = 0;

            // Limit to target (optional, but looks better in UI)
            if ($current_data[$trigger_type] < $target_req) {
                $current_data[$trigger_type]++;
                $progress->last_updated = $now;
            }

            // 6. Check ALL Conditions for Completion
            $all_cleared = true;
            foreach ($conditions as $c_type => $c_val) {
                $req_count = is_numeric($c_val) ? $c_val : ($c_val['count'] ?? 1);
                $cur_count = $current_data[$c_type] ?? 0;

                if ($cur_count < $req_count) {
                    $all_cleared = false;
                    break;
                }
            }

            // Save JSON
            $progress->progress_data = json_encode($current_data);

            if ($all_cleared) {
                $progress->is_completed = 'Y';
                $progress->completed_date = $now;
                $this->addTicket($member_srl, $mission->reward_tickets, "Mission Reward: " . $mission->title);
            }

            // Save to DB
            $this->_saveProgress($progress, $is_new);
        }

        return new BaseObject();
    }

    /**
     * @brief Condition Check Helper
     */
    function _checkConditions($mission, $extra)
    {
        if (!$mission->conditions)
            return true;

        // Example: Check module_srl (Specific Board)
        // Conditions are stored as serialized array or JSON
        $conditions = is_array($mission->conditions) ? $mission->conditions : unserialize($mission->conditions);
        if (!$conditions)
            return true;

        if (isset($conditions['module_srl']) && isset($extra['module_srl'])) {
            if (!in_array($extra['module_srl'], $conditions['module_srl']))
                return false;
        }

        return true;
    }

    /**
     * @brief Save Progress Helper
     */
    function _saveProgress($progress, $is_new)
    {
        $oModel = getModel('a_mission');
        // If not new, we should rely on progress_srl which should be in the object if loaded from DB
        if ($is_new) {
            $progress->progress_srl = getNextSequence();
            executeQuery('a_mission.insertProgress', $progress);
        } else {
            executeQuery('a_mission.updateProgress', $progress);
        }
    }

    /**
     * @brief Play Game (Consume Tickets)
     */
    function playGame($game_type, $bet_amount)
    {
        if (!Context::get('is_logged'))
            return new BaseObject(-1, 'msg_not_logged');

        $logged_info = Context::get('logged_info');
        $member_srl = $logged_info->member_srl;

        // 1. Check Balance
        $args = new stdClass();
        $args->member_srl = $member_srl;
        $output = executeQuery('a_mission.getTicketBalance', $args);
        if (!$output->toBool())
            return $output;

        $current_tickets = $output->data ? $output->data->ticket_count : 0;
        if ($current_tickets < $bet_amount)
            return new BaseObject(-1, 'msg_not_enough_tickets');

        // 2. Consume Tickets
        $this->addTicket($member_srl, -$bet_amount, "Game Play: " . $game_type);

        // 3. Game Logic (Placeholder)
        // Here you would implement the actual game RNG and logic.
        // For now, let's say it's a 50/50 chance to double up.
        $is_win = rand(0, 1);
        $win_amount = 0;

        if ($is_win) {
            $win_amount = $bet_amount * 2;
            $this->addTicket($member_srl, $win_amount, "Game Win: " . $game_type);
        }

        // 4. Log Result
        $log_args = new stdClass();
        $log_args->game_log_srl = getNextSequence();
        $log_args->member_srl = $member_srl;
        $log_args->spent_tickets = $bet_amount;
        $log_args->won_points = $win_amount;
        $log_args->regdate = date('YmdHis');
        executeQuery('a_mission.insertGameLog', $log_args);

        $result = new BaseObject();
        $result->add('result', $is_win ? 'win' : 'lose');
        $result->add('win_amount', $win_amount);
        $result->add('current_ticket', $current_tickets - $bet_amount + $win_amount);

        return $result;
    }

    /**
     * @brief Add Ticket to User (Positive or Negative)
     */
    function addTicket($member_srl, $count, $message = '')
    {
        if ($count == 0)
            return;

        // DB Transaction or simple update
        // Check if wallet exists
        $args = new stdClass();
        $args->member_srl = $member_srl;
        $output = executeQuery('a_mission.getTicketBalance', $args);
        
        $current_tickets = 0;

        if ($output->data) {
            $current_tickets = $output->data->ticket_count;
            $args->ticket_count = $current_tickets + $count;
            if ($args->ticket_count < 0)
                $args->ticket_count = 0; // Prevent negative balance
            executeQuery('a_mission.updateTicketBalance', $args);
        } else {
            // If spending but no wallet, invalid state usually, but handle gracefully
            if ($count < 0)
                return;
            $args->ticket_count = $count;
            executeQuery('a_mission.insertTicketWallet', $args);
        }
        
        // New Balance (for logging)
        $balance_after = $args->ticket_count;

        // Log ticket history
        $log_args = new stdClass();
        $log_args->log_srl = getNextSequence();
        $log_args->member_srl = $member_srl;
        $log_args->amount = $count;
        $log_args->balance_after = $balance_after;
        $log_args->description = $message ? $message : ($count > 0 ? 'Granted' : 'Consumed');
        $log_args->regdate = date('YmdHis');
        $log_args->ipaddress = $_SERVER['REMOTE_ADDR'];
        
        executeQuery('a_mission.insertTicketLog', $log_args);
    }
}
