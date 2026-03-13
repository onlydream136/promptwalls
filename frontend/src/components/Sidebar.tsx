import { useLocation, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'

const navItems = [
  {
    path: '/',
    key: 'dashboard',
    icon: (
      <path
        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
      />
    ),
  },
  {
    path: '/files',
    key: 'fileManager',
    icon: (
      <path
        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
      />
    ),
  },
  {
    path: '/reidentify',
    key: 'reidentification',
    icon: (
      <path
        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
      />
    ),
  },
  {
    path: '/settings',
    key: 'settings',
    icon: (
      <>
        <path
          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
        />
        <path
          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
        />
      </>
    ),
  },
]

export default function Sidebar() {
  const { t } = useTranslation()
  const location = useLocation()

  return (
    <aside className="w-64 bg-brand-navy text-white flex flex-col shrink-0">
      <div className="p-6">
        <div className="flex items-center gap-2 mb-8">
          <div className="w-8 h-8 bg-brand-teal rounded flex items-center justify-center font-bold">
            P
          </div>
          <span className="text-lg font-bold tracking-tight">PromptWalls</span>
        </div>
        <nav className="space-y-1">
          {navItems.map((item) => {
            const isActive = location.pathname === item.path
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex items-center gap-3 px-4 py-3 rounded transition-colors ${
                  isActive
                    ? 'bg-brand-blue/10 text-brand-blue border-l-4 border-brand-blue rounded-r'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800'
                }`}
              >
                <svg
                  className="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  {item.icon}
                </svg>
                {t(`nav.${item.key}`)}
              </Link>
            )
          })}
        </nav>
      </div>
      <div className="mt-auto p-6 bg-slate-900/50">
        <div className="flex items-center gap-2 mb-2">
          <div className="w-2 h-2 rounded-full bg-teal-500 animate-pulse" />
          <span className="text-xs text-slate-400">{t('dashboard.engineOnline')}</span>
        </div>
        <div className="text-[10px] text-slate-500 uppercase tracking-widest">
          {t('common.version')} 1.0.0
        </div>
      </div>
    </aside>
  )
}
