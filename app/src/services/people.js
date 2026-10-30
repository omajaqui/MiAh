import { api } from '@/boot/axios'
import { unwrapList } from '@/utils/format'

export async function listBankTypes() {
  const { data } = await api.get('/bank-types')
  return Array.isArray(data.data) ? data.data : []
}

export async function listPeople(params) {
  const { data } = await api.get('/people', { params })
  return unwrapList(data)
}

export async function getPerson(id) {
  const { data } = await api.get(`/people/${id}`)
  return data.data
}

export async function createPerson(payload) {
  const { data } = await api.post('/people', payload)
  return data.data
}

export async function updatePerson(id, payload) {
  const { data } = await api.put(`/people/${id}`, payload)
  return data.data
}

export async function updatePersonStatus(id, isActive) {
  const { data } = await api.patch(`/people/${id}/status`, { is_active: isActive })
  return data.data
}

export async function createAccount(id, payload) {
  const { data } = await api.post(`/people/${id}/account`, payload)
  return data.data
}

export async function updateAccount(id, payload) {
  const { data } = await api.patch(`/people/${id}/account`, payload)
  return data.data
}

export async function resetPassword(id, payload) {
  const { data } = await api.post(`/people/${id}/account/reset-password`, payload)
  return data
}
