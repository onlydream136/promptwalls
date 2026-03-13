import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { getSettings, updateSettings, testConnection } from '../api/client'

export default function Settings() {
  const { t } = useTranslation()
  const [config, setConfig] = useState<Record<string, string>>({})
  const [originalConfig, setOriginalConfig] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [testResults, setTestResults] = useState<Record<string, { success: boolean; message: string }>>({})
  const [testing, setTesting] = useState<Record<string, boolean>>({})

  useEffect(() => {
    getSettings()
      .then((r) => {
        setConfig(r.data)
        setOriginalConfig(r.data)
      })
      .catch(() => {})
  }, [])

  const handleChange = (key: string, value: string) => {
    setConfig((prev) => ({ ...prev, [key]: value }))
    setSaved(false)
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await updateSettings(config)
      setOriginalConfig(config)
      setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch (e) {
      console.error('Save failed', e)
    } finally {
      setSaving(false)
    }
  }

  const handleTestConnection = async (type: string) => {
    setTesting((prev) => ({ ...prev, [type]: true }))
    setTestResults((prev) => {
      const next = { ...prev }
      delete next[type]
      return next
    })
    try {
      await updateSettings(config)
      const res = await testConnection(type)
      setTestResults((prev) => ({ ...prev, [type]: res.data }))
    } catch (e) {
      setTestResults((prev) => ({
        ...prev,
        [type]: { success: false, message: 'Connection failed' },
      }))
    } finally {
      setTesting((prev) => ({ ...prev, [type]: false }))
    }
  }

  return (
    <div className="p-8 max-w-4xl mx-auto">
      <header className="mb-8">
        <h1 className="text-3xl font-bold text-slate-900">{t('settings.title')}</h1>
        <p className="mt-2 text-slate-600">{t('settings.subtitle')}</p>
      </header>

      <div className="space-y-6">
        {/* LLM Endpoint Configuration */}
        <section className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-center gap-2 mb-6 border-b pb-4">
            <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="M13 10V3L4 14h7v7l9-11h-7z" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} />
            </svg>
            <h2 className="text-xl font-semibold text-slate-800">{t('settings.llmConfig')}</h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
            {/* OCR Endpoint */}
            <div className="md:col-span-9">
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('settings.ocrEndpoint')}
              </label>
              <input
                type="text"
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                value={config.ocr_endpoint || ''}
                onChange={(e) => handleChange('ocr_endpoint', e.target.value)}
              />
              <input
                type="text"
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mt-2"
                placeholder="Model name"
                value={config.ocr_model || ''}
                onChange={(e) => handleChange('ocr_model', e.target.value)}
              />
            </div>
            <div className="md:col-span-3">
              <button
                onClick={() => handleTestConnection('ocr')}
                disabled={testing.ocr}
                className="w-full px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {testing.ocr ? (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    {t('settings.testing')}
                  </span>
                ) : t('settings.testConnection')}
              </button>
              {testResults.ocr && (
                <p className={`text-xs mt-1 ${testResults.ocr.success ? 'text-green-600' : 'text-red-600'}`}>
                  {testResults.ocr.success ? t('settings.connectionSuccess') : t('settings.connectionFailed')}
                </p>
              )}
            </div>

            {/* Assessment Endpoint */}
            <div className="md:col-span-9">
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('settings.assessmentEndpoint')}
              </label>
              <input
                type="text"
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                value={config.assessment_endpoint || ''}
                onChange={(e) => handleChange('assessment_endpoint', e.target.value)}
              />
              <input
                type="text"
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mt-2"
                placeholder="Model name"
                value={config.assessment_model || ''}
                onChange={(e) => handleChange('assessment_model', e.target.value)}
              />
            </div>
            <div className="md:col-span-3">
              <button
                onClick={() => handleTestConnection('assessment')}
                disabled={testing.assessment}
                className="w-full px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {testing.assessment ? (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    {t('settings.testing')}
                  </span>
                ) : t('settings.testConnection')}
              </button>
              {testResults.assessment && (
                <p className={`text-xs mt-1 ${testResults.assessment.success ? 'text-green-600' : 'text-red-600'}`}>
                  {testResults.assessment.success ? t('settings.connectionSuccess') : t('settings.connectionFailed')}
                </p>
              )}
            </div>
          </div>
        </section>

        {/* Folder Path Configuration */}
        <section className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-center gap-2 mb-6 border-b pb-4">
            <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
              />
            </svg>
            <h2 className="text-xl font-semibold text-slate-800">{t('settings.folderConfig')}</h2>
          </div>

          <div className="space-y-4">
            {[
              { key: 'income_files_path', label: t('settings.incomeFiles') },
              { key: 'no_sensitive_path', label: t('settings.noSensitive') },
              { key: 'sensitive_files_path', label: t('settings.sensitiveFiles') },
              { key: 'desensitized_files_path', label: t('settings.desensitizedFiles') },
            ].map(({ key, label }) => (
              <div key={key} className="grid grid-cols-1 sm:grid-cols-4 items-center gap-4">
                <label className="text-sm font-medium text-slate-600">{label}</label>
                <div className="sm:col-span-3">
                  <input
                    type="text"
                    className="block w-full rounded-md border-slate-300 bg-slate-50 text-sm focus:border-blue-500 focus:bg-white"
                    value={config[key] || ''}
                    onChange={(e) => handleChange(key, e.target.value)}
                  />
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Replacement Strategy */}
        <section className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                />
              </svg>
              <div>
                <h2 className="text-lg font-semibold text-slate-800">{t('settings.strategy')}</h2>
                <p className="text-xs text-slate-500">{t('settings.strategyDesc')}</p>
              </div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                className="sr-only peer"
                checked={config.use_llm_desensitize === 'true' || config.use_llm_desensitize === true as any}
                onChange={(e) => handleChange('use_llm_desensitize', e.target.checked ? 'true' : 'false')}
              />
              <div className="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600" />
              <span className="ml-3 text-sm font-medium text-slate-700">{t('settings.generalLlm')}</span>
            </label>
          </div>
        </section>

        {/* Save Actions */}
        <div className="flex items-center justify-end gap-4 pt-4">
          {saved && (
            <span className="text-sm text-green-600 font-medium">{t('settings.saved')}</span>
          )}
          <button
            onClick={() => setConfig(originalConfig)}
            className="px-6 py-2.5 text-slate-700 font-semibold hover:text-slate-900 transition-colors"
          >
            {t('common.discard')}
          </button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="px-8 py-2.5 bg-blue-600 text-white font-semibold rounded-md shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all disabled:opacity-50"
          >
            {saving ? t('common.loading') : t('settings.saveChanges')}
          </button>
        </div>
      </div>
    </div>
  )
}
