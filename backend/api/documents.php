<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
require_once '../vendor/autoload.php'; // For PDF/DOCX generation libraries

class DocumentAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getAllDocuments($userId, $userRole) {
        try {
            // Admin and Approver can see all documents, others only their own
            if (in_array($userRole, ['Admin', 'Approver'])) {
                $query = "SELECT d.*, u.name as created_by_name, a.name as approved_by_name 
                         FROM documents d 
                         LEFT JOIN users u ON d.created_by = u.id 
                         LEFT JOIN users a ON d.approved_by = a.id 
                         ORDER BY d.created_at DESC";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "SELECT d.*, u.name as created_by_name, a.name as approved_by_name 
                         FROM documents d 
                         LEFT JOIN users u ON d.created_by = u.id 
                         LEFT JOIN users a ON d.approved_by = a.id 
                         WHERE d.created_by = :user_id 
                         ORDER BY d.created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $userId);
            }
            
            $stmt->execute();
            
            $documents = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['filled_data'] = json_decode($row['filled_data'], true);
                $documents[] = $row;
            }
            
            return [
                'success' => true,
                'data' => $documents
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch documents: ' . $e->getMessage()
            ];
        }
    }
    
    public function getDocument($id, $userId, $userRole) {
        try {
            // Check access permissions
            if (in_array($userRole, ['Admin', 'Approver'])) {
                $query = "SELECT d.*, t.content as template_content, u.name as created_by_name 
                         FROM documents d 
                         JOIN templates t ON d.template_id = t.id 
                         LEFT JOIN users u ON d.created_by = u.id 
                         WHERE d.id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
            } else {
                $query = "SELECT d.*, t.content as template_content, u.name as created_by_name 
                         FROM documents d 
                         JOIN templates t ON d.template_id = t.id 
                         LEFT JOIN users u ON d.created_by = u.id 
                         WHERE d.id = :id AND d.created_by = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':user_id', $userId);
            }
            
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $document = $stmt->fetch(PDO::FETCH_ASSOC);
                $document['filled_data'] = json_decode($document['filled_data'], true);
                
                return [
                    'success' => true,
                    'data' => $document
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Document not found or access denied'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch document: ' . $e->getMessage()
            ];
        }
    }
    
    public function createDocument($data, $userId) {
        try {
            // Get template
            $query = "SELECT * FROM templates WHERE id = :template_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':template_id', $data['template_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error' => 'Template not found'
                ];
            }
            
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Auto-generate order number if not provided
            if (!isset($data['filled_data']['order_number']) || empty($data['filled_data']['order_number'])) {
                $data['filled_data']['order_number'] = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Auto-generate date if not provided
            if (!isset($data['filled_data']['date']) || empty($data['filled_data']['date'])) {
                $data['filled_data']['date'] = date('Y-m-d');
            }
            
            // Create document
            $query = "INSERT INTO documents (template_id, template_name, filled_data, created_by) 
                     VALUES (:template_id, :template_name, :filled_data, :created_by)";
            $stmt = $this->conn->prepare($query);
            
            $filledDataJson = json_encode($data['filled_data']);
            
            $stmt->bindParam(':template_id', $data['template_id']);
            $stmt->bindParam(':template_name', $template['name']);
            $stmt->bindParam(':filled_data', $filledDataJson);
            $stmt->bindParam(':created_by', $userId);
            
            if ($stmt->execute()) {
                $documentId = $this->conn->lastInsertId();
                
                // Log the activity
                $this->logActivity($userId, $documentId, null, 'Document Created', 'Created document: ' . $template['name']);
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $documentId,
                        'message' => 'Document created successfully'
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to create document'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create document: ' . $e->getMessage()
            ];
        }
    }
    
    public function getDocumentPreview($id, $userId, $userRole) {
        try {
            $documentResult = $this->getDocument($id, $userId, $userRole);
            
            if (!$documentResult['success']) {
                return $documentResult;
            }
            
            $document = $documentResult['data'];
            $preview = $this->fillTemplate($document['template_content'], $document['filled_data']);
            
            return [
                'success' => true,
                'data' => [
                    'preview' => $preview,
                    'document' => $document
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate preview: ' . $e->getMessage()
            ];
        }
    }
    
    public function exportDocument($id, $format, $userId, $userRole) {
        try {
            $documentResult = $this->getDocument($id, $userId, $userRole);
            
            if (!$documentResult['success']) {
                return $documentResult;
            }
            
            $document = $documentResult['data'];
            $content = $this->fillTemplate($document['template_content'], $document['filled_data']);
            
            $filename = $this->sanitizeFilename($document['template_name']) . '_' . date('Y-m-d_H-i-s');
            
            if ($format === 'pdf') {
                $filePath = $this->generatePDF($content, $filename);
            } elseif ($format === 'docx') {
                $filePath = $this->generateDOCX($content, $filename);
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid export format'
                ];
            }
            
            if ($filePath) {
                // Update document with export URL
                $this->updateExportUrl($id, $format, $filePath);
                
                // Log the activity
                $this->logActivity($userId, $id, null, 'Document Exported', "Exported document as $format");
                
                return [
                    'success' => true,
                    'data' => [
                        'download_url' => $filePath,
                        'filename' => basename($filePath)
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to generate export file'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function uploadSignature($documentId, $userId, $userRole) {
        try {
            // Check if user has access to this document
            $documentResult = $this->getDocument($documentId, $userId, $userRole);
            if (!$documentResult['success']) {
                return $documentResult;
            }
            
            if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'error' => 'No file uploaded or upload error'
                ];
            }
            
            $file = $_FILES['signature'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'
                ];
            }
            
            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                return [
                    'success' => false,
                    'error' => 'File size too large. Maximum 5MB allowed.'
                ];
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/signatures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'signature_' . $documentId . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Update document with signature path
                $query = "UPDATE documents SET digital_signature = :signature_path WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':signature_path', $filename);
                $stmt->bindParam(':id', $documentId);
                
                if ($stmt->execute()) {
                    // Log the activity
                    $this->logActivity($userId, $documentId, null, 'Signature Uploaded', 'Digital signature uploaded');
                    
                    return [
                        'success' => true,
                        'data' => [
                            'signature_url' => 'uploads/signatures/' . $filename,
                            'message' => 'Signature uploaded successfully'
                        ]
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => 'Failed to upload signature'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Signature upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function fillTemplate($content, $data) {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Replace any remaining placeholders with empty brackets
        $content = preg_replace('/\{\{[^}]+\}\}/', '[___]', $content);
        
        return $content;
    }
    
    private function generatePDF($content, $filename) {
        try {
            // Simple PDF generation using TCPDF or similar
            // For now, we'll create a simple text file as placeholder
            $uploadDir = '../uploads/exports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $filename . '.pdf';
            
            // In a real implementation, use TCPDF, FPDF, or similar library
            // For demo purposes, we'll create a text file
            file_put_contents($filePath, $content);
            
            return 'uploads/exports/' . $filename . '.pdf';
            
        } catch (Exception $e) {
            error_log("PDF generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateDOCX($content, $filename) {
        try {
            // Simple DOCX generation using PhpWord or similar
            $uploadDir = '../uploads/exports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $filename . '.docx';
            
            // In a real implementation, use PhpWord library
            // For demo purposes, we'll create a text file
            file_put_contents($filePath, $content);
            
            return 'uploads/exports/' . $filename . '.docx';
            
        } catch (Exception $e) {
            error_log("DOCX generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateExportUrl($documentId, $format, $filePath) {
        try {
            $field = $format === 'pdf' ? 'export_pdf_url' : 'export_docx_url';
            $query = "UPDATE documents SET $field = :file_path WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':file_path', $filePath);
            $stmt->bindParam(':id', $documentId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to update export URL: " . $e->getMessage());
        }
    }
    
    private function sanitizeFilename($filename) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
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
$documentAPI = new DocumentAPI();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Get authorization header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Validate user
$user = $documentAPI->validateUser($token);
if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check permissions for document creation
if ($method === 'POST' && !in_array($user['role'], ['Admin', 'Encoder'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient permissions to create documents'
    ]);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'preview':
                        $response = $documentAPI->getDocumentPreview($_GET['id'], $user['id'], $user['role']);
                        break;
                    case 'export':
                        $format = $_GET['format'] ?? 'pdf';
                        $response = $documentAPI->exportDocument($_GET['id'], $format, $user['id'], $user['role']);
                        break;
                    default:
                        $response = $documentAPI->getDocument($_GET['id'], $user['id'], $user['role']);
                }
            } else {
                $response = $documentAPI->getDocument($_GET['id'], $user['id'], $user['role']);
            }
        } else {
            $response = $documentAPI->getAllDocuments($user['id'], $user['role']);
        }
        break;
        
    case 'POST':
        if (isset($_GET['action']) && $_GET['action'] === 'upload_signature') {
            $documentId = $_GET['document_id'] ?? null;
            if ($documentId) {
                $response = $documentAPI->uploadSignature($documentId, $user['id'], $user['role']);
            } else {
                $response = [
                    'success' => false,
                    'error' => 'Document ID required'
                ];
            }
        } else {
            $response = $documentAPI->createDocument($input, $user['id']);
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
