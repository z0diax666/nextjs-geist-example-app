'use client'

import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { User } from '@/lib/types'

interface AuthContextType {
  user: User | null
  login: (email: string, password: string) => Promise<boolean>
  logout: () => void
  isLoading: boolean
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Check for stored auth token on mount
    const storedUser = localStorage.getItem('auth_user')
    if (storedUser) {
      try {
        setUser(JSON.parse(storedUser))
      } catch (error) {
        localStorage.removeItem('auth_user')
      }
    }
    setIsLoading(false)
  }, [])

  const login = async (email: string, password: string): Promise<boolean> => {
    setIsLoading(true)
    
    try {
      const response = await fetch('http://localhost/office-order-backend/api/auth', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'login',
          email,
          password
        })
      })

      const data = await response.json()

      if (data.success && data.data.user) {
        const user: User = {
          id: data.data.user.id.toString(),
          name: data.data.user.name,
          email: data.data.user.email,
          role: data.data.user.role
        }
        
        setUser(user)
        localStorage.setItem('auth_user', JSON.stringify(user))
        localStorage.setItem('auth_token', data.data.token)
        setIsLoading(false)
        return true
      }
      
      setIsLoading(false)
      return false
    } catch (error) {
      console.error('Login error:', error)
      setIsLoading(false)
      return false
    }
  }

  const logout = () => {
    setUser(null)
    localStorage.removeItem('auth_user')
    localStorage.removeItem('auth_token')
  }

  return (
    <AuthContext.Provider value={{ user, login, logout, isLoading }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

// Role-based access control hook
export function useRoleAccess() {
  const { user } = useAuth()
  
  const hasRole = (requiredRole: User['role'] | User['role'][]): boolean => {
    if (!user) return false
    
    if (Array.isArray(requiredRole)) {
      return requiredRole.includes(user.role)
    }
    
    return user.role === requiredRole
  }

  const canManageTemplates = (): boolean => {
    return hasRole('Admin')
  }

  const canCreateDocuments = (): boolean => {
    return hasRole(['Admin', 'Encoder'])
  }

  const canApproveDocuments = (): boolean => {
    return hasRole(['Admin', 'Approver'])
  }

  const canViewAuditLogs = (): boolean => {
    return hasRole(['Admin', 'Approver'])
  }

  return {
    user,
    hasRole,
    canManageTemplates,
    canCreateDocuments,
    canApproveDocuments,
    canViewAuditLogs,
  }
}
