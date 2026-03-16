import { useState, useEffect, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../auth/AuthContext'
import api from '../api/client'

interface UserItem {
  id: number
  username: string
  name: string
  role: 'admin' | 'operator'
  status: 'active' | 'inactive'
  last_login_at: string | null
  created_at: string
}

interface UserForm {
  username: string
  name: string
  password: string
  role: 'admin' | 'operator'
  status: 'active' | 'inactive'
}

const emptyForm: UserForm = { username: '', name: '', password: '', role: 'operator', status: 'active' }

export default function UserManagement() {
  const { t } = useTranslation()
  const { user: currentUser } = useAuth()
  const [users, setUsers] = useState<UserItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)

  // Modal state
  const [showModal, setShowModal] = useState(false)
  const [editingUser, setEditingUser] = useState<UserItem | null>(null)
  const [form, setForm] = useState<UserForm>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/users', { params: { search, page, per_page: 10 } })
      setUsers(res.data.data)
      setTotal(res.data.total)
      setLastPage(res.data.last_page)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [search, page])

  useEffect(() => { fetchUsers() }, [fetchUsers])

  const openCreate = () => {
    setEditingUser(null)
    setForm(emptyForm)
    setShowModal(true)
  }

  const openEdit = (user: UserItem) => {
    setEditingUser(user)
    setForm({ username: user.username, name: user.name, password: '', role: user.role, status: user.status })
    setShowModal(true)
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      if (editingUser) {
        const data: any = { username: form.username, name: form.name, role: form.role, status: form.status }
        if (form.password) data.password = form.password
        await api.put(`/users/${editingUser.id}`, data)
      } else {
        await api.post('/users', form)
      }
      setShowModal(false)
      setMessage({ type: 'success', text: t('users.saveSuccess') })
      fetchUsers()
    } catch (err: any) {
      setMessage({ type: 'error', text: err?.response?.data?.message || 'Error' })
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (user: UserItem) => {
    if (!confirm(t('users.deleteConfirm'))) return
    try {
      await api.delete(`/users/${user.id}`)
      setMessage({ type: 'success', text: t('users.deleteSuccess') })
      fetchUsers()
    } catch (err: any) {
      setMessage({ type: 'error', text: err?.response?.data?.message || 'Error' })
    }
  }

  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [message])

  const roleStyle: Record<string, string> = {
    admin: 'bg-red-100 text-red-700',
    operator: 'bg-blue-100 text-blue-700',
  }

  return (
    <div className="p-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-2xl font-bold text-slate-800">{t('users.title')}</h2>
          <p className="text-sm text-slate-500 mt-1">{t('users.subtitle')}</p>
        </div>
        <button onClick={openCreate} className="px-4 py-2 bg-brand-orange text-white rounded-lg hover:bg-orange-600 transition-colors flex items-center gap-2 font-medium shadow">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          {t('users.addUser')}
        </button>
      </div>

      {/* Message */}
      {message && (
        <div className={`mb-4 p-3 rounded-lg text-sm ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
          {message.text}
        </div>
      )}

      {/* Search */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div className="p-4 border-b border-slate-100">
          <div className="relative max-w-md">
            <svg className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              placeholder={t('users.searchPlaceholder')}
              className="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-brand-orange/20 focus:border-brand-orange outline-none text-sm"
            />
          </div>
        </div>

        {/* Table */}
        <table className="w-full">
          <thead>
            <tr className="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
              <th className="px-6 py-3">{t('users.username')}</th>
              <th className="px-6 py-3">{t('users.fullName')}</th>
              <th className="px-6 py-3">{t('users.role')}</th>
              <th className="px-6 py-3">{t('users.status')}</th>
              <th className="px-6 py-3">{t('users.lastLogin')}</th>
              <th className="px-6 py-3">{t('users.actions')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-50">
            {loading ? (
              <tr><td colSpan={6} className="text-center py-12 text-slate-400">{t('common.loading')}</td></tr>
            ) : users.length === 0 ? (
              <tr><td colSpan={6} className="text-center py-12 text-slate-400">{t('common.noData')}</td></tr>
            ) : users.map((user) => (
              <tr key={user.id} className="hover:bg-slate-50/50 transition-colors">
                <td className="px-6 py-4 text-sm font-medium text-slate-800">{user.username}</td>
                <td className="px-6 py-4 text-sm text-slate-600">{user.name}</td>
                <td className="px-6 py-4">
                  <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${roleStyle[user.role] || ''}`}>
                    {t(`users.${user.role}`)}
                  </span>
                </td>
                <td className="px-6 py-4">
                  <span className="flex items-center gap-1.5 text-sm">
                    <span className={`w-2 h-2 rounded-full ${user.status === 'active' ? 'bg-green-500' : 'bg-slate-300'}`} />
                    {t(`users.${user.status}`)}
                  </span>
                </td>
                <td className="px-6 py-4 text-sm text-slate-500">
                  {user.last_login_at || '-'}
                </td>
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2">
                    <button onClick={() => openEdit(user)} className="p-1.5 text-slate-400 hover:text-brand-orange transition-colors">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                      </svg>
                    </button>
                    {user.id !== currentUser?.id && (
                      <button onClick={() => handleDelete(user)} className="p-1.5 text-slate-400 hover:text-red-500 transition-colors">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {/* Pagination */}
        {total > 0 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-slate-100">
            <span className="text-sm text-brand-orange">
              {t('users.showing', { from: (page - 1) * 10 + 1, to: Math.min(page * 10, total), total })}
            </span>
            <div className="flex items-center gap-1">
              <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
                className="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">
                &lt;
              </button>
              {Array.from({ length: lastPage }, (_, i) => i + 1).slice(0, 5).map((p) => (
                <button key={p} onClick={() => setPage(p)}
                  className={`px-3 py-1 text-sm rounded ${p === page ? 'bg-brand-orange text-white' : 'border border-slate-200 hover:bg-slate-50'}`}>
                  {p}
                </button>
              ))}
              <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
                className="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">
                &gt;
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onClick={() => setShowModal(false)}>
          <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-slate-800 mb-4">
              {editingUser ? t('users.editUser') : t('users.createUser')}
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('users.username')}</label>
                <input value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-brand-orange/20 focus:border-brand-orange outline-none text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('users.fullName')}</label>
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-brand-orange/20 focus:border-brand-orange outline-none text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  {t('login.password')} {editingUser && <span className="text-slate-400 font-normal">({t('users.passwordHint')})</span>}
                </label>
                <input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-brand-orange/20 focus:border-brand-orange outline-none text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('users.status')}</label>
                <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as any })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-brand-orange/20 focus:border-brand-orange outline-none text-sm">
                  <option value="active">{t('users.active')}</option>
                  <option value="inactive">{t('users.inactive')}</option>
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-3 mt-6">
              <button onClick={() => setShowModal(false)}
                className="px-4 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                {t('common.cancel')}
              </button>
              <button onClick={handleSave} disabled={saving}
                className="px-4 py-2 text-sm bg-brand-orange text-white rounded-lg hover:bg-orange-600 transition-colors disabled:opacity-50">
                {saving ? t('common.loading') : t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
