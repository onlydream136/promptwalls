import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

const titleMap: Record<string, string> = {
  '/': 'dashboard.title',
  '/files': 'files.title',
  '/reidentify': 'reidentify.title',
  '/users': 'users.title',
  '/settings': 'settings.title',
}

export default function Header() {
  const { t, i18n } = useTranslation()
  const location = useLocation()
  const { user } = useAuth()

  const titleKey = titleMap[location.pathname] || 'dashboard.title'

  const toggleLang = () => {
    const next = i18n.language === 'zh' ? 'en' : 'zh'
    i18n.changeLanguage(next)
    localStorage.setItem('lang', next)
  }

  return (
    <header className="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-10 shrink-0">
      <h1 className="text-xl font-semibold text-slate-800">{t(titleKey)}</h1>
      <div className="flex items-center gap-4">
        <button
          onClick={toggleLang}
          className="px-3 py-1 text-xs font-semibold border border-slate-300 rounded hover:bg-slate-50 transition-colors"
        >
          {i18n.language === 'zh' ? 'EN' : '繁'}
        </button>
        <button className="p-2 text-slate-400 hover:text-brand-blue transition-colors">
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
            />
          </svg>
        </button>
        <div className="flex items-center gap-3 pl-4 border-l border-slate-200">
          <div className="text-right">
            <p className="text-xs font-medium text-slate-900">{user?.name}</p>
            <p className="text-[10px] text-slate-500">{user?.role === 'admin' ? t('users.admin') : t('users.operator')}</p>
          </div>
          <div className="w-10 h-10 bg-brand-orange/10 rounded-full flex items-center justify-center text-brand-orange font-bold border border-brand-orange/20">
            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
          </div>
        </div>
      </div>
    </header>
  )
}
