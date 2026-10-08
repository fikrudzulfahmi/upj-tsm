import { defineStore } from 'pinia'
import { settingApi } from '@/api'

const BAWAAN = {
  shop_name: 'Bengkel',
  shop_address: '',
  shop_phone: '',
  membership_duration_months: 6,
  member_discount_percent: 10,
  points_per_amount: 10000,
  member_points_expire_with_membership: true,
  checkup_extends_membership: false,
  auto_member_after_first_service: false,
  fuel_bar_count: 8,
  record_redeem_cost: true,
  update_buy_price_on_purchase: true,
  service_reminder_days: 90,
}

export const usePengaturanStore = defineStore('pengaturan', {
  state: () => ({
    nilai: { ...BAWAAN },
    termuat: false,
    memuat: false,
  }),
  getters: {
    namaBengkel: (s) => s.nilai.shop_name || 'Bengkel',
    alamatBengkel: (s) => s.nilai.shop_address || '',
    teleponBengkel: (s) => s.nilai.shop_phone || '',
    jumlahBarBensin: (s) => Number(s.nilai.fuel_bar_count) || 8,
    diskonMemberGlobal: (s) => Number(s.nilai.member_discount_percent) || 0,
    poinPerNominal: (s) => Number(s.nilai.points_per_amount) || 10000,
    durasiMember: (s) => Number(s.nilai.membership_duration_months) || 6,
  },
  actions: {
    async muat(paksa = false) {
      if (this.termuat && !paksa) return this.nilai
      this.memuat = true
      try {
        const res = await settingApi.daftar()
        this.nilai = { ...BAWAAN, ...(res.data || {}) }
        this.termuat = true
      } catch {
        /* biarkan nilai bawaan */
      } finally {
        this.memuat = false
      }
      return this.nilai
    },
    async simpan(perubahan) {
      const res = await settingApi.simpan(perubahan)
      this.nilai = { ...this.nilai, ...(res.data || {}) }
      this.termuat = true
      return this.nilai
    },
  },
})
