<?php

/**
 * @file CoarNotifyReviewOfferPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CoarNotifyReviewOfferPlugin
 * @ingroup plugins_generic_coarNotifyReviewOffer
 *
 * @brief COAR Notify Review Offer plugin class
 */

use PKP\plugins\GenericPlugin;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\config\Config;
use PKP\core\Registry;
use APP\core\Application;
use PKP\core\PKPApplication;
use PKP\template\TemplateManager;
use PKP\core\Core;
use APP\i18n\AppLocale;
use PKP\plugins\HookRegistry;
use PKP\db\DAORegistry;

class CoarNotifyReviewOfferPlugin extends GenericPlugin
{
    /**
     * Called as a plugin is registered to the registry
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            // AGREGAR: Registrar DAOs necesarios
            $this->import('classes.ReviewOfferPreferenceDAO');
            $reviewOfferPreferenceDao = new ReviewOfferPreferenceDAO();
            DAORegistry::registerDAO('ReviewOfferPreferenceDAO', $reviewOfferPreferenceDao);
            
            // Hooks existentes
            HookRegistry::register('Templates::Management::Settings::website', array($this, 'callbackShowWebsiteSettingsTabs'));
            HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));
            HookRegistry::register('Publication::publish', array($this, 'handlePublicationEvent'));
            
            // Hook para mostrar en el workflow
            HookRegistry::register('Templates::Workflow::Publication', array($this, 'addToWorkflow'));
        }
        return $success;
    }

    /**
     * Get the plugin display name.
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.coarNotifyReviewOffer.displayName');
    }

    /**
     * Get the plugin description.
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.coarNotifyReviewOffer.description');
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration()
    {
        $this->import('CoarNotifyReviewOfferSchemaMigration');
        return new CoarNotifyReviewOfferSchemaMigration();
    }

    /**
     * Método para obtener configuraciones de servicios de revisión
     */
    public function getReviewServiceList($contextId = null)
    {
        if ($contextId === null) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $contextId = $context ? $context->getId() : 0;
        }
        
