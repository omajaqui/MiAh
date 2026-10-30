import { api } from '@/boot/axios'
import { unwrapList } from '@/utils/format'

export async function getAdminDashboard() {
  const { data } = await api.get('/admin/dashboard')
  return data.data
}

export async function listCycles(params) {
  const { data } = await api.get('/savings-cycles', { params })
  return unwrapList(data)
}

export async function getCycle(id) {
  const { data } = await api.get(`/savings-cycles/${id}`)
  return data.data
}

export async function createCycle(payload) {
  const { data } = await api.post('/savings-cycles', payload)
  return data.data
}

export async function updateCycle(id, payload) {
  const { data } = await api.put(`/savings-cycles/${id}`, payload)
  return data.data
}

export async function previewCalendar(id) {
  const { data } = await api.get(`/savings-cycles/${id}/calendar-preview`)
  return data.data
}

export async function addMember(id, payload) {
  const { data } = await api.post(`/savings-cycles/${id}/members`, payload)
  return data.data
}

export async function addMembers(id, personIds) {
  const { data } = await api.post(`/savings-cycles/${id}/members`, { person_ids: personIds })
  return data.data
}

export async function removeMember(cycleId, memberId) {
  const { data } = await api.delete(`/savings-cycles/${cycleId}/members/${memberId}`)
  return data
}

export async function reorderMembers(id, personIds) {
  const { data } = await api.put(`/savings-cycles/${id}/members/order`, { person_ids: personIds })
  return data.data
}

export async function startCycle(id) {
  const { data } = await api.post(`/savings-cycles/${id}/start`)
  return data.data
}

export async function closeCycle(id, reason) {
  const { data } = await api.post(`/savings-cycles/${id}/close`, { reason })
  return data.data
}

export async function finalizeCycle(id, payload = {}) {
  const { data } = await api.post(`/savings-cycles/${id}/finalize`, payload)
  return data.data
}

export async function getSummary(id) {
  const { data } = await api.get(`/savings-cycles/${id}/summary`)
  return data.data
}

export async function registerPayment(contributionId, payload) {
  const { data } = await api.post(`/contributions/${contributionId}/payments`, payload)
  return data.data
}

export async function annulPayment(paymentId, reason) {
  const { data } = await api.post(`/payments/${paymentId}/annul`, { reason })
  return data.data
}

export async function registerDisbursement(turnId, payload) {
  const { data } = await api.post(`/turns/${turnId}/disbursement`, payload)
  return data.data
}

export async function annulDisbursement(id, reason) {
  const { data } = await api.post(`/disbursements/${id}/annul`, { reason })
  return data.data
}
