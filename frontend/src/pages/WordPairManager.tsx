import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { getWordPairsList, createWordPair, updateWordPair, deleteWordPair } from '../api/client'

interface WordPairItem {
  id: number
  placeholder: string
  original_value: string
  entity_type: string
  file_record?: { id: number; filename: string } | null
  created_at: string
}

const entityTypes = ['name', 'phone', 'email', 'id_number', 'address', 'bank_card', 'date', 'passport', 'ssn', 'medical', 'confidential', 'other']

export default function WordPairManager() {
  const { t, i18n } = useTranslation()
  const [pairs, setPairs] = useState<WordPairItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [showModal, setShowModal] = useState(false)
  const [editingPair, setEditingPair] = useState<WordPairItem | null>(null)
  const [form, setForm] = useState({ placeholder: '', original_value: '', entity_type: 'other' })
  const [message, setMessage] = useState<{ type: string; text: string } | null>(null)

  const fetchPairs = async () => {
    setLoading(true)
    try {
      const res = await getWordPairsList({ search, page })
      setPairs(res.data.data)
      setTotal(res.data.total)
      setLastPage(res.data.last_page)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchPairs() }, [page, search])

  const openCreate = () => {
    setEditingPair(null)
    setForm({ placeholder: '', original_value: '', entity_type: 'other' })
    setShowModal(true)
  }

  const openEdit = (pair: WordPairItem) => {
    setEditingPair(pair)
    setForm({ placeholder: pair.placeholder, original_value: pair.original_value, entity_type: pair.entity_type })
    setShowModal(true)
  }

  const handleSave = async () => {
    try {
      if (editingPair) {
        await updateWordPair(editingPair.id, form)
      } else {
        await createWordPair(form)
      }
      setShowModal(false)
      setMessage({ type: 'success', text: t('wordpairs.saveSuccess') })
      fetchPairs()
      setTimeout(() => setMessage(null), 3000)
    } catch {
      setMessage({ type: 'error', text: t('wordpairs.saveFailed') })
      setTimeout(() => setMessage(null), 3000)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm(t('wordpairs.deleteConfirm'))) return
    try {
      await deleteWordPair(id)
      setMessage({ type: 'success', text: t('wordpairs.deleteSuccess') })
      fetchPairs()
      setTimeout(() => setMessage(null), 3000)
    } catch {
      setMessage({ type: 'error', text: t('wordpairs.deleteFailed') })
      setTimeout(() => setMessage(null), 3000)
    }
  }

  const isZh = i18n.language === 'zh'

  return (
    <div className="p-8 max-w-6xl mx-auto">
      <header className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{t('wordpairs.title')}</h1>
          <p className="text-slate-500 text-sm mt-1">{t('wordpairs.subtitle')}</p>
        </div>
        <button onClick={openCreate}
          className="px-5 py-2.5 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700 transition-colors flex items-center gap-2">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
          {t('wordpairs.addPair')}
        </button>
      </header>

      {message && (
        <div className={`mb-4 px-4 py-3 rounded-lg text-sm flex items-center justify-between ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
          <span>{message.text}</span>
          <button onClick={() => setMessage(null)} className="font-bold hover:opacity-70">&times;</button>
        </div>
      )}

      {/* Search */}
      <div className="mb-6">
        <input
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          placeholder={t('wordpairs.searchPlaceholder')}
          className="w-full max-w-md px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none text-sm"
        />
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table className="w-full">
          <thead className="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider">
            <tr>
              <th className="px-6 py-3">{t('wordpairs.placeholder')}</th>
              <th className="px-6 py-3">{t('wordpairs.originalValue')}</th>
              <th className="px-6 py-3">{t('wordpairs.entityType')}</th>
              <th className="px-6 py-3">{t('wordpairs.relatedFile')}</th>
              <th className="px-6 py-3">{t('wordpairs.actions')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr><td colSpan={5} className="px-6 py-12 text-center text-slate-400">{t('common.loading')}</td></tr>
            ) : pairs.length === 0 ? (
              <tr><td colSpan={5} className="px-6 py-12 text-center text-slate-400">{t('common.noData')}</td></tr>
            ) : pairs.map((pair) => (
              <tr key={pair.id} className="hover:bg-slate-50/50 transition-colors">
                <td className="px-6 py-4">
                  <code className="text-rose-600 bg-rose-50 px-2 py-0.5 rounded text-sm font-mono">{pair.placeholder}</code>
                </td>
                <td className="px-6 py-4 text-sm text-indigo-700 font-semibold">{pair.original_value}</td>
                <td className="px-6 py-4">
                  <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                    {pair.entity_type}
                  </span>
                </td>
                <td className="px-6 py-4 text-sm text-slate-500">
                  {pair.file_record?.filename || (isZh ? '手動添加' : 'Manual')}
                </td>
                <td className="px-6 py-4">
                  <div className="flex gap-2">
                    <button onClick={() => openEdit(pair)}
                      className="px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                      {t('wordpairs.edit')}
                    </button>
                    <button onClick={() => handleDelete(pair.id)}
                      className="px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                      {t('common.delete')}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {/* Pagination */}
        {lastPage > 1 && (
          <div className="px-6 py-3 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
            <span className="text-xs text-slate-500">
              {t('wordpairs.showing', { current: pairs.length, total })}
            </span>
            <div className="flex gap-1">
              <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
                className="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">&lt;</button>
              {Array.from({ length: lastPage }, (_, i) => i + 1).slice(
                Math.max(0, page - 3), Math.min(lastPage, page + 2)
              ).map((p) => (
                <button key={p} onClick={() => setPage(p)}
                  className={`px-3 py-1 text-sm border rounded ${p === page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50'}`}>
                  {p}
                </button>
              ))}
              <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
                className="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">&gt;</button>
            </div>
          </div>
        )}
      </div>

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onClick={() => setShowModal(false)}>
          <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-slate-800 mb-4">
              {editingPair ? t('wordpairs.editPair') : t('wordpairs.addPair')}
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('wordpairs.placeholder')}</label>
                <input value={form.placeholder} onChange={(e) => setForm({ ...form, placeholder: e.target.value })}
                  placeholder="[[NAME_001]]"
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none text-sm font-mono" />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('wordpairs.originalValue')}</label>
                <input value={form.original_value} onChange={(e) => setForm({ ...form, original_value: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">{t('wordpairs.entityType')}</label>
                <select value={form.entity_type} onChange={(e) => setForm({ ...form, entity_type: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none text-sm">
                  {entityTypes.map((type) => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-3 mt-6">
              <button onClick={() => setShowModal(false)}
                className="px-4 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                {t('common.cancel')}
              </button>
              <button onClick={handleSave}
                disabled={!form.placeholder || !form.original_value}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                {t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
