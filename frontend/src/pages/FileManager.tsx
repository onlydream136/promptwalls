import { useEffect, useState, useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { getFiles, getFileCounts, uploadFiles, downloadFile, deleteFile, retryFile, desensitizeFile, previewFileUrl } from '../api/client'

interface FileItem {
  id: number
  filename: string
  file_type: string
  file_size: number
  status: string
  folder: string
  created_at: string
  updated_at: string
}

const tabs = ['incoming', 'clean', 'sensitive', 'desensitized', 'restored'] as const
type Folder = (typeof tabs)[number]

const tabKeys: Record<Folder, string> = {
  incoming: 'files.incoming',
  clean: 'files.clean',
  sensitive: 'files.sensitive',
  desensitized: 'files.desensitized',
  restored: 'files.restored',
}

const statusStyles: Record<string, { bg: string; text: string; dot: string }> = {
  pending: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-400' },
  ocr_scanning: { bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-500' },
  assessing: { bg: 'bg-indigo-100', text: 'text-indigo-700', dot: 'bg-indigo-500' },
  sensitive: { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
  no_risk: { bg: 'bg-green-100', text: 'text-green-700', dot: 'bg-green-500' },
  desensitized: { bg: 'bg-amber-100', text: 'text-amber-700', dot: 'bg-amber-500' },
  failed: { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
  restored: { bg: 'bg-teal-100', text: 'text-teal-700', dot: 'bg-teal-500' },
}

const statusLabels: Record<string, { en: string; zh: string }> = {
  pending: { en: 'Pending', zh: '待处理' },
  ocr_scanning: { en: 'OCR Scanning', zh: 'OCR扫描中' },
  assessing: { en: 'Assessing', zh: '评估中' },
  sensitive: { en: 'Sensitive', zh: '含敏感信息' },
  no_risk: { en: 'No Risk', zh: '无风险' },
  desensitized: { en: 'Desensitized', zh: '已脱敏' },
  failed: { en: 'Failed', zh: '处理失败' },
  restored: { en: 'Restored', zh: '已復原' },
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

export default function FileManager() {
  const { t, i18n } = useTranslation()
  const [activeTab, setActiveTab] = useState<Folder>('incoming')
  const [files, setFiles] = useState<FileItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [uploading, setUploading] = useState(false)
  const [uploadProgress, setUploadProgress] = useState(0)
  const [uploadResult, setUploadResult] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [loading, setLoading] = useState(false)
  const [tabCounts, setTabCounts] = useState<Record<string, number>>({})
  const fileInputRef = useRef<HTMLInputElement>(null)
  const abortRef = useRef<AbortController | null>(null)

  const fetchCounts = useCallback(() => {
    getFileCounts()
      .then((r) => setTabCounts(r.data))
      .catch(() => {})
  }, [])

  const fetchFiles = useCallback(() => {
    abortRef.current?.abort()
    const controller = new AbortController()
    abortRef.current = controller
    setLoading(true)
    getFiles({ folder: activeTab, search, page }, controller.signal)
      .then((r) => {
        setFiles(r.data.data)
        setTotal(r.data.total)
      })
      .catch((e) => {
        if (e?.name === 'CanceledError') return
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
  }, [activeTab, search, page])

  useEffect(() => {
    fetchFiles()
    fetchCounts()
    return () => abortRef.current?.abort()
  }, [fetchFiles, fetchCounts])

  const handleUpload = async (fileList: FileList) => {
    if (fileList.length === 0) return
    setUploading(true)
    setUploadProgress(0)
    setUploadResult(null)
    const formData = new FormData()
    Array.from(fileList).forEach((f) => formData.append('files[]', f))

    try {
      const res = await uploadFiles(formData, (percent) => setUploadProgress(percent))
      const count = res.data?.uploaded?.length || fileList.length
      setUploadResult({ type: 'success', message: t('files.uploadSuccess', { count }) })
      fetchFiles()
      fetchCounts()
    } catch (e: any) {
      const msg = e?.response?.data?.message || t('files.uploadFailed')
      setUploadResult({ type: 'error', message: msg })
    } finally {
      setUploading(false)
      setUploadProgress(0)
    }
  }

  const handleDownload = async (file: FileItem) => {
    try {
      const res = await downloadFile(file.id)
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = file.filename
      a.click()
      window.URL.revokeObjectURL(url)
    } catch (e) {
      console.error('Download failed', e)
    }
  }

  const handleRetry = async (file: FileItem) => {
    try {
      await retryFile(file.id)
      setFiles((prev) =>
        prev.map((f) => (f.id === file.id ? { ...f, status: 'pending' } : f))
      )
      fetchCounts()
      setUploadResult({
        type: 'success',
        message: i18n.language === 'zh'
          ? `"${file.filename}" 已重新加入处理队列`
          : `"${file.filename}" queued for reprocessing`,
      })
      setTimeout(() => setUploadResult(null), 3000)
    } catch (e) {
      setUploadResult({
        type: 'error',
        message: i18n.language === 'zh' ? '重试失败' : 'Retry failed',
      })
    }
  }

  const handleDesensitize = async (file: FileItem) => {
    try {
      await desensitizeFile(file.id)
      setFiles((prev) =>
        prev.map((f) => (f.id === file.id ? { ...f, status: 'desensitized' } : f))
      )
      fetchCounts()
      setUploadResult({
        type: 'success',
        message: i18n.language === 'zh'
          ? `"${file.filename}" ${t('files.desensitizeSuccess')}`
          : `"${file.filename}" desensitized successfully`,
      })
      setTimeout(() => setUploadResult(null), 3000)
    } catch {
      setUploadResult({
        type: 'error',
        message: i18n.language === 'zh' ? t('files.desensitizeFailed') : 'Desensitize failed',
      })
    }
  }

  const nonEditableTypes = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff']

  const previewableTypes = ['pdf', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'svg']

  const handlePreview = (file: FileItem) => {
    const ext = file.file_type.toLowerCase()
    if (previewableTypes.includes(ext)) {
      window.open(previewFileUrl(file.id), '_blank')
    } else {
      setUploadResult({
        type: 'error',
        message: i18n.language === 'zh'
          ? `「${ext.toUpperCase()}」格式無法在瀏覽器中預覽，請下載後查看`
          : `"${ext.toUpperCase()}" files cannot be previewed in browser, please download`,
      })
      setTimeout(() => setUploadResult(null), 4000)
    }
  }

  const handleDelete = async (file: FileItem) => {
    if (!window.confirm(i18n.language === 'zh'
      ? `确认删除文件 "${file.filename}"？`
      : `Delete file "${file.filename}"?`
    )) return
    try {
      await deleteFile(file.id)
      setFiles((prev) => prev.filter((f) => f.id !== file.id))
      setTotal((prev) => Math.max(0, prev - 1))
      fetchCounts()
      setUploadResult({
        type: 'success',
        message: i18n.language === 'zh'
          ? `已删除 "${file.filename}"`
          : `Deleted "${file.filename}"`,
      })
      setTimeout(() => setUploadResult(null), 3000)
    } catch (e) {
      setUploadResult({
        type: 'error',
        message: i18n.language === 'zh' ? '删除失败' : 'Delete failed',
      })
    }
  }

  return (
    <div className="p-8 space-y-8">
      {/* Upload Area */}
      <section>
        <div className="max-w-4xl mx-auto">
          <div
            className={`border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer group ${
              dragOver
                ? 'border-blue-500 bg-blue-50'
                : 'border-slate-300 bg-white hover:border-blue-400 hover:bg-blue-50'
            }`}
            onClick={() => fileInputRef.current?.click()}
            onDragOver={(e) => {
              e.preventDefault()
              setDragOver(true)
            }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(e) => {
              e.preventDefault()
              setDragOver(false)
              handleUpload(e.dataTransfer.files)
            }}
          >
            <input
              ref={fileInputRef}
              type="file"
              multiple
              className="hidden"
              onChange={(e) => e.target.files && handleUpload(e.target.files)}
            />
            <div className="flex flex-col items-center">
              <div className="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                  />
                </svg>
              </div>
              <h3 className="text-lg font-medium text-slate-800">
                {uploading ? t('files.uploading') : t('files.uploadTitle')}
              </h3>
              {uploading ? (
                <div className="w-full max-w-xs mx-auto mt-3">
                  <div className="w-full bg-slate-200 rounded-full h-2.5">
                    <div
                      className="bg-blue-500 h-2.5 rounded-full transition-all duration-300"
                      style={{ width: `${uploadProgress}%` }}
                    />
                  </div>
                  <p className="text-sm text-slate-500 mt-1">{uploadProgress}%</p>
                </div>
              ) : (
                <>
                  <p className="text-slate-500 text-sm mt-1">{t('files.uploadDesc')}</p>
                  <div className="mt-4 flex gap-2 justify-center">
                    {['.PDF', '.DOCX', '.XLSX', '.CSV', '.TXT', '.PNG', '.JPG'].map((ext) => (
                      <span
                        key={ext}
                        className="px-2 py-1 bg-slate-100 text-slate-600 text-[10px] uppercase font-bold rounded"
                      >
                        {ext}
                      </span>
                    ))}
                  </div>
                </>
              )}
            </div>
          </div>
          {uploadResult && (
            <div
              className={`mt-4 px-4 py-3 rounded-lg text-sm flex items-center justify-between ${
                uploadResult.type === 'success'
                  ? 'bg-green-50 text-green-700 border border-green-200'
                  : 'bg-red-50 text-red-700 border border-red-200'
              }`}
            >
              <span>{uploadResult.message}</span>
              <button onClick={() => setUploadResult(null)} className="ml-3 font-bold hover:opacity-70">&times;</button>
            </div>
          )}
        </div>
      </section>

      {/* File Explorer */}
      <section className="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col min-h-[500px]">
        {/* Tabs */}
        <div className="flex border-b border-slate-200">
          {tabs.map((tab) => (
            <button
              key={tab}
              onClick={() => {
                setActiveTab(tab)
                setPage(1)
              }}
              className={`px-6 py-4 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
                activeTab === tab
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
              }`}
            >
              {t(tabKeys[tab])}
              {tabCounts[tab] !== undefined && tabCounts[tab] > 0 && (
                <span className={`ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none ${
                  activeTab === tab
                    ? 'bg-blue-100 text-blue-600'
                    : 'bg-slate-200 text-slate-500'
                }`}>
                  {tabCounts[tab]}
                </span>
              )}
            </button>
          ))}
        </div>

        {/* Search Toolbar */}
        <div className="px-6 py-4 bg-slate-50 flex items-center justify-between">
          <div className="relative w-72">
            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                />
              </svg>
            </span>
            <input
              type="text"
              className="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-md text-sm placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              placeholder={t('files.searchPlaceholder')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <button
            onClick={fetchFiles}
            className="p-2 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
              />
            </svg>
          </button>
        </div>

        {/* File Table */}
        <div className="overflow-x-auto flex-1 relative">
          {loading && (
            <div className="absolute inset-0 bg-white/60 z-10 flex items-center justify-center">
              <svg className="animate-spin h-6 w-6 text-blue-500" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            </div>
          )}
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-200">
                <th className="px-6 py-3">{t('files.originalName')}</th>
                <th className="px-6 py-3">{t('files.format')}</th>
                <th className="px-6 py-3">{t('files.processedDate')}</th>
                <th className="px-6 py-3">{t('files.status')}</th>
                <th className="px-6 py-3 text-right">{t('files.actions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {files.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-12 text-center text-slate-400">
                    {t('common.noData')}
                  </td>
                </tr>
              ) : (
                files.map((file) => (
                  <tr key={file.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-4 flex items-center gap-3">
                      <div className="w-10 h-10 bg-blue-50 text-blue-600 rounded flex items-center justify-center shrink-0">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                          />
                        </svg>
                      </div>
                      <div>
                        <button
                          onClick={() => handlePreview(file)}
                          className="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline cursor-pointer text-left"
                          title={i18n.language === 'zh' ? '點擊預覽' : 'Click to preview'}
                        >
                          {file.filename}
                        </button>
                        <p className="text-xs text-slate-500">{formatSize(file.file_size)}</p>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-xs font-semibold text-slate-600 px-2 py-1 bg-slate-100 rounded uppercase">
                        {file.file_type}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">{file.created_at}</td>
                    <td className="px-6 py-4">
                      {(() => {
                        const style = statusStyles[file.status] || statusStyles.pending
                        const label = statusLabels[file.status]
                        const isProcessing = file.status === 'ocr_scanning' || file.status === 'assessing'
                        return (
                          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${style.bg} ${style.text}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${style.dot} ${isProcessing ? 'animate-pulse' : ''}`} />
                            {label ? (i18n.language === 'zh' ? label.zh : label.en) : file.status}
                          </span>
                        )
                      })()}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="inline-flex gap-2">
                        {file.status === 'failed' && (
                          <button
                            onClick={() => handleRetry(file)}
                            className="inline-flex items-center px-3 py-1.5 bg-white border border-amber-300 rounded text-xs font-semibold text-amber-700 hover:bg-amber-50 transition-all shadow-sm"
                          >
                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                              />
                            </svg>
                            {i18n.language === 'zh' ? '重试' : 'Retry'}
                          </button>
                        )}
                        {file.status === 'sensitive' && nonEditableTypes.includes(file.file_type.toLowerCase()) && (
                          <button
                            onClick={() => handleDesensitize(file)}
                            className="inline-flex items-center px-3 py-1.5 bg-white border border-blue-300 rounded text-xs font-semibold text-blue-700 hover:bg-blue-50 transition-all shadow-sm"
                          >
                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} />
                            </svg>
                            {t('files.convertToText')}
                          </button>
                        )}
                        <button
                          onClick={() => handleDownload(file)}
                          className="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 rounded text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm"
                        >
                          <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                            />
                          </svg>
                          {t('common.download')}
                        </button>
                        <button
                          onClick={() => handleDelete(file)}
                          className="inline-flex items-center px-3 py-1.5 bg-white border border-red-200 rounded text-xs font-semibold text-red-600 hover:bg-red-50 transition-all shadow-sm"
                        >
                          <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                            />
                          </svg>
                          {t('common.delete')}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 mt-auto flex items-center justify-between">
          <p className="text-xs text-slate-500">
            {t('files.showing', { current: files.length, total })}
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="px-3 py-1 border border-slate-300 rounded text-xs disabled:opacity-50"
            >
              Previous
            </button>
            <button
              onClick={() => setPage((p) => p + 1)}
              disabled={files.length < 20}
              className="px-3 py-1 border border-slate-300 rounded text-xs disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      </section>
    </div>
  )
}
