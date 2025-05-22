<?php

/**
 * @file classes/CoarNotifyReviewOfferDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CoarNotifyReviewOfferDAO
 * @ingroup plugins_generic_coarNotifyReviewOffer
 *
 * @brief Operations for retrieving and modifying COAR Notify Review Offer data.
 */

use Illuminate\Support\Facades\DB;

class CoarNotifyReviewOfferDAO extends DAO
{
    /**
     * Get all active review services for a context
     * @param int $contextId
     * @return array
     */
    public function getActiveServices($contextId)
    {
        return DB::table('coar_notify_review_services')
            ->where('context_id', $contextId)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * Get a specific review service
     * @param int $serviceId
     * @return array|null
     */
    public function getService($serviceId)
    {
        $result = DB::table('coar_notify_review_services')
            ->where('service_id', $serviceId)
            ->first();
            
        return $result ? $result : null;
    }

    /**
     * Insert a new review service
     * @param int $contextId
     * @param string $serviceName
     * @param string $serviceUrl
     * @param string $serviceDescription
     * @return int Service ID
     */
    public function insertService($contextId, $serviceName, $serviceUrl, $serviceDescription = '')
    {
        return DB::table('coar_notify_review_services')->insertGetId([
            'context_id' => $contextId,
            'service_name' => $serviceName,
            'service_url' => $serviceUrl,
            'service_description' => $serviceDescription,
            'is_active' => true,
            'date_created' => DB::raw('NOW()'),
            'date_modified' => DB::raw('NOW()')
        ]);
    }

    /**
     * Update a review service
     * @param int $serviceId
     * @param array $data
     * @return boolean
     */
    public function updateService($serviceId, $data)
    {
        $data['date_modified'] = DB::raw('NOW()');
        
        return DB::table('coar_notify_review_services')
            ->where('service_id', $serviceId)
            ->update($data) > 0;
    }

    /**
     * Delete a review service
     * @param int $serviceId
     * @return boolean
     */
    public function deleteService($serviceId)
    {
        return DB::table('coar_notify_review_services')
            ->where('service_id', $serviceId)
            ->delete() > 0;
    }

    /**
     * Log a notification
     * @param int $submissionId
     * @param int $serviceId
     * @param int $userId
     * @param string $type
     * @param string $payload
     * @return int Notification ID
     */
    public function logNotification($submissionId, $serviceId, $userId, $type, $payload)
    {
        return DB::table('coar_notify_review_notifications')->insertGetId([
            'submission_id' => $submissionId,
            'service_id' => $serviceId,
            'user_id' => $userId,
            'notification_type' => $type,
            'notification_payload' => $payload,
            'status' => 'pending',
            'date_created' => DB::raw('NOW()')
        ]);
    }

    /**
     * Update notification status
     * @param int $notificationId
     * @param string $status
     * @param string $responseData
     * @return boolean
     */
    public function updateNotificationStatus($notificationId, $status, $responseData = null)
    {
        $updateData = [
            'status' => $status
        ];
        
        if ($responseData !== null) {
            $updateData['response_data'] = $responseData;
        }
        
        if ($status === 'sent') {
            $updateData['date_sent'] = DB::raw('NOW()');
        }
        
        return DB::table('coar_notify_review_notifications')
            ->where('notification_id', $notificationId)
            ->update($updateData) > 0;
    }

    /**
     * Get notifications for a submission
     * @param int $submissionId
     * @return array
     */
    public function getNotificationsBySubmission($submissionId)
    {
        return DB::table('coar_notify_review_notifications as n')
            ->leftJoin('coar_notify_review_services as s', 'n.service_id', '=', 's.service_id')
            ->select('n.*', 's.service_name')
            ->where('n.submission_id', $submissionId)
            ->orderBy('n.date_created', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get user auto-notify setting
     * @param int $userId
     * @param int $contextId
     * @return boolean
     */
    public function getUserAutoNotifySetting($userId, $contextId)
    {
        $result = DB::table('coar_notify_user_settings')
            ->where('user_id', $userId)
            ->where('context_id', $contextId)
            ->value('auto_notify_on_publication');
            
        return $result ? (bool)$result : false;
    }

    /**
     * Set user auto-notify setting
     * @param int $userId
     * @param int $contextId
     * @param boolean $autoNotify
     * @return boolean
     */
    public function setUserAutoNotifySetting($userId, $contextId, $autoNotify)
    {
        return DB::table('coar_notify_user_settings')->updateOrInsert(
            [
                'user_id' => $userId,
                'context_id' => $contextId
            ],
            [
                'auto_notify_on_publication' => (bool)$autoNotify,
                'date_modified' => DB::raw('NOW()')
            ]
        );
    }

    /**
     * Get user preferred services
     * @param int $userId
     * @param int $contextId
     * @return array
     */
    public function getUserPreferredServices($userId, $contextId)
    {
        $result = DB::table('coar_notify_user_settings')
            ->where('user_id', $userId)
            ->where('context_id', $contextId)
            ->value('preferred_services');
            
        return $result ? json_decode($result, true) : [];
    }

    /**
     * Set user preferred services
     * @param int $userId
     * @param int $contextId
     * @param array $services
     * @return boolean
     */
    public function setUserPreferredServices($userId, $contextId, $services)
    {
        return DB::table('coar_notify_user_settings')->updateOrInsert(
            [
                'user_id' => $userId,
                'context_id' => $contextId
            ],
            [
                'preferred_services' => json_encode($services),
                'date_modified' => DB::raw('NOW()')
            ]
        );
    }

    /**
     * Get statistics for notifications
     * @param int $contextId
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getNotificationStats($contextId, $dateStart = null, $dateEnd = null)
    {
        $query = DB::table('coar_notify_review_notifications as n')
            ->leftJoin('coar_notify_review_services as s', 'n.service_id', '=', 's.service_id')
            ->where('s.context_id', $contextId);
            
        if ($dateStart) {
            $query->where('n.date_created', '>=', $dateStart);
        }
        
        if ($dateEnd) {
            $query->where('n.date_created', '<=', $dateEnd);
        }
        
        return $query->select(
                's.service_name',
                'n.status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('s.service_name', 'n.status')
            ->get()
            ->toArray();
    }
}