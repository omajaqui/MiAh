import { api } from '@/boot/axios'

export async function getMemberDashboard() {
  const { data } = await api.get('/member/dashboard')
  return data.data
}

export async function getMemberCycles() {
  const { data } = await api.get('/member/cycles')
  return data.data
}

export async function getMemberCycle(id) {
  const { data } = await api.get(`/member/cycles/${id}`)
  return data.data
}

export async function getCurrentTurn() {
  const { data } = await api.get('/member/current-turn')
  return data.data
}

export async function reportMemberPayments(cycleId, contributionIds, receipt) {
  const payload = new FormData()
  contributionIds.forEach((id) => payload.append('contribution_ids[]', id))
  payload.append('receipt', receipt)

  const { data } = await api.post(`/member/cycles/${cycleId}/payment-reports`, payload)
  return data.data
}