        $reviewServiceList = $this->getSetting($contextId, 'reviewServiceList');
        return is_array($reviewServiceList) ? $reviewServiceList : array();
    }

    /**
     * Get the JavaScript URL for this plugin.
     * @param $request PKPRequest
     * @return string
     */
    public function getJavaScriptURL($request)
    {
        return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/CoarNotifyReviewOfferPlugin.js';
    }

    /**
     * Override the builtin to get the correct template path.
     * @param string $template Template name (optional)
     * @param bool $inCore Whether template is in core (optional)
     * @return string
     */
    public function getTemplateResource($template = null, $inCore = false)
    {
        if ($template === null) {
            return 'plugins/generic/coarNotifyReviewOffer:templates/';
        }
        return 'plugins/generic/coarNotifyReviewOffer:templates/' . $template;
    }

    /**
     * Get the settings form for this plugin.
     * @param $contextId int Context ID
     * @return CoarNotifyReviewOfferSettingsForm
     */
    public function getSettingsForm($contextId)
    {
        $this->import('CoarNotifyReviewOfferSettingsForm');
        return new CoarNotifyReviewOfferSettingsForm($this, $contextId);
    }

    /**
     * @see Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
                
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));

                $settingsForm = $this->getSettingsForm($context->getId());

                if ($request->getUserVar('save')) {
                    $settingsForm->readInputData();
                    if ($settingsForm->validate()) {
                        $settingsForm->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $settingsForm->initData();
                }
                return new JSONMessage(true, $settingsForm->fetch($request));
                
            case 'reload':
                $context = $request->getContext();
                $contextId = $context ? $context->getId() : CONTEXT_SITE;
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pluginName' => $this->getName(),
                    'contextId' => $contextId,
                ]);
                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settings.tpl')));
        }
        return parent::manage($args, $request);
    }

    /**
     * Hook callback: register output filter to add data citation to views.
     * @param $hookName string
     * @param $args array
     */
    public function callbackShowWebsiteSettingsTabs($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $output .= $templateMgr->fetch($this->getTemplateResource('websiteSettingsTab.tpl'));
        return false;
    }

    /**
     * Hook para mostrar en el workflow de publicación
     */
    public function addToWorkflow($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        
        $request = Application::get()->getRequest();
        $submission = $templateMgr->getTemplateVars('submission');
        
        if ($submission) {
            $templateMgr->assign([
                'submissionId' => $submission->getId(),
                'reviewServiceList' => $this->getReviewServiceList()
            ]);
            
            $output .= $templateMgr->fetch($this->getTemplateResource('coarNotifyReviewOffer.tpl'));
        }
        
        return false;
    }

    /**
     * Permit requests to the COAR Notify grid handler
     * @param $hookName string The name of the hook being invoked
     * @param $params array The parameters to the invoked hook
     */
    public function setupGridHandler($hookName, $params)
    {
        $component = &$params[0];
        
        if ($component == 'plugins.generic.coarNotifyReviewOffer.controllers.grid.CoarReviewOfferGridHandler') {
            import($component);
            CoarReviewOfferGridHandler::setPlugin($this);
            return true;
        }
        return false;
    }

    /**
     * Handle publication events to send automatic notifications
     * @param $hookName string
     * @param $params array
     */
    public function handlePublicationEvent($hookName, $params)
    {
        $newPublication = $params[0];
        $submission = $params[1];
        $request = $params[2];

        // Import necessary classes
        $this->import('classes.CoarNotifyReviewOfferDAO');
        $coarDAO = new CoarNotifyReviewOfferDAO();

        // Check if user has auto-notify enabled
        $context = $request->getContext();
        $user = $request->getUser();
        
        if ($user && $coarDAO->getUserAutoNotifySetting($user->getId(), $context->getId())) {
            // Send automatic notifications
            $this->sendAutomaticNotifications($submission, $user, $context);
        }

        return false;
    }

    /**
     * Send automatic notifications for published submissions
     * @param $submission Submission
     * @param $user User
     * @param $context Context
     */
    private function sendAutomaticNotifications($submission, $user, $context)
    {
        $this->import('classes.CoarNotifyReviewOfferDAO');
        $coarDAO = new CoarNotifyReviewOfferDAO();
        
        $activeServices = $coarDAO->getActiveServices($context->getId());
        
        foreach ($activeServices as $service) {
            $this->sendNotificationToService($submission, $service, $user, 'automatic');
        }
    }

    /**
     * Send notification to a specific service
     * @param $submission Submission
     * @param $service array
     * @param $user User
     * @param $type string
     */
    private function sendNotificationToService($submission, $service, $user, $type = 'manual')
    {
        $this->import('classes.CoarNotifyReviewOfferDAO');
        $coarDAO = new CoarNotifyReviewOfferDAO();
        
        // Build the notification payload
        $payload = $this->buildNotificationPayload($submission, $service);
        
        try {
            // Send HTTP request to service
            $response = $this->sendHttpNotification($service['service_url'], $payload);
            
            // Log the notification attempt
            $notificationId = $coarDAO->logNotification(
                $submission->getId(),
                $service['service_id'],
                $user->getId(),
                $type,
                json_encode($payload)
            );
            
            // Update status based on response
            if ($response) {
                $coarDAO->updateNotificationStatus($notificationId, 'sent', json_encode($response));
            } else {
                $coarDAO->updateNotificationStatus($notificationId, 'failed');
            }
            
        } catch (Exception $e) {
            error_log("COAR Notify Error: " . $e->getMessage());
            // Log failed notification
            $notificationId = $coarDAO->logNotification(
                $submission->getId(),
                $service['service_id'],
                $user->getId(),
                $type,
                json_encode($payload)
            );
            $coarDAO->updateNotificationStatus($notificationId, 'failed', $e->getMessage());
        }
    }

    /**
     * Build COAR Notify protocol payload
     * @param $submission Submission
     * @param $service array
     * @return array
     */
    private function buildNotificationPayload($submission, $service)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        // Build JSON-LD payload according to COAR Notify specification
        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://purl.org/coar/notify'
            ],
            'id' => $request->getCompleteUrl() . '/notify/' . uniqid(),
            'type' => ['Offer', 'coar-notify:ReviewAction'],
            'summary' => 'Review offer for preprint: ' . $submission->getLocalizedTitle(),
            'actor' => [
                'id' => $context->getUrl(),
                'type' => 'Application',
                'name' => $context->getLocalizedName()
            ],
            'object' => [
                'id' => $request->getRouter()->url($request, null, 'preprint', 'view', $submission->getBestId()),
                'type' => ['Document', 'sorg:ScholarlyArticle'],
                'url' => $request->getRouter()->url($request, null, 'preprint', 'view', $submission->getBestId())
            ],
            'target' => [
                'id' => $service['service_url'],
                'type' => 'Service',
                'inbox' => $service['service_url']
            ],
            'origin' => [
                'id' => $context->getUrl(),
                'type' => 'Application',
                'name' => $context->getLocalizedName()
            ]
        ];
    }

    /**
     * Send HTTP notification using cURL
     * @param $url string
     * @param $payload array
     * @return array|bool
     */
    private function sendHttpNotification($url, $payload)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/ld+json',
                'Accept: application/ld+json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // For development only
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
        }
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }
}