import { useState, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { uploadReidentifyFile, processReidentify, restoreFile, getWordPairs } from '../api/client'

interface WordPair {
  placeholder: string
  original_value: string
  entity_type: string
}

interface ReidentifyResult {
  restored_text: string
  replacements_made: number
  replacements: { placeholder: string; original: string; type: string }[]
}

export default function ReIdentification() {
  const { t } = useTranslation()
  const [uploadedText, setUploadedText] = useState('')
  const [detectedFileId, setDetectedFileId] = useState<number | null>(null)
  const [result, setResult] = useState<ReidentifyResult | null>(null)
  const [pairs, setPairs] = useState<WordPair[]>([])
  const [processing, setProcessing] = useState(false)
  const [complete, setComplete] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')
  const [uploadedFile, setUploadedFile] = useState<File | null>(null)
  const [uploadedFilename, setUploadedFilename] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const isTextFormat = (name: string) => {
    const ext = name.split('.').pop()?.toLowerCase() || ''
    return ['txt', 'csv', 'log', 'json'].includes(ext)
  }

  const canRestoreOriginal = (name: string) => {
    const ext = name.split('.').pop()?.toLowerCase() || ''
    return ['docx', 'xlsx', 'csv', 'txt', 'log', 'json'].includes(ext)
  }

  const handleFileUpload = async (file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    setUploading(true)
    setError('')
    setResult(null)
    setComplete(false)
    setUploadedFile(file)
    setUploadedFilename(file.name)

    try {
      const res = await uploadReidentifyFile(formData)
      setUploadedText(res.data.content)
      setDetectedFileId(res.data.detected_file_id)

      if (res.data.detected_file_id) {
        const pairsRes = await getWordPairs(res.data.detected_file_id)
        setPairs(pairsRes.data.pairs)
      }
    } catch (e) {
      setError(t('files.uploadFailed'))
    } finally {
      setUploading(false)
    }
  }

  const handleProcess = async () => {
    if (!uploadedText) return
    setProcessing(true)
    setComplete(false)
    setError('')

    try {
      const res = await processReidentify({
        text: uploadedText,
        file_record_id: detectedFileId ?? undefined,
      })
      setResult(res.data)
      setComplete(true)
    } catch (e) {
      setError(t('reidentify.processFailed') || 'Processing failed')
    } finally {
      setProcessing(false)
    }
  }

  const handleDownloadRestored = async () => {
    if (!uploadedFile) return
    try {
      const formData = new FormData()
      formData.append('file', uploadedFile)
      if (detectedFileId) formData.append('file_record_id', String(detectedFileId))
      const res = await restoreFile(formData)
      const ext = uploadedFilename.split('.').pop() || 'txt'
      const baseName = uploadedFilename.replace(/^desensitized_/, '').replace(/\.[^.]+$/, '')
      const blob = new Blob([res.data])
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `restored_${baseName}.${ext}`
      a.click()
      URL.revokeObjectURL(url)
    } catch (err: any) {
      console.error('Restore error:', err?.response?.status, err?.response?.data, err?.message)
      setError('Download failed')
    }
  }

  return (
    <div className="p-8 max-w-6xl mx-auto">
      {/* Header */}
      <header className="mb-10">
        <h1 className="text-3xl font-extrabold text-slate-900">{t('reidentify.title')}</h1>
        <p className="mt-3 text-lg text-slate-600 max-w-3xl">{t('reidentify.description')}</p>
      </header>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left Panel */}
        <section className="lg:col-span-2 space-y-8">
          {/* Upload Zone */}
          <div
            className={`bg-white rounded-xl border-2 border-dashed p-10 text-center transition-colors relative ${
              uploading
                ? 'border-indigo-400 bg-indigo-50/30'
                : uploadedText
                ? 'border-green-400 bg-green-50/30'
                : 'border-slate-300 hover:border-indigo-400 group cursor-pointer'
            }`}
            onClick={() => !uploading && fileInputRef.current?.click()}
          >
            <input
              ref={fileInputRef}
              type="file"
              className="hidden"
              accept=".txt,.docx,.xlsx,.csv,.json"
              onChange={(e) => e.target.files?.[0] && handleFileUpload(e.target.files[0])}
            />
            <div className="flex flex-col items-center">
              {uploading ? (
                <>
                  <svg className="animate-spin h-10 w-10 text-indigo-500 mb-4" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  <h3 className="text-lg font-semibold text-indigo-700">{t('files.uploading')}</h3>
                </>
              ) : uploadedText ? (
                <>
                  <div className="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4">
                    <svg className="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path d="M5 13l4 4L19 7" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} />
                    </svg>
                  </div>
                  <h3 className="text-lg font-semibold text-green-700">{t('reidentify.uploadTitle')}</h3>
                  <p className="text-slate-500 mt-1 text-sm">{t('reidentify.uploadDesc')}</p>
                </>
              ) : (
                <>
                  <div className="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <svg className="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                      />
                    </svg>
                  </div>
                  <h3 className="text-lg font-semibold text-slate-800">{t('reidentify.uploadTitle')}</h3>
                  <p className="text-slate-500 mt-1">{t('reidentify.uploadDesc')}</p>
                  <p className="text-xs text-slate-400 mt-4 uppercase tracking-widest font-bold">
                    {t('reidentify.secureLocal')}
                  </p>
                </>
              )}
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="px-4 py-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200 flex items-center justify-between">
              <span>{error}</span>
              <button onClick={() => setError('')} className="ml-3 font-bold hover:opacity-70">&times;</button>
            </div>
          )}

          {/* Process Button */}
          <div className="flex flex-col items-center space-y-4">
            <button
              onClick={handleProcess}
              disabled={!uploadedText || processing}
              className={`w-full sm:w-auto px-10 py-4 font-bold rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all ${
                complete
                  ? 'bg-green-600 text-white'
                  : 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200'
              } disabled:opacity-50 disabled:cursor-not-allowed`}
            >
              {processing ? (
                <>
                  <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  {t('reidentify.processing')}
                </>
              ) : complete
                ? t('reidentify.complete')
                : t('reidentify.processBtn')}
            </button>
            <p className="text-sm text-slate-400 italic">{t('reidentify.localOnly')}</p>
          </div>

          {/* Result Area */}
          {result && (
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
              <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 className="font-bold text-slate-700 flex items-center gap-2">
                  <span className="w-2 h-2 bg-green-500 rounded-full" />
                  {t('reidentify.resultTitle')}
                </h3>
                <button
                  onClick={handleDownloadRestored}
                  className="text-indigo-600 text-sm font-semibold hover:underline"
                >
                  {t('reidentify.downloadOriginal')}
                </button>
              </div>
              <div className="p-6">
                <div className="bg-slate-900 rounded-lg p-4 font-mono text-sm text-indigo-300 leading-relaxed max-h-60 overflow-y-auto whitespace-pre-wrap">
                  {result.restored_text}
                </div>
              </div>
            </div>
          )}
        </section>

        {/* Right Sidebar */}
        <aside className="space-y-6">
          {/* Session Stats */}
          <div className="bg-indigo-900 rounded-xl p-6 text-white shadow-xl">
            <h4 className="text-indigo-200 text-xs font-bold uppercase tracking-widest mb-4">
              {t('reidentify.sessionContext')}
            </h4>
            <div className="space-y-4">
              <div>
                <p className="text-3xl font-bold">{pairs.length}</p>
                <p className="text-indigo-300 text-sm">{t('reidentify.activePairs')}</p>
              </div>
              <div className="w-full bg-indigo-800 h-1.5 rounded-full overflow-hidden">
                <div
                  className="bg-indigo-400 h-full"
                  style={{ width: `${Math.min(100, (pairs.length / 200) * 100)}%` }}
                />
              </div>
              <p className="text-xs text-indigo-400 italic">{t('reidentify.mapEncrypted')}</p>
            </div>
          </div>

          {/* Word Pair Map Preview */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
              <h4 className="font-bold text-slate-700 text-sm">{t('reidentify.wordPairMap')}</h4>
              <span className="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded uppercase font-bold">
                {t('reidentify.live')}
              </span>
            </div>
            <div className="divide-y divide-slate-100 max-h-96 overflow-y-auto">
              {pairs.length === 0 ? (
                <div className="p-4 text-center text-xs text-slate-400">
                  {t('common.noData')}
                </div>
              ) : (
                pairs.map((pair, i) => (
                  <div key={i} className="p-3 flex items-center justify-between text-xs">
                    <div className="flex flex-col">
                      <span className="text-slate-400 uppercase font-medium text-[9px]">
                        {t('reidentify.placeholder')}
                      </span>
                      <code className="text-rose-500 font-bold">{pair.placeholder}</code>
                    </div>
                    <svg className="h-4 w-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path d="M14 5l7 7m0 0l-7 7m7-7H3" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} />
                    </svg>
                    <div className="flex flex-col text-right">
                      <span className="text-slate-400 uppercase font-medium text-[9px]">
                        {t('reidentify.original')}
                      </span>
                      <span className="text-indigo-600 font-bold">{pair.original_value}</span>
                    </div>
                  </div>
                ))
              )}
            </div>
            {pairs.length > 0 && (
              <div className="p-3 bg-slate-50 border-t border-slate-100">
                <button onClick={() => window.location.href = '/wordpairs'} className="w-full text-center text-xs text-indigo-600 font-bold hover:text-indigo-800">
                  {t('reidentify.viewFullMap')}
                </button>
              </div>
            )}
          </div>

          {/* How it works */}
          <div className="bg-amber-50 rounded-xl p-5 border border-amber-100">
            <h5 className="text-amber-800 font-bold text-sm mb-2 flex items-center gap-2">
              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                />
              </svg>
              {t('reidentify.howItWorks')}
            </h5>
            <ol className="text-amber-900/80 text-xs space-y-2 list-decimal ml-4 leading-relaxed">
              <li>{t('reidentify.step1')}</li>
              <li>{t('reidentify.step2')}</li>
              <li>{t('reidentify.step3')}</li>
              <li>{t('reidentify.step4')}</li>
            </ol>
          </div>
        </aside>
      </div>
    </div>
  )
}
