'use client'

import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { useAuth, useRoleAccess } from '@/hooks/useAuth'
import { mockData, templateUtils } from '@/lib/api'
import { Template, type FormData } from '@/lib/types'

export default function Dashboard() {
  const { user, logout } = useAuth()
  const { canManageTemplates, canCreateDocuments } = useRoleAccess()
  
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null)
  const [formData, setFormData] = useState<FormData>({})
  const [templates, setTemplates] = useState(mockData.templates)
  const [newTemplate, setNewTemplate] = useState({ name: '', content: '' })
  const [activeTab, setActiveTab] = useState('generator')
  const [signatureFile, setSignatureFile] = useState<File | null>(null)

  // Generate preview by replacing variables
  const generatePreview = () => {
    if (!selectedTemplate) return ''
    return templateUtils.fillTemplate(selectedTemplate.content, formData)
  }

  // Handle form input changes
  const handleInputChange = (variable: string, value: string) => {
    setFormData(prev => ({ ...prev, [variable]: value }))
  }

  // Add new template
  const handleAddTemplate = () => {
    if (newTemplate.name && newTemplate.content) {
      const validation = templateUtils.validateTemplate(newTemplate.content)
      if (!validation.isValid) {
        alert('Template validation failed: ' + validation.errors.join(', '))
        return
      }

      const variables = templateUtils.extractVariables(newTemplate.content)
      const template: Template = {
        id: templates.length + 1,
        name: newTemplate.name,
        content: newTemplate.content,
        variables,
        version: 1,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      setTemplates(prev => [...prev, template])
      setNewTemplate({ name: '', content: '' })
    }
  }

  // Delete template
  const handleDeleteTemplate = (id: number) => {
    if (confirm('Are you sure you want to delete this template?')) {
      setTemplates(prev => prev.filter(t => t.id !== id))
      if (selectedTemplate?.id === id) {
        setSelectedTemplate(null)
        setFormData({})
      }
    }
  }

  // Export functions (mock implementation)
  const exportToPDF = () => {
    if (!selectedTemplate) return
    
    // In a real implementation, this would call the backend API
    const blob = new Blob([generatePreview()], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${selectedTemplate.name.replace(/\s+/g, '_')}.txt`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    
    alert('PDF export functionality would generate a proper PDF file')
  }

  const exportToWord = () => {
    if (!selectedTemplate) return
    
    // In a real implementation, this would call the backend API
    const blob = new Blob([generatePreview()], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${selectedTemplate.name.replace(/\s+/g, '_')}.txt`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    
    alert('Word export functionality would generate a proper DOCX file')
  }

  // Handle signature file upload
  const handleSignatureUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      if (file.size > 5 * 1024 * 1024) { // 5MB limit
        alert('File size must be less than 5MB')
        return
      }
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file')
        return
      }
      setSignatureFile(file)
    }
  }

  if (!user) return null

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center py-6">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Office Order Generator</h1>
              <p className="text-gray-600">Internal Document Management System</p>
            </div>
            <div className="flex items-center space-x-4">
              <Badge variant="secondary">{user.role}</Badge>
              <span className="text-gray-700">{user.name}</span>
              <Button variant="outline" size="sm" onClick={logout}>
                Logout
              </Button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="generator">Document Generator</TabsTrigger>
            <TabsTrigger value="templates">Template Manager</TabsTrigger>
            <TabsTrigger value="history">Document History</TabsTrigger>
          </TabsList>

          {/* Document Generator Tab */}
          <TabsContent value="generator" className="space-y-6">
            {canCreateDocuments() ? (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Form Section */}
                <div className="space-y-6">
                  <Card>
                    <CardHeader>
                      <CardTitle>Select Template</CardTitle>
                      <CardDescription>Choose a template to generate your office order</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select onValueChange={(value) => {
                        const template = templates.find(t => t.id === parseInt(value))
                        setSelectedTemplate(template || null)
                        setFormData({})
                      }}>
                        <SelectTrigger>
                          <SelectValue placeholder="Select a template" />
                        </SelectTrigger>
                        <SelectContent>
                          {templates.map(template => (
                            <SelectItem key={template.id} value={template.id.toString()}>
                              {template.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </CardContent>
                  </Card>

                  {selectedTemplate && (
                    <Card>
                      <CardHeader>
                        <CardTitle>Fill Document Details</CardTitle>
                        <CardDescription>Enter the required information for your office order</CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        {selectedTemplate.variables.map(variable => (
                          <div key={variable} className="space-y-2">
                            <Label htmlFor={variable} className="capitalize">
                              {variable.replace(/_/g, ' ')}
                              {templateUtils.getRequiredFields(selectedTemplate.content).includes(variable) && (
                                <span className="text-red-500 ml-1">*</span>
                              )}
                            </Label>
                            <Input
                              id={variable}
                              placeholder={`Enter ${variable.replace(/_/g, ' ')}`}
                              value={formData[variable] || ''}
                              onChange={(e) => handleInputChange(variable, e.target.value)}
                              required={templateUtils.getRequiredFields(selectedTemplate.content).includes(variable)}
                            />
                          </div>
                        ))}
                        
                        <Separator className="my-6" />
                        
                        <div className="space-y-4">
                          <h4 className="font-medium">Digital Signature (Optional)</h4>
                          <Input 
                            type="file" 
                            accept="image/*" 
                            onChange={handleSignatureUpload}
                          />
                          {signatureFile && (
                            <p className="text-sm text-green-600">
                              Signature uploaded: {signatureFile.name}
                            </p>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  )}
                </div>

                {/* Preview Section */}
                <div className="space-y-6">
                  <Card>
                    <CardHeader>
                      <CardTitle>Live Preview</CardTitle>
                      <CardDescription>Real-time preview of your office order</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="bg-white border border-gray-200 rounded-lg p-6 min-h-96">
                        {selectedTemplate ? (
                          <div>
                            <pre className="whitespace-pre-wrap text-sm font-mono leading-relaxed">
                              {generatePreview()}
                            </pre>
                            {signatureFile && (
                              <div className="mt-6 pt-4 border-t border-gray-200">
                                <p className="text-sm text-gray-600 mb-2">Digital Signature:</p>
                                <div className="w-32 h-16 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                                  <span className="text-xs text-gray-500">[Signature Image]</span>
                                </div>
                              </div>
                            )}
                          </div>
                        ) : (
                          <div className="flex items-center justify-center h-full text-gray-500">
                            Select a template to see preview
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>

                  {selectedTemplate && (
                    <Card>
                      <CardHeader>
                        <CardTitle>Export Options</CardTitle>
                        <CardDescription>Download your completed office order</CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                          <Button onClick={exportToPDF} className="w-full">
                            Export as PDF
                          </Button>
                          <Button onClick={exportToWord} variant="outline" className="w-full">
                            Export as Word
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  )}
                </div>
              </div>
            ) : (
              <Card>
                <CardContent className="text-center py-12">
                  <h3 className="text-lg font-medium text-gray-900">Access Restricted</h3>
                  <p className="text-gray-600 mt-2">You don't have permission to create documents.</p>
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Template Manager Tab */}
          <TabsContent value="templates" className="space-y-6">
            {canManageTemplates() ? (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Create New Template */}
                <Card>
                  <CardHeader>
                    <CardTitle>Create New Template</CardTitle>
                    <CardDescription>Add a new office order template with variables</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="template-name">Template Name</Label>
                      <Input
                        id="template-name"
                        placeholder="Enter template name"
                        value={newTemplate.name}
                        onChange={(e) => setNewTemplate(prev => ({ ...prev, name: e.target.value }))}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="template-content">Template Content</Label>
                      <Textarea
                        id="template-content"
                        placeholder="Enter template content with variables like {{employee_name}}"
                        rows={10}
                        value={newTemplate.content}
                        onChange={(e) => setNewTemplate(prev => ({ ...prev, content: e.target.value }))}
                      />
                    </div>
                    <div className="text-sm text-gray-600">
                      <p>Use double curly braces for variables: <code>{'{{variable_name}}'}</code></p>
                      <p>Detected variables: {templateUtils.extractVariables(newTemplate.content).join(', ') || 'None'}</p>
                      {newTemplate.content && (
                        <div className="mt-2">
                          {templateUtils.validateTemplate(newTemplate.content).isValid ? (
                            <span className="text-green-600">✓ Template is valid</span>
                          ) : (
                            <div className="text-red-600">
                              <span>✗ Template has errors:</span>
                              <ul className="list-disc list-inside ml-4">
                                {templateUtils.validateTemplate(newTemplate.content).errors.map((error, index) => (
                                  <li key={index}>{error}</li>
                                ))}
                              </ul>
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                    <Button 
                      onClick={handleAddTemplate} 
                      className="w-full"
                      disabled={!newTemplate.name || !newTemplate.content || !templateUtils.validateTemplate(newTemplate.content).isValid}
                    >
                      Add Template
                    </Button>
                  </CardContent>
                </Card>

                {/* Existing Templates */}
                <Card>
                  <CardHeader>
                    <CardTitle>Existing Templates</CardTitle>
                    <CardDescription>Manage your office order templates</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {templates.map(template => (
                      <div key={template.id} className="border border-gray-200 rounded-lg p-4">
                        <div className="flex justify-between items-start">
                          <div className="flex-1">
                            <h4 className="font-medium">{template.name}</h4>
                            <p className="text-sm text-gray-600 mt-1">
                              Variables: {template.variables.join(', ')}
                            </p>
                            <p className="text-xs text-gray-500 mt-1">
                              Version {template.version} • Created {new Date(template.created_at).toLocaleDateString()}
                            </p>
                          </div>
                          <Badge variant="outline" className="ml-2">
                            v{template.version}
                          </Badge>
                        </div>
                        <div className="flex space-x-2 mt-3">
                          <Button size="sm" variant="outline">Edit</Button>
                          <Button 
                            size="sm" 
                            variant="outline" 
                            onClick={() => handleDeleteTemplate(template.id)}
                          >
                            Delete
                          </Button>
                        </div>
                      </div>
                    ))}
                  </CardContent>
                </Card>
              </div>
            ) : (
              <Card>
                <CardContent className="text-center py-12">
                  <h3 className="text-lg font-medium text-gray-900">Access Restricted</h3>
                  <p className="text-gray-600 mt-2">Only administrators can manage templates.</p>
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Document History Tab */}
          <TabsContent value="history" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Document History</CardTitle>
                <CardDescription>View previously generated office orders and audit logs</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {mockData.documents.map(doc => (
                    <div key={doc.id} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className="font-medium">{doc.template_name}</h4>
                          <p className="text-sm text-gray-600">
                            Created by {doc.created_by} on {new Date(doc.created_at).toLocaleDateString()}
                          </p>
                          <Badge variant="outline" className="mt-2">
                            {doc.status}
                          </Badge>
                        </div>
                        <div className="flex space-x-2">
                          <Button size="sm" variant="outline">View</Button>
                          <Button size="sm" variant="outline">Download</Button>
                        </div>
                      </div>
                    </div>
                  ))}
                  
                  {mockData.documents.length === 0 && (
                    <div className="text-center py-12 text-gray-500">
                      <p>No documents generated yet</p>
                      <p className="text-sm mt-2">Start by creating a document in the Generator tab</p>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </main>
    </div>
  )
}
