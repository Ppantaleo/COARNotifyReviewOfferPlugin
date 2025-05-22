<?php

/**
 * @file CoarNotifyReviewOfferSchemaMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CoarNotifyReviewOfferSchemaMigration
 * @brief Describe database table structures for COAR Notify Review Offer plugin.
 */

 use Illuminate\Database\Schema\Blueprint;
 use Illuminate\Database\Capsule\Manager as Capsule;

class CoarNotifyReviewOfferSchemaMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Verificar que tenemos una conexión válida usando múltiples métodos
            $connection = null;
            
            // Intentar obtener conexión de diferentes maneras
            try {
                $connection = DB::connection();
            } catch (Exception $e) {
                // Si falla DB::connection(), intentar con otro método
                try {
                    $dao = DAORegistry::getDAO('UserDAO');
                    if ($dao && method_exists($dao, 'getDataSource')) {
                        $connection = $dao->getDataSource();
                    }
                } catch (Exception $e2) {
                    error_log("Could not get database connection: " . $e2->getMessage());
                }
            }
            
            if (!$connection) {
                throw new Exception('No database connection available');
            }

            // Crear tabla de servicios de revisión
            if (!$this->tableExists('coar_notify_review_services')) {
                $sql = "
                    CREATE TABLE coar_notify_review_services (
                        service_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        context_id BIGINT NOT NULL,
                        service_name VARCHAR(255) NOT NULL,
                        service_url TEXT NOT NULL,
                        service_description TEXT,
                        is_active BOOLEAN DEFAULT TRUE,
                        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX coar_notify_review_services_context_id (context_id),
                        INDEX coar_notify_review_services_is_active (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                try {
                    DB::statement($sql);
                } catch (Exception $e) {
                    // Fallback a método ADODB si existe
                    if (method_exists($connection, 'Execute')) {
                        $connection->Execute($sql);
                    } else {
                        throw $e;
                    }
                }
            }

            // Crear tabla de notificaciones
            if (!$this->tableExists('coar_notify_review_notifications')) {
                $sql = "
                    CREATE TABLE coar_notify_review_notifications (
                        notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        submission_id BIGINT NOT NULL,
                        service_id BIGINT NOT NULL,
                        user_id BIGINT NOT NULL,
                        notification_type VARCHAR(100) NOT NULL,
                        notification_payload TEXT NOT NULL,
                        status VARCHAR(50) DEFAULT 'pending',
                        response_data TEXT,
                        date_sent TIMESTAMP NULL,
                        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX coar_notify_review_notifications_submission_id (submission_id),
                        INDEX coar_notify_review_notifications_service_id (service_id),
                        INDEX coar_notify_review_notifications_user_id (user_id),
                        INDEX coar_notify_review_notifications_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                try {
                    DB::statement($sql);
                } catch (Exception $e) {
                    if (method_exists($connection, 'Execute')) {
                        $connection->Execute($sql);
                    } else {
                        throw $e;
                    }
                }
            }

            // Crear tabla de configuración de usuario
            if (!$this->tableExists('coar_notify_user_settings')) {
                $sql = "
                    CREATE TABLE coar_notify_user_settings (
                        setting_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        user_id BIGINT NOT NULL,
                        context_id BIGINT NOT NULL,
                        auto_notify_on_publication BOOLEAN DEFAULT FALSE,
                        preferred_services TEXT,
                        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY coar_notify_user_settings_unique (user_id, context_id),
                        INDEX coar_notify_user_settings_context_id (context_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                try {
                    DB::statement($sql);
                } catch (Exception $e) {
                    if (method_exists($connection, 'Execute')) {
                        $connection->Execute($sql);
                    } else {
                        throw $e;
                    }
                }
            }

            error_log("COAR Notify Review Offer Plugin: Database tables created successfully");

        } catch (Exception $e) {
            error_log("COAR Notify Review Offer Plugin Migration Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new Exception("Failed to create database tables for COAR Notify Review Offer Plugin: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $sqls = [
                "DROP TABLE IF EXISTS coar_notify_user_settings",
                "DROP TABLE IF EXISTS coar_notify_review_notifications", 
                "DROP TABLE IF EXISTS coar_notify_review_services"
            ];
            
            foreach ($sqls as $sql) {
                try {
                    DB::statement($sql);
                } catch (Exception $e) {
                    // Intentar con DAO si DB::statement falla
                    try {
                        $dao = DAORegistry::getDAO('UserDAO');
                        if ($dao && method_exists($dao, 'getDataSource')) {
                            $connection = $dao->getDataSource();
                            if (method_exists($connection, 'Execute')) {
                                $connection->Execute($sql);
                            }
                        }
                    } catch (Exception $e2) {
                        error_log("Could not execute: {$sql} - " . $e2->getMessage());
                    }
                }
            }
            
            error_log("COAR Notify Review Offer Plugin: Database tables dropped successfully");
        } catch (Exception $e) {
            error_log("COAR Notify Review Offer Plugin Migration Down Error: " . $e->getMessage());
        }
    }

    /**
     * Check if a table exists
     * @param string $tableName
     * @return bool
     */
    private function tableExists($tableName)
    {
        try {
            $result = DB::select("SHOW TABLES LIKE '{$tableName}'");
            return !empty($result);
        } catch (Exception $e) {
            // Fallback method
            try {
                $dao = DAORegistry::getDAO('UserDAO');
                if ($dao && method_exists($dao, 'getDataSource')) {
                    $connection = $dao->getDataSource();
                    if (method_exists($connection, 'Execute')) {
                        $result = $connection->Execute("SHOW TABLES LIKE '{$tableName}'");
                        return $result && !$result->EOF;
                    }
                }
            } catch (Exception $e2) {
                error_log("Error checking if table {$tableName} exists: " . $e2->getMessage());
            }
            return false;
        }
    }
}