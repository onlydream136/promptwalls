import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { getDashboardStats, getDashboardRecent, getDashboardMonitor } from '../api/client'

interface Stats {
  total_files: number
  sensitive_detected: number
  desensitized: number
  no_risk: number
  processing: number
}

interface RecentFile {
  id: number
  filename: string
  file_type: string
  status: string
  created_at: string
  latest_action: string | null
}

interface Monitor {
  path: string
  status: string
  pending_files: number
  last_scan: string | null
  memory_usage: number
}

const statusColors: Record<string, string> = {
  pending: 'bg-slate-100 text-slate-600',
  ocr_scanning: 'bg-brand-blue/10 text-brand-blue',
  assessing: 'bg-amber-100 text-amber-700',
  sensitive: 'bg-red-100 text-red-700',
  no_risk: 'bg-slate-100 text-slate-600',
  desensitized: 'bg-teal-100 text-teal-700',
}

export default function Dashboard() {
  const { t } = useTranslation()
  const [stats, setStats] = useState<Stats | null>(null)
  const [recent, setRecent] = useState<RecentFile[]>([])
  const [monitor, setMonitor] = useState<Monitor | null>(null)

  useEffect(() => {
    getDashboardStats().then((r) => setStats(r.data)).catch(() => {})
    getDashboardRecent().then((r) => setRecent(r.data)).catch(() => {})
    getDashboardMonitor().then((r) => setMonitor(r.data)).catch(() => {})
  }, [])

  const statCards = [
    {
      label: t('dashboard.totalFiles'),
      value: stats?.total_files ?? 0,
      badge: t('dashboard.monthly'),
      badgeClass: 'text-slate-400',
      iconBg: 'bg-blue-50 text-brand-blue',
    },
    {
      label: t('dashboard.sensitiveDetected'),
      value: stats?.sensitive_detected ?? 0,
      badge: t('dashboard.warning'),
      badgeClass: 'text-amber-600',
      iconBg: 'bg-amber-50 text-amber-600',
    },
    {
      label: t('dashboard.desensitized'),
      value: stats?.desensitized ?? 0,
      badge: t('dashboard.success'),
      badgeClass: 'text-teal-600',
      iconBg: 'bg-teal-50 text-brand-teal',
    },
    {
      label: t('dashboard.noRisk'),
      value: stats?.no_risk ?? 0,
      badge: t('dashboard.cleared'),
      badgeClass: 'text-slate-400',
      iconBg: 'bg-slate-100 text-slate-600',
    },
  ]

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto">
      {/* Stats Grid */}
      <section className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((card, i) => (
          <div
            key={i}
            className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow"
          >
            <div className="flex items-center justify-between mb-4">
              <span className={`p-2 rounded-lg ${card.iconBg}`}>
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                  />
                </svg>
              </span>
              <span className={`text-xs font-bold uppercase ${card.badgeClass}`}>{card.badge}</span>
            </div>
            <p className="text-3xl font-bold text-slate-900">
              {card.value.toLocaleString()}
            </p>
            <p className="text-sm text-slate-500 mt-1">{card.label}</p>
          </div>
        ))}
      </section>

      {/* Monitor + Throughput Row */}
      <section className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Folder Monitor */}
        <div className="lg:col-span-1 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-bold text-slate-800">{t('dashboard.folderMonitor')}</h2>
            <span className="px-2 py-1 bg-teal-100 text-teal-700 text-[10px] font-bold rounded uppercase">
              {monitor?.status === 'active' ? t('common.active') : t('common.inactive')}
            </span>
          </div>
          <div className="bg-slate-50 rounded-lg p-4 border border-slate-200 mb-4">
            <div className="flex items-start gap-3">
              <svg className="w-5 h-5 text-brand-blue mt-1 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
              </svg>
              <div>
                <p className="text-sm font-mono font-medium text-slate-700 break-all">
                  {monitor?.path || 'C:\\IncomeFiles'}
                </p>
                <p className="text-xs text-slate-500 mt-1">
                  {t('dashboard.lastScan')}: {monitor?.last_scan || '-'}
                </p>
              </div>
            </div>
          </div>
          <div className="space-y-3">
            <div className="flex justify-between text-sm">
              <span className="text-slate-500">{t('dashboard.scanningRate')}</span>
              <span className="font-medium">{monitor?.pending_files ?? 0} files</span>
            </div>
            <div className="w-full bg-slate-200 h-1 rounded-full overflow-hidden">
              <div className="bg-brand-blue h-full w-3/4" />
            </div>
            <div className="flex justify-between text-xs text-slate-400">
              <span>{t('dashboard.memoryUsage')}: {monitor?.memory_usage ?? 0}MB</span>
            </div>
          </div>
        </div>

        {/* Throughput Chart */}
        <div className="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <h2 className="text-lg font-bold text-slate-800 mb-6">{t('dashboard.throughput')}</h2>
          <div className="h-40 w-full flex items-end gap-2 px-2">
            {[40, 60, 45, 90, 75, 55, 30, 85, 65, 95].map((h, i) => (
              <div
                key={i}
                className={`w-full rounded-t ${
                  i % 5 === 4
                    ? 'bg-brand-teal/40 border-t-2 border-brand-teal'
                    : 'bg-brand-blue/20'
                }`}
                style={{ height: `${h}%` }}
              />
            ))}
          </div>
          <div className="flex justify-between mt-4 text-xs text-slate-400">
            <span>08:00</span>
            <span>10:00</span>
            <span>12:00</span>
            <span>14:00</span>
            <span>16:00</span>
            <span>{t('common.active')}</span>
          </div>
        </div>
      </section>

      {/* Recent Activity Table */}
      <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 className="text-lg font-bold text-slate-800">{t('dashboard.recentActivity')}</h2>
          <button className="text-xs font-semibold text-brand-blue hover:underline">
            {t('dashboard.viewAllLogs')}
          </button>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50">
                <th className="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {t('dashboard.fileName')}
                </th>
                <th className="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {t('dashboard.detectionStatus')}
                </th>
                <th className="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                  {t('dashboard.timestamp')}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {recent.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-6 py-8 text-center text-slate-400">
                    {t('common.noData')}
                  </td>
                </tr>
              ) : (
                recent.map((file) => (
                  <tr key={file.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                          />
                        </svg>
                        <span className="text-sm font-medium text-slate-700">{file.filename}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${
                          statusColors[file.status] || 'bg-slate-100 text-slate-600'
                        }`}
                      >
                        <span className="w-2 h-2 rounded-full bg-current" />
                        {file.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-500">{file.created_at}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
