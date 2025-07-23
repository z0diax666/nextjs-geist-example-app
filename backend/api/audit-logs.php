<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

class AuditLogAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAuditLogs($userId, $userRole, $filters = []) {
        try {
            // Only Admin and Approver can view audit logs
            if (!in_array($userRole, ['Admin', 'Approver'])) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions to view audit logs'
                ];
            }
            
            $whereConditions = [];
            $params = [];
            
            // Apply filters
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $whereConditions[] = "al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (isset($filters['document_id']) && !empty($filters['document_id'])) {
                $whereConditions[] = "al.document_id = :document_id";
                $params[':document_id'] = $filters['document_id'];
            }
            
            if (isset($filters['template_id']) && !empty($filters['template_id'])) {
                $whereConditions[] = "al.template_id = :template_id";
                $params[':template_id'] = $filters['template_id'];
            }
            
            if (isset($filters['action']) && !empty($filters['action'])) {
                $whereConditions[] = "al.action LIKE :action";
                $params[':action'] = '%' . $filters['action'] . '%';
            }
            
            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $whereConditions[] = "DATE(al.timestamp) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $whereConditions[] = "DATE(al.timestamp) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Pagination
            $page = isset($filters['page']) ? (int)$filters['page'] : 1;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total 
                          FROM audit_logs al 
                          LEFT JOIN users u ON al.user_id = u.id 
                          LEFT JOIN documents d ON al.document_id = d.id 
                          LEFT JOIN templates t ON al.template_id = t.id 
                          $whereClause";
            
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get audit logs with related data
            $query = "SELECT 
                        al.id,
                        al.user_id,
                        al.document_id,
                        al.template_id,
                        al.action,
                        al.details,
                        al.ip_address,
                        al.user_agent,
                        al.timestamp,
                        u.name as user_name,
                        u.email as user_email,
                        u.role as user_role,
                        d.template_name as document_name,
                        t.name as template_name
                     FROM audit_logs al
                     LEFT JOIN users u ON al.user_id = u.id
                     LEFT JOIN documents d ON al.document_id = d.id
                     LEFT JOIN templates t ON al.template_id = t.id
                     $whereClause
                     ORDER BY al.timestamp DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $auditLogs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $auditLogs[] = $row;
            }
            
            return [
                'success' => true,
                'data' => [
                    'logs' => $auditLogs,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($totalRecords / $limit),
                        'total_records' => $totalRecords,
                        'per_page' => $limit
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch audit logs: ' . $e->getMessage()
            ];
        }
    }
    
    public function getAuditLogStats($userId, $userRole) {
        try {
            // Only Admin and Approver can view audit log stats
            if (!in_array($userRole, ['Admin', 'Approver'])) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions to view audit log statistics'
                ];
            }
            
            // Get activity stats for the last 30 days
            $query = "SELECT 
                        DATE(timestamp) as date,
                        COUNT(*) as activity_count,
                        COUNT(DISTINCT user_id) as unique_users
                     FROM audit_logs 
                     WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY DATE(timestamp)
                     ORDER BY date DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $dailyStats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dailyStats[] = $row;
            }
            
            // Get action type stats
            $query = "SELECT 
                        action,
                        COUNT(*) as count
                     FROM audit_logs 
                     WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY action
                     ORDER BY count DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $actionStats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $actionStats[] = $row;
            }
            
            // Get user activity stats
            $query = "SELECT 
                        u.name,
                        u.email,
                        u.role,
                        COUNT(al.id) as activity_count,
                        MAX(al.timestamp) as last_activity
                     FROM users u
                     LEFT JOIN audit_logs al ON u.id = al.user_id 
                        AND al.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY u.id, u.name, u.email, u.role
                     ORDER BY activity_count DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $userStats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userStats[] = $row;
            }
            
            return [
                'success' => true,
                'data' => [
                    'daily_activity' => $dailyStats,
                    'action_breakdown' => $actionStats,
                    'user_activity' => $userStats
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch audit log statistics: ' . $e->getMessage()
            ];
        }
    }
    
    public function exportAuditLogs($userId, $userRole, $filters = []) {
        try {
            // Only Admin can export audit logs
            if ($userRole !== 'Admin') {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions to export audit logs'
                ];
            }
            
            $logsResult = $this->getAuditLogs($userId, $userRole, $filters);
            
            if (!$logsResult['success']) {
                return $logsResult;
            }
            
            $logs = $logsResult['data']['logs'];
            
            // Create CSV content
            $csvContent = "ID,User,Email,Role,Action,Details,Document,Template,IP Address,Timestamp\n";
            
            foreach ($logs as $log) {
                $csvContent .= sprintf(
                    "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                    $log['id'],
                    $log['user_name'] ?? '',
                    $log['user_email'] ?? '',
                    $log['user_role'] ?? '',
                    $log['action'],
                    str_replace('"', '""', $log['details'] ?? ''),
                    $log['document_name'] ?? '',
                    $log['template_name'] ?? '',
                    $log['ip_address'] ?? '',
                    $log['timestamp']
                );
            }
            
            // Save to file
            $uploadDir = '../uploads/exports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
            $filePath = $uploadDir . $filename;
            
            if (file_put_contents($filePath, $csvContent)) {
                // Log the export activity
                $this->logActivity($userId, null, null, 'Audit Logs Exported', 'Exported audit logs to CSV');
                
                return [
                    'success' => true,
                    'data' => [
                        'download_url' => 'uploads/exports/' . $filename,
                        'filename' => $filename
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to create export file'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function logActivity($userId, $documentId, $templateId, $action, $details) {
        try {
            $query = "INSERT INTO audit_logs (user_id, document_id, template_id, action, details, ip_address, user_agent) 
                     VALUES (:user_id, :document_id, :template_id, :action, :details, :ip_address, :user_agent)";
            $stmt = $this->conn->prepare($query);
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':document_id', $documentId);
            $stmt->bindParam(':template_id', $templateId);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
    
    private function validateUser($token) {
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) !== 2) {
                return false;
            }
            
            $userId = $parts[0];
            $timestamp = $parts[1];
            
            if ((time() - $timestamp) > 86400) {
                return false;
            }
            
            $query = "SELECT id, role FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Handle the request
$auditLogAPI = new AuditLogAPI();
$method = $_SERVER['REQUEST_METHOD'];

// Get authorization header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Validate user
$user = $auditLogAPI->validateUser($token);
if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'stats':
                    $response = $auditLogAPI->getAuditLogStats($user['id'], $user['role']);
                    break;
                case 'export':
                    $filters = $_GET;
                    unset($filters['action']);
                    $response = $auditLogAPI->exportAuditLogs($user['id'], $user['role'], $filters);
                    break;
                default:
                    $response = [
                        'success' => false,
                        'error' => 'Invalid action'
                    ];
            }
        } else {
            $filters = $_GET;
            $response = $auditLogAPI->getAuditLogs($user['id'], $user['role'], $filters);
        }
        break;
        
    default:
        $response = [
            'success' => false,
            'error' => 'Method not allowed'
        ];
}

echo json_encode($response);
?>
