<?php
/**
 * @class  a_missionAdminView
 * @author Antigravity (dev@example.com)
 * @brief  A-Mission module admin view class
 */
class a_missionAdminView extends a_mission
{
    /**
     * @brief Initialization
     */
    function init()
    {
        // Debug
        file_put_contents('debug_a_mission.txt', "Init called\n", FILE_APPEND);
        // Set template path
        $this->setTemplatePath($this->module_path . 'tpl');
    }

    /**
     * @brief Main Configuration Page (Mission List)
     */
    function dispAMissionAdminConfig()
    {
        file_put_contents('debug_a_mission.txt', "Disp called\n", FILE_APPEND);
        // 1. Get Mission List
        $args = new stdClass();
        $args->page = Context::get('page');
        $output = executeQueryArray('a_mission.getMissionList', $args);

        Context::set('mission_list', $output->data);
        Context::set('total_count', $output->total_count);
        Context::set('total_page', $output->total_page);
        Context::set('page', $output->page);
        Context::set('page_navigation', $output->page_navigation);

        // 2. Set Template
        $this->setTemplateFile('mission_list');
    }

    /**
     * @brief Insert/Edit Mission Page
     */
    function dispAMissionAdminInsert()
    {
        $mission_srl = Context::get('mission_srl');
        if ($mission_srl) {
            $args = new stdClass();
            $args->mission_srl = $mission_srl;
            $output = executeQuery('a_mission.getMission', $args);
            if ($output->data) {
                // Decode JSON for View
                $output->data->conditions = json_decode($output->data->conditions, true);
                Context::set('mission_info', $output->data);
            }
        }

        $this->setTemplateFile('mission_insert');
    }
    /**
     * @brief Member Management Page (Ticket List)
     */
    function dispAMissionAdminMemberList()
    {
        $view_type = Context::get('view_type');
        
        // 3. Main Logic (Reverted to Standard XML with Fix)
        $args = new stdClass();
        $args->page = Context::get('page');
        $args->s_user_id = Context::get('s_user_id');
        $args->s_nick_name = Context::get('s_nick_name');
        
        $view_type = Context::get('view_type');
        
        if($view_type == 'ranking') {
            // [Ranking Mode - Decoupled Strategy]
            // 1. Get Top 100 Ticket Holders (Raw SQL to be 100% sure)
            $oDB = DB::getInstance();
            // [DEBUG] Force xe_ prefix based on user report. 
            // Automatic detection might be returning 'rx_' which doesn't match the table.
            $prefix = 'xe_'; 
            /* 
            $db_info = Context::getDBInfo();
            if(is_object($db_info)) {
                if(isset($db_info->master_db['db_prefix'])) $prefix = $db_info->master_db['db_prefix'];
                elseif(isset($db_info->db_prefix)) $prefix = $db_info->db_prefix;
            }
            */
            
            // Fetch Tickets first (No Joins)
            $query = "SELECT member_srl, ticket_count, last_update 
                      FROM {$prefix}a_mission_tickets 
                      WHERE ticket_count > 0 
                      ORDER BY ticket_count DESC, last_update ASC 
                      LIMIT 100";
            
            $result = $oDB->_query($query);
            
            $ticket_list = [];
            $member_srls = [];
            
            if($result) {
                while($row = $oDB->_fetch($result)) {
                    // [DEBUG FIX] _fetch might return an ARRAY of objects (All rows) instead of one object
                    if(is_array($row)) {
                        foreach($row as $r) {
                            if(!is_object($r)) continue;
                            $r->ticket_count = (int)$r->ticket_count;
                            $ticket_list[] = $r;
                            $member_srls[] = $r->member_srl;
                        }
                    } else {
                        // Standard single row object
                        $row->ticket_count = (int)$row->ticket_count;
                        $ticket_list[] = $row;
                        $member_srls[] = $row->member_srl;
                    }
                }
            }
            
            // 2. Fetch Member Info if we have tickets (Raw SQL for safety)
            if(count($member_srls) > 0) {
                $srls_str = implode(',', $member_srls);
                $m_query = "SELECT * FROM {$prefix}member WHERE member_srl IN ($srls_str)";
                
                $m_result = $oDB->_query($m_query);
                
                $member_map = [];
                if($m_result) {
                    while($m_row = $oDB->_fetch($m_result)) {
                         // [DEBUG FIX] Handle Array Return for Members too
                         if(is_array($m_row)) {
                             foreach($m_row as $mr) {
                                 if(!is_object($mr)) continue;
                                 $member_map[$mr->member_srl] = $mr;
                             }
                         } else {
                             $member_map[$m_row->member_srl] = $m_row;
                         }
                    }
                }
                
                // 3. Merge Member Info into Ticket List
                foreach($ticket_list as $key => $ticket) {
                    if(isset($member_map[$ticket->member_srl])) {
                        $m = $member_map[$ticket->member_srl];
                        $ticket->user_id = $m->user_id;
                        $ticket->nick_name = $m->nick_name;
                        $ticket->email_address = $m->email_address;
                    } else {
                        $ticket->user_id = 'Unknown';
                        $ticket->nick_name = 'Deleted Member';
                        $ticket->email_address = '';
                    }
                    $ticket_list[$key] = $ticket;
                }
            }
            
            // Normalize for template
            Context::set('ticket_list', $ticket_list);
            Context::set('page_navigation', null);
        } else {
            // Default: Use Core Member Query + Separate Ticket Fetch (Stability First)
            // 1. Get Members using standard Core Query
            if($args->s_user_id) $args->user_id = $args->s_user_id;
            if($args->s_nick_name) $args->nick_name = $args->s_nick_name;
            // Map other search args if needed
            
            $output = executeQueryArray('member.getMemberList', $args);
            
            $member_list = array();
            if($output->data) {
                $member_list = is_array($output->data) ? $output->data : array($output->data);
            }
            
            // 2. Get Ticket Counts for these members
            if(count($member_list) > 0) {
                $member_srls = array();
                foreach($member_list as $key => $val) {
                    $member_srls[] = $val->member_srl;
                    $val->ticket_count = 0; // Initialize
                    $val->last_update = null;
                }
                
                $t_args = new stdClass();
                $t_args->member_srls = implode(',', $member_srls);
                // We need a specific query for this: 'getTicketCountsBySrls' or raw query
                // Using simple raw query for efficiency here since it's just a lookup
                $oDB = DB::getInstance();
                $db_info = Context::getDBInfo();
                $prefix = 'xe_';
                if(is_object($db_info)) {
                    if(isset($db_info->master_db['db_prefix'])) $prefix = $db_info->master_db['db_prefix'];
                    elseif(isset($db_info->db_prefix)) $prefix = $db_info->db_prefix;
                }
                
                $query = "SELECT member_srl, ticket_count, last_update FROM {$prefix}a_mission_tickets WHERE member_srl IN ({$t_args->member_srls})";
                $result = $oDB->_query($query);
                if($result) {
                    while($row = $oDB->_fetch($result)) {
                        foreach($member_list as $key => $member) {
                            if($member->member_srl == $row->member_srl) {
                                $member->ticket_count = (int)$row->ticket_count;
                                $member->last_update = $row->last_update;
                                break;
                            }
                        }
                    }
                }
            }
            
            Context::set('ticket_list', $member_list);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);
        }

        $this->setTemplateFile('member_list');
    }
    
    /**
     * @brief Log List View
     */
    function dispAMissionAdminLogList()
    {
        $args = new stdClass();
        $args->page = Context::get('page');
        
        $output = executeQueryArray('a_mission.getTicketLogList', $args);
        
        // Normalize
        $log_list = [];
        if($output->data) {
             $log_list = is_array($output->data) ? $output->data : array($output->data);
        }
        
        Context::set('total_count', $output->total_count);
        Context::set('total_page', $output->total_page);
        Context::set('page', $output->page);
        Context::set('page_navigation', $output->page_navigation);
        Context::set('log_list', $log_list);
        
        $this->setTemplateFile('log_list');
    }
}
