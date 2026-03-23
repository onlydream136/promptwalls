import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import Sidebar from './components/Sidebar'
import Header from './components/Header'
import Dashboard from './pages/Dashboard'
import FileManager from './pages/FileManager'
import ReIdentification from './pages/ReIdentification'
import Settings from './pages/Settings'
import UserManagement from './pages/UserManagement'
import WordPairManager from './pages/WordPairManager'
import Login from './pages/Login'

function ProtectedLayout() {
  const { isAdmin } = useAuth()

  return (
    <div className="flex h-screen overflow-hidden bg-brand-bg font-sans text-slate-900 antialiased">
      <Sidebar />
      <main className="flex-1 flex flex-col min-w-0 overflow-hidden">
        <Header />
        <div className="flex-1 overflow-y-auto">
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/files" element={<FileManager />} />
            <Route path="/reidentify" element={<ReIdentification />} />
            <Route path="/wordpairs" element={<WordPairManager />} />
            {isAdmin && <Route path="/users" element={<UserManagement />} />}
            {isAdmin && <Route path="/settings" element={<Settings />} />}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </div>
      </main>
    </div>
  )
}

export default function App() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="w-8 h-8 border-4 border-brand-orange border-t-transparent rounded-full animate-spin" />
      </div>
    )
  }

  if (!user) {
    return <Login />
  }

  return <ProtectedLayout />
}
