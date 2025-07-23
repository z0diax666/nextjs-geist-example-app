<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

class TemplateAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAllTemplates() {
        try {
            $query = "SELECT t.*, u.name as created_by_name 
                     FROM templates t 
                     LEFT JOIN users u ON t.created_by = u.id 
                     ORDER BY t.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $templates = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['variables'] = json_decode($row['variables'], true);
                $templates[] = $row;
            }
            
            return [
                'success' => true,
                'data' => $templates
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch templates: ' . $e->getMessage()
            ];
        }
    }
    
    public function getTemplate($id) {
        try {
            $query = "SELECT t.*, u.name as created_by_name 
                     FROM templates t 
                     LEFT JOIN users u ON t.created_by = u.id 
                     WHERE t.id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                $template['variables'] = json_decode($template['variables'], true);
                
                return [
                    'success' => true,
                    'data' => $template
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Template not found'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch template: ' . $e->getMessage()
            ];
        }
    }
    
    public function createTemplate($data, $userId) {
        try {
            // Extract variables from template content
            $variables = $this->extractVariables($data['content']);
            
            // Validate template
            $validation = $this->validateTemplate($data['content']);
            if (!$validation['isValid']) {
                return [
                    'success' => false,
                    'error' => 'Template validation failed: ' . implode(', ', $validation['errors'])
                ];
            }
            
            $query = "INSERT INTO templates (name, content, variables, created_by) 
                     VALUES (:name, :content, :variables, :created_by)";
            $stmt = $this->conn->prepare($query);
            
            $variablesJson = json_encode($variables);
            
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':content', $data['content']);
            $stmt->bindParam(':variables', $variablesJson);
            $stmt->bindParam(':created_by', $userId);
            
            if ($stmt->execute()) {
                $templateId = $this->conn->lastInsertId();
                
                // Log the activity
                $this->logActivity($userId, null, $templateId, 'Template Created', 'Created template: ' . $data['name']);
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $templateId,
                        'message' => 'Template created successfully'
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to create template'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create template: ' . $e->getMessage()
            ];
        }
    }
    
    public function updateTemplate($id, $data, $userId) {
        try {
            // Extract variables from template content
            $variables = $this->extractVariables($data['content']);
            
            // Validate template
            $validation = $this->validateTemplate($data['content']);
            if (!$validation['isValid']) {
                return [
                    'success' => false,
                    'error' => 'Template validation failed: ' . implode(', ', $validation['errors'])
                ];
            }
            
            $query = "UPDATE templates 
                     SET name = :name, content = :content, variables = :variables, version = version + 1 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            
            $variablesJson = json_encode($variables);
            
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':content', $data['content']);
            $stmt->bindParam(':variables', $variablesJson);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Log the activity
                $this->logActivity($userId, null, $id, 'Template Updated', 'Updated template: ' . $data['name']);
                
                return [
                    'success' => true,
                    'data' => ['message' => 'Template updated successfully']
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to update template'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update template: ' . $e->getMessage()
            ];
        }
    }
    
    public function deleteTemplate($id, $userId) {
        try {
            // Get template name for logging
            $query = "SELECT name FROM templates WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                return [
                    'success' => false,
                    'error' => 'Template not found'
                ];
            }
            
            // Delete template
            $query = "DELETE FROM templates WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Log the activity
                $this->logActivity($userId, null, $id, 'Template Deleted', 'Deleted template: ' . $template['name']);
                
                return [
                    'success' => true,
                    'data' => ['message' => 'Template deleted successfully']
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to delete template'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete template: ' . $e->getMessage()
            ];
        }
    }
    
    private function extractVariables($content) {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        return array_unique(array_map('trim', $matches[1]));
    }
    
    private function validateTemplate($content) {
        $errors = [];
        
        if (empty(trim($content))) {
            $errors[] = 'Template content cannot be empty';
        }
        
        // Check for unmatched braces
        $openBraces = substr_count($content, '{{');
        $closeBraces = substr_count($content, '}}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unmatched template braces detected';
        }
        
        // Check for empty variables
        if (preg_match('/\{\{\s*\}\}/', $content)) {
            $errors[] = 'Empty variable placeholders found';
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
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
$templateAPI = new TemplateAPI();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Get authorization header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Validate user for write operations
$user = null;
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $user = $templateAPI->validateUser($token);
    if (!$user || $user['role'] !== 'Admin') {
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized access'
        ]);
        exit;
    }
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $response = $templateAPI->getTemplate($_GET['id']);
        } else {
            $response = $templateAPI->getAllTemplates();
        }
        break;
        
    case 'POST':
        $response = $templateAPI->createTemplate($input, $user['id']);
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            $response = $templateAPI->updateTemplate($_GET['id'], $input, $user['id']);
        } else {
            $response = [
                'success' => false,
                'error' => 'Template ID required'
            ];
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $response = $templateAPI->deleteTemplate($_GET['id'], $user['id']);
        } else {
            $response = [
                'success' => false,
                'error' => 'Template ID required'
            ];
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
