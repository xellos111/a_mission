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
        // Triggers will be registered here
        return new BaseObject();
    }

    /**
     * @brief check update
     */
    function checkUpdate()
    {
        $oModuleModel = getModel('module');

        // Check Triggers
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
        $oModuleModel = getModel('module');
        $oModuleController = getController('module');

        // Register Triggers
        if(!$oModuleModel->getTrigger('member.doLogin', 'a_mission', 'controller', 'triggerAfterLogin', 'after'))
            $oModuleController->insertTrigger('member.doLogin', 'a_mission', 'controller', 'triggerAfterLogin', 'after');

        if(!$oModuleModel->getTrigger('document.insertDocument', 'a_mission', 'controller', 'triggerAfterInsertDocument', 'after'))
            $oModuleController->insertTrigger('document.insertDocument', 'a_mission', 'controller', 'triggerAfterInsertDocument', 'after');

        if(!$oModuleModel->getTrigger('comment.insertComment', 'a_mission', 'controller', 'triggerAfterInsertComment', 'after'))
            $oModuleController->insertTrigger('comment.insertComment', 'a_mission', 'controller', 'triggerAfterInsertComment', 'after');

        if(!$oModuleModel->getTrigger('document.updateVotedCount', 'a_mission', 'controller', 'triggerDocumentVoted', 'after'))
            $oModuleController->insertTrigger('document.updateVotedCount', 'a_mission', 'controller', 'triggerDocumentVoted', 'after');

        return new BaseObject(0, 'success_updated');
    }

    /**
     * @brief re-generate the cache file
     */
    function recompileCache()
    {
    }
}
