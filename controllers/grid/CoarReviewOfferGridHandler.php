<?php

import('lib.pkp.classes.controllers.grid.GridHandler');
import('plugins.generic.coarNotifyReviewOffer.controllers.grid.CoarNotifyReviewOfferGridRow');
import('plugins.generic.coarNotifyReviewOffer.controllers.grid.CoarNotifyReviewOfferGridCellProvider');

class CoarReviewOfferGridHandler extends GridHandler {
    static $plugin;

    /** @var boolean */
    var $_readOnly;

    /**
     * Constructor
     */
    function __construct() {
        parent::__construct();
        $this->addRoleAssignment(
            array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
            array(
                'fetchGrid',
                'fetchRow',
                'assignService',
                'unassignService',
            )
        );
    }

    /**
     * Set the CoarNotifyReviewOfferPlugin plugin.
     * @param $plugin CoarNotifyReviewOfferPlugin
     */
    static function setPlugin($plugin) {
        self::$plugin = $plugin;
    }

    /**
     * Get the submission associated with this grid.
     * @return Submission
     */
    function getSubmission() {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get whether or not this grid should be 'read only'
     * @return boolean
     */
    function getReadOnly() {
        return $this->_readOnly;
    }

    /**
     * Set the boolean for 'read only' status
     * @param boolean
     */
    function setReadOnly($readOnly) {
        $this->_readOnly = $readOnly;
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    function authorize($request, &$args, $roleAssignments) {
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    function getSlashlessString($input): string {
        return str_replace('/', '-', $input);
    }

    /**
     * @copydoc Gridhandler::initialize()
     */
    function initialize($request, $args = null) {
        parent::initialize($request, $args);

        $gridData = array();
        $this->setTitle('plugins.generic.coarNotifyReviewOffer.preferences');
        $this->setEmptyRowText('plugins.generic.coarNotifyReviewOffer.noServices');

        if (!$this::$plugin) {
            return;
        }

        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $reviewOfferPreferenceDao = DAORegistry::getDAO('ReviewOfferPreferenceDAO');

        $currentlySelectedReviewOfferServices = array_map(function($prefResult) {
            return $prefResult->getData('serviceUrl');
        }, $reviewOfferPreferenceDao->getBySubmissionId($submission->getId())->toArray());

        $reviewServiceList = $this::$plugin->getReviewServiceList();

        foreach ($reviewServiceList as $serviceUrl => $inboxUrl) {
            $isSelected = in_array($serviceUrl, $currentlySelectedReviewOfferServices);

            $gridData[$this->getSlashlessString($serviceUrl)] = array(
                'serviceUrl' =>  $serviceUrl,
                'isSelected' => $isSelected,
            );
        }

        $this->setGridDataElements($gridData);

        if ($this->canAdminister($request->getUser())) {
            $this->setReadOnly(false);
        } else {
            $this->setReadOnly(true);
        }

        // Columns
        $cellProvider = new CoarNotifyReviewOfferGridCellProvider();
        $cellProvider->setSubmissionId($submissionId);

        $this->addColumn(new GridColumn(
            'reviewService',
            'plugins.generic.coarNotifyReviewOffer.reviewService',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));

        $this->addColumn(new GridColumn(
            'sendReviewOnPublication',
            'plugins.generic.coarNotifyReviewOffer.sendReviewOffer',
            null,
            'controllers/grid/common/cell/selectStatusCell.tpl',
            $cellProvider
        ));
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc Gridhandler::getRowInstance()
     */
    function getRowInstance() {
        return new CoarNotifyReviewOfferGridRow($this->getReadOnly());
    }

    /**
     * @copydoc GridHandler::getJSHandler()
     */
    public function getJSHandler() {
        return '$.pkp.plugins.generic.coarNotifyReviewOffer.CoarReviewOfferGridHandler';
    }

    /**
     * @param $user User
     * @return boolean
     */
    function canAdminister($user) {
        return true;
    }

    function sendNotification($type, $params) {
        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            $type,
            $params,
        );
    }

    /**
     * Assign service from review offer preferences.
     * @param $args array
     * @param $request PKPRequest
     */
    function assignService($args, $request) {
        if (!$request->checkCSRF()) return new JSONMessage(false);

        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $serviceUrl = $args['serviceUrl'][0];

        $reviewOfferPreferenceDao = DAORegistry::getDAO('ReviewOfferPreferenceDAO');
        $reviewOfferPreference = new ReviewOfferPreference();
        $reviewOfferPreference->setSubmissionId($submissionId);
        $reviewOfferPreference->setServiceUrl($serviceUrl);
        $reviewOfferPreference->setIsSent(false);

        $reviewOfferPreferenceDao->insertObject($reviewOfferPreference);

        $this->sendNotification(
            NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('plugins.generic.coarNotifyReviewOffer.reviewOfferPreferencesUpdated')],
        );

        return DAO::getDataChangedEvent($submissionId);
    }

    /**
     * Unassign service from review offer preferences.
     * @param $args array
     * @param $request PKPRequest
     */
    function unassignService($args, $request) {
        if (!$request->checkCSRF()) return new JSONMessage(false);
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $serviceUrl = $args['serviceUrl'][0];

        $reviewOfferPreferenceDao = DAORegistry::getDAO('ReviewOfferPreferenceDAO');
        $reviewOfferPreferenceDao->deleteByServiceUrlAndSubmissionId($serviceUrl, $submissionId);

        $this->sendNotification(
            NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('plugins.generic.coarNotifyReviewOffer.reviewOfferPreferencesUpdated')],
        );
        return DAO::getDataChangedEvent($submissionId);
    }

}
