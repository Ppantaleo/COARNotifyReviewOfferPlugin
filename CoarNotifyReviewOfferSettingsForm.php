<?php

import('lib.pkp.classes.form.Form');

class CoarNotifyReviewOfferSettingsForm extends Form {

    /** @var int Associated context ID */
    private $_contextId;

    /** @var CoarNotifyReviewOfferPlugin Registration notification plugin */
    private $_plugin;

    /**
     * Constructor
     * @param $plugin CoarNotifyReviewOfferPlugin Registration notification plugin
     * @param $contextId int Context ID
     */
    public function __construct(CoarNotifyReviewOfferPlugin $plugin, $contextId) {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData() {
        $originName = $this->_plugin->getSetting($this->_contextId, 'originName');
        $this->setData('originName', $originName);
        $originHomeUrl = $this->_plugin->getSetting($this->_contextId, 'originHomeUrl');
        $this->setData('originHomeUrl', $originHomeUrl);
        $originInboxUrl = $this->_plugin->getSetting($this->_contextId, 'originInboxUrl');
        $this->setData('originInboxUrl', $originInboxUrl);

        $reviewServiceList = $this->_plugin->getSetting($this->_contextId, 'reviewServiceList');
        $this->setData('homeUrl', is_array($reviewServiceList) ? array_keys($reviewServiceList) : []);
        $this->setData('inboxUrl', is_array($reviewServiceList) ? array_values($reviewServiceList) : []);

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData() {
        $this->readUserVars(array('homeUrl', 'inboxUrl'));
        $homeUrls = $this->getData('homeUrl');
        $inboxUrls = $this->getData('inboxUrl');
        foreach($inboxUrls as $i => $inboxUrl) {
            //clean empty entries
            if(empty($inboxUrl) && empty($homeUrls[$i])){
                unset($inboxUrls[$i]);
                unset($homeUrls[$i]);
            }
        }
        $this->setData('homeUrl', array_values($homeUrls));
        $this->setData('inboxUrl', array_values($inboxUrls));

        $this->readUserVars(['originName']);
        $originName = $this->getData('originName');
        $this->setData('originName', $originName);

        $this->readUserVars(['originHomeUrl']);
        $originHomeUrl = $this->getData('originHomeUrl');
        $this->setData('originHomeUrl', $originHomeUrl);

        $this->readUserVars(['originInboxUrl']);
        $originInboxUrl = $this->getData('originInboxUrl');
        $this->setData('originInboxUrl', $originInboxUrl);

        parent::readInputData();
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false) {
        $templateManager = TemplateManager::getManager($request);
        $templateManager->assign('pluginName', $this->_plugin->getName());
        $templateManager->addJavaScript(
            'CoarNotifyReviewOfferSettingsFormHandler',
            $request->getBaseUrl() . '/' . $this->_plugin->getPluginPath() . '/js/CoarNotifyReviewOfferSettingsFormHandler.js',
            [
                'priority' => STYLE_SEQUENCE_CORE,
                'contexts' => 'CoarNotifyReviewOfferSettingsForm'
            ]
        );
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs) {
        $this->_plugin->updateSetting($this->_contextId, 'reviewServiceList', array_combine($this->getData('homeUrl'), $this->getData('inboxUrl')), 'object');

        $this->_plugin->updateSetting($this->_contextId, 'originName', $this->getData('originName'), 'string');
        $this->_plugin->updateSetting($this->_contextId, 'originHomeUrl', $this->getData('originHomeUrl'), 'string');
        $this->_plugin->updateSetting($this->_contextId, 'originInboxUrl', $this->getData('originInboxUrl'), 'string');
        return parent::execute(...$functionArgs);
    }
}