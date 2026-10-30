import { api } from '@/boot/axios'
import { unwrapList } from '@/utils/format'

export async function listPaymentReports(params) {
  const { data } = await api.get('/payment-reports', { params })
  return unwrapList(data)
}

export async function getPaymentReport(id) {
  const { data } = await api.get(`/payment-reports/${id}`)
  return data.data
}

export async function reviewPaymentReport(id, payload) {
  const { data } = await api.post(`/payment-reports/${id}/review`, payload)
  return data.data
}

function sniffImageMime(bytes) {
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
    return 'image/jpeg'
  }

  if (bytes.length >= 8 && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) {
    return 'image/png'
  }

  if (bytes.length >= 6 && bytes[0] === 0x47 && bytes[1] === 0x49 && bytes[2] === 0x46) {
    return 'image/gif'
  }

  if (
    bytes.length >= 12
    && bytes[0] === 0x52 && bytes[1] === 0x49 && bytes[2] === 0x46 && bytes[3] === 0x46
    && bytes[8] === 0x57 && bytes[9] === 0x45 && bytes[10] === 0x42 && bytes[11] === 0x50
  ) {
    return 'image/webp'
  }

  return null
}

export async function getPaymentReportReceiptUrl(id) {
  const response = await api.get(`/payment-reports/${id}/receipt`, {
    responseType: 'blob',
    headers: { Accept: 'image/*,application/octet-stream' },
  })

  const buffer = await response.data.arrayBuffer()
  const bytes = new Uint8Array(buffer)
  const headerType = String(response.headers['content-type'] || '').split(';')[0].trim()
  const mime = sniffImageMime(bytes) || (headerType.startsWith('image/') ? headerType : 'image/jpeg')
  const blob = new Blob([buffer], { type: mime })

  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = () => reject(new Error('No se pudo leer el comprobante.'))
    reader.readAsDataURL(blob)
  })
}
