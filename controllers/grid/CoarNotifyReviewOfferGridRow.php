<?php

/**
 * @brief Handle item grid row requests.
 */

 use PKP\controllers\grid\GridHandler;
 use PKP\controllers\grid\GridRow;
 use PKP\controllers\grid\GridCellProvider;
 use PKP\controllers\grid\GridColumn;
 use PKP\linkAction\LinkAction;
 use PKP\linkAction\request\AjaxAction;
 use PKP\security\authorization\SubmissionAccessPolicy;
 use PKP\core\JSONMessage;
 use PKP\db\DAORegistry;
 use APP\core\Application;

class CoarNotifyReviewOfferGridRow extends GridRow {
    /** @var boolean */
    var $_readOnly;

    /**
     * Constructor
     */
    function __construct($readOnly = false) {
        $this->_readOnly = $readOnly;
        parent::__construct();
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc GridRow::initialize()
     */
    function initialize($request, $template = null) {
        parent::initialize($request, $template);
    }

    /**
     * Determine if this grid row should be read only.
     * @return boolean
     */
    function isReadOnly() {
        return $this->_readOnly;
    }
}
