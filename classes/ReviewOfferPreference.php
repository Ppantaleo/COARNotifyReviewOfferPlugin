<?php

/**
 * @file plugins/generic/coarNotifyReviewOffer/classes/ReviewOfferPreference.inc.php
 *
 * @class ReviewOfferPreference
 * @ingroup plugins_generic_coarNotifyReviewOffer
 *
 * Data object representing a Review Offer Preference.
 */

class ReviewOfferPreference extends DataObject {

    /**
     * Get submission ID.
     * @return int
     */
    function getSubmissionId(){
        return $this->getData('submissionId');
    }

    /**
     * Set submission ID.
     * @param $submissionId int
     */
    function setSubmissionId($submissionId) {
        return $this->setData('submissionId', $submissionId);
    }

    /**
     * Get service URL.
     * @return string
     */
    function getServiceUrl(){
        return $this->getData('serviceUrl');
    }

    /**
     * Set service URL.
     * @param $serviceUrl string
     */
    function setServiceUrl($serviceUrl) {
        return $this->setData('serviceUrl', $serviceUrl);
    }

    /**
     * Get is sent flag.
     * @return string
     */
    function getIsSent(){
        return $this->getData('isSent');
    }

    /**
     * Set is sent flag.
     * @param $isSent bool
     */
    function setIsSent($isSent) {
        return $this->setData('isSent', $isSent);
    }

}