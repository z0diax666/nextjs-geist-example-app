<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

class EmployeeAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAllEmployees($userId, $userRole) {
        try {
            $query = "SELECT 
                        employee_id,
                        name,
                        email,
                        designation,
                        department,
                        manager_id,
                        salary,
                        hire_date,
                        status
                     FROM employees 
                     WHERE status = 'active'
                     ORDER BY name ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $employees = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Hide salary for non-admin users
                if (!in_array($userRole, ['Admin', 'Approver'])) {
                    unset($row['salary']);
                }
                $employees[] = $row;
            }
            
            return [
                'success' => true,
                'data' => $employees
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch employees: ' . $e->getMessage()
            ];
        }
    }
    
    public function getEmployee($employeeId, $userId, $userRole) {
        try {
            $query = "SELECT 
                        employee_id,
                        name,
                        email,
                        designation,
                        department,
                        manager_id,
                        salary,
                        hire_date,
                        status
                     FROM employees 
                     WHERE employee_id = :employee_id AND status = 'active'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Hide salary for non-admin users
                if (!in_array($userRole, ['Admin', 'Approver'])) {
                    unset($employee['salary']);
                }
                
                // Get manager details if manager_id exists
                if (!empty($employee['manager_id'])) {
                    $managerQuery = "SELECT name, designation FROM employees WHERE employee_id = :manager_id";
                    $managerStmt = $this->conn->prepare($managerQuery);
                    $managerStmt->bindParam(':manager_id', $employee['manager_id']);
                    $managerStmt->execute();
                    
                    if ($managerStmt->rowCount() > 0) {
                        $manager = $managerStmt->fetch(PDO::FETCH_ASSOC);
                        $employee['manager_name'] = $manager['name'];
                        $employee['manager_designation'] = $manager['designation'];
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $employee
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Employee not found'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch employee: ' . $e->getMessage()
            ];
        }
    }
    
    public function searchEmployees($searchTerm, $userId, $userRole) {
        try {
            $query = "SELECT 
                        employee_id,
                        name,
                        email,
                        designation,
                        department,
                        manager_id,
                        salary,
                        hire_date,
                        status
                     FROM employees 
                     WHERE status = 'active' 
                     AND (
                        name LIKE :search_term 
                        OR employee_id LIKE :search_term 
                        OR email LIKE :search_term 
                        OR designation LIKE :search_term 
                        OR department LIKE :search_term
                     )
                     ORDER BY name ASC
                     LIMIT 20";
            
            $stmt = $this->conn->prepare($query);
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bindParam(':search_term', $searchPattern);
            $stmt->execute();
            
            $employees = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Hide salary for non-admin users
                if (!in_array($userRole, ['Admin', 'Approver'])) {
                    unset($row['salary']);
                }
                $employees[] = $row;
            }
            
            return [
                'success' => true,
                'data' => $employees
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function getEmployeesByDepartment($department, $userId, $userRole) {
        try {
            $query = "SELECT 
                        employee_id,
                        name,
                        email,
                        designation,
                        department,
                        manager_id,
                        salary,
                        hire_date,
                        status
                     FROM employees 
                     WHERE status = 'active' AND department = :department
                     ORDER BY name ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            
            $employees = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Hide salary for non-admin users
                if (!in_array($userRole, ['Admin', 'Approver'])) {
                    unset($row['salary']);
                }
                $employees[] = $row;
            }
            
            return [
                'success' => true,
                'data' => $employees
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch employees by department: ' . $e->getMessage()
            ];
        }
    }
    
    public function getDepartments() {
        try {
            $query = "SELECT DISTINCT department 
                     FROM employees 
                     WHERE status = 'active' AND department IS NOT NULL 
                     ORDER BY department ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $departments = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $departments[] = $row['department'];
            }
            
            return [
                'success' => true,
                'data' => $departments
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch departments: ' . $e->getMessage()
            ];
        }
    }
    
    public function getDesignations() {
        try {
            $query = "SELECT DISTINCT designation 
                     FROM employees 
                     WHERE status = 'active' AND designation IS NOT NULL 
                     ORDER BY designation ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $designations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $designations[] = $row['designation'];
            }
            
            return [
                'success' => true,
                'data' => $designations
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch designations: ' . $e->getMessage()
            ];
        }
    }
    
    public function getEmployeeStats($userId, $userRole) {
        try {
            // Only Admin and Approver can view detailed stats
            if (!in_array($userRole, ['Admin', 'Approver'])) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions to view employee statistics'
                ];
            }
            
            // Total employees
            $query = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Employees by department
            $query = "SELECT department, COUNT(*) as count 
                     FROM employees 
                     WHERE status = 'active' AND department IS NOT NULL 
                     GROUP BY department 
                     ORDER BY count DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $departmentStats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $departmentStats[] = $row;
            }
            
            // Employees by designation
            $query = "SELECT designation, COUNT(*) as count 
                     FROM employees 
                     WHERE status = 'active' AND designation IS NOT NULL 
                     GROUP BY designation 
                     ORDER BY count DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $designationStats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $designationStats[] = $row;
            }
            
            // Recent hires (last 30 days)
            $query = "SELECT COUNT(*) as recent_hires 
                     FROM employees 
                     WHERE status = 'active' AND hire_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $recentHires = $stmt->fetch(PDO::FETCH_ASSOC)['recent_hires'];
            
            return [
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'recent_hires' => $recentHires,
                    'department_breakdown' => $departmentStats,
                    'designation_breakdown' => $designationStats
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch employee statistics: ' . $e->getMessage()
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
$employeeAPI = new EmployeeAPI();
$method = $_SERVER['REQUEST_METHOD'];

// Get authorization header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Validate user
$user = $employeeAPI->validateUser($token);
if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['employee_id'])) {
            $response = $employeeAPI->getEmployee($_GET['employee_id'], $user['id'], $user['role']);
        } elseif (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'search':
                    $searchTerm = $_GET['q'] ?? '';
                    if (empty($searchTerm)) {
                        $response = [
                            'success' => false,
                            'error' => 'Search term required'
                        ];
                    } else {
                        $response = $employeeAPI->searchEmployees($searchTerm, $user['id'], $user['role']);
                    }
                    break;
                case 'departments':
                    $response = $employeeAPI->getDepartments();
                    break;
                case 'designations':
                    $response = $employeeAPI->getDesignations();
                    break;
                case 'stats':
                    $response = $employeeAPI->getEmployeeStats($user['id'], $user['role']);
                    break;
                case 'by_department':
                    $department = $_GET['department'] ?? '';
                    if (empty($department)) {
                        $response = [
                            'success' => false,
                            'error' => 'Department required'
                        ];
                    } else {
                        $response = $employeeAPI->getEmployeesByDepartment($department, $user['id'], $user['role']);
                    }
                    break;
                default:
                    $response = [
                        'success' => false,
                        'error' => 'Invalid action'
                    ];
            }
        } else {
            $response = $employeeAPI->getAllEmployees($user['id'], $user['role']);
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
