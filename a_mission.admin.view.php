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
}
