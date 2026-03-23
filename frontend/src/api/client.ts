import axios from 'axios'

const api = axios.create({
  baseURL: 'http://voogpt.com:9005/api',
  timeout: 60000,
  headers: { 'Content-Type': 'application/json' },
})

// Auto-attach token from localStorage
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Auto-logout on 401
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && localStorage.getItem('token')) {
      localStorage.removeItem('token')
      window.location.reload()
    }
    return Promise.reject(err)
  }
)

// Dashboard
export const getDashboardStats = () => api.get('/dashboard/stats')
export const getDashboardRecent = () => api.get('/dashboard/recent')
export const getDashboardThroughput = () => api.get('/dashboard/throughput')
export const getDashboardMonitor = () => api.get('/dashboard/monitor')

// Files
export const getFiles = (params: { folder?: string; search?: string; page?: number }, signal?: AbortSignal) =>
  api.get('/files', { params, signal })
export const getFile = (id: number) => api.get(`/files/${id}`)
export const getFileCounts = () => api.get('/files/counts')
export const uploadFiles = (formData: FormData, onProgress?: (percent: number) => void) =>
  api.post('/files/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    timeout: 300000,
    onUploadProgress: (e) => {
      if (onProgress && e.total) {
        onProgress(Math.round((e.loaded * 100) / e.total))
      }
    },
  })
export const previewFileUrl = (id: number) => {
  const token = localStorage.getItem('token')
  return `${api.defaults.baseURL}/files/${id}/preview?token=${token}`
}
export const downloadFile = (id: number) =>
  api.get(`/files/${id}/download`, { responseType: 'blob' })
export const retryFile = (id: number) => api.post(`/files/${id}/retry`)
export const deleteFile = (id: number) => api.delete(`/files/${id}`)

// Re-identification
export const getReidentifyFiles = () => api.get('/reidentify/files')
export const uploadReidentifyFile = (formData: FormData) =>
  api.post('/reidentify/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
export const processReidentify = (data: { text: string; file_record_id?: number }) =>
  api.post('/reidentify/process', data)
export const getWordPairs = (fileId: number) => api.get(`/reidentify/pairs/${fileId}`)

// Settings
export const getSettings = () => api.get('/settings')
export const updateSettings = (settings: Record<string, string>) =>
  api.put('/settings', { settings })
export const testConnection = (type: string) =>
  api.post('/settings/test-connection', { type })

export default api
