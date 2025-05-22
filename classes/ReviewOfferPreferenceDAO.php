<?php

import('lib.pkp.classes.db.DAO');
import('plugins.generic.coarNotifyReviewOffer.classes.ReviewOfferPreference');

class ReviewOfferPreferenceDAO extends DAO {

    /**
     * Get ReviewOfferPreference by submission ID.
     * @param $submissionId int Submission ID
     * @return ReviewOfferPreference
     */
    function getBySubmissionId($submissionId) {
        $result = $this->retrieve(
            'SELECT * FROM review_offer_preferences WHERE submission_id = ?',
            [$submissionId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Insert a ReviewOfferPreference.
     * @param $preference ReviewOfferPreference
     * @return Void
     */
    function insertObject($preference) {
        $this->update(
            'INSERT INTO review_offer_preferences (submission_id, service_url, is_sent) VALUES (?, ?, ?)',
            array(
                $preference->getSubmissionId(),
                $preference->getServiceUrl(),
                (bool) $preference->getIsSent()
            )
        );
    }

    /**
     * Get the id of the last inserted ReviewOfferPreference.
     * @return int
     */
    function getInsertId() {
        return parent::_getInsertId('review_offer_preferences', 'id');
    }

    function deleteByServiceUrlAndSubmissionId($serviceUrl, $submissionId) {
        $this->update(
            'DELETE FROM review_offer_preferences WHERE service_url = ? AND submission_id = ?',
            array(
                (string) $serviceUrl,
                (int) $submissionId,
            )
        );
    }

    /**
     * Generate a new ReviewOfferPreference object.
     * @return ReviewOfferPreference
     */
    function newDataObject() {
        return new ReviewOfferPreference();
    }

    /**
     * Return a new ReviewOfferPreference object from a given row.
     * @return ReviewOfferPreference
     */
    function _fromRow($row) {
        $preference = $this->newDataObject();
        $preference->setSubmissionId($row['submission_id']);
        $preference->setServiceUrl($row['service_url']);
        $preference->setIsSent($row['is_sent']);

        return $preference;
    }

}