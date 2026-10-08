<script setup>
/**
 * Ikon SVG terpusat (lucide-vue-next). Map eksplisit = tree-shaking jalan,
 * dan nama ikon dipakai sebagai string di seluruh aplikasi.
 */
import {
  AlertTriangle, ArrowLeft, ArrowRight, Award, BadgeCheck, Ban, Banknote, BarChart3, Bell,
  Bike, Bluetooth, BluetoothConnected, BluetoothSearching, Boxes, Calculator, Calendar, Car,
  Check, CheckCircle2, ChevronDown, ChevronLeft,
  ChevronRight, ChevronUp, CircleCheckBig, CircleDollarSign, ClipboardCheck, ClipboardList,
  ClipboardPen, Clock, Cog, Coins, CreditCard, Download, Eye, FileDown, FileSpreadsheet,
  FileText, Filter, Fuel, Gauge, Gift, HandCoins, History, Home, IdCard, Inbox, Info, KeyRound,
  Landmark, LayoutDashboard, ListChecks, ListOrdered, Loader2, LogOut, MapPin, Menu, Minus,
  MoreHorizontal, Package, PackageMinus, PackagePlus, Pencil, Phone, PieChart, Play, Plus,
  Printer, RefreshCw, RotateCcw, Save, ScrollText, Search, Send, Settings, Settings2,
  ShieldCheck, Sparkles, SquarePen, Star, Store, Table2, Thermometer, Trash2, TrendingDown,
  TrendingUp, TriangleAlert, Undo2, User, UserCog, UserPlus, Users, Wallet, Wrench, X, XCircle,
} from 'lucide-vue-next'

const ICONS = {
  alert: AlertTriangle, alertTriangle: AlertTriangle, arrowLeft: ArrowLeft, arrowRight: ArrowRight,
  bluetooth: Bluetooth, bluetoothTersambung: BluetoothConnected, bluetoothCari: BluetoothSearching,
  award: Award, badgeCheck: BadgeCheck, ban: Ban, banknote: Banknote, chart: BarChart3,
  bell: Bell, bike: Bike, boxes: Boxes, calculator: Calculator, calendar: Calendar, car: Car,
  check: Check, checkCircle: CheckCircle2, chevronDown: ChevronDown, chevronLeft: ChevronLeft,
  chevronRight: ChevronRight, chevronUp: ChevronUp, circleCheck: CircleCheckBig,
  money: CircleDollarSign, clipboardCheck: ClipboardCheck, clipboardList: ClipboardList,
  clipboardPen: ClipboardPen, clock: Clock, cog: Cog, coins: Coins, creditCard: CreditCard,
  download: Download, eye: Eye, fileDown: FileDown, fileExcel: FileSpreadsheet,
  fileText: FileText, filter: Filter, fuel: Fuel, gauge: Gauge, gift: Gift, handCoins: HandCoins,
  history: History, home: Home, idCard: IdCard, inbox: Inbox, info: Info, key: KeyRound,
  landmark: Landmark, dashboard: LayoutDashboard, listChecks: ListChecks, listOrdered: ListOrdered,
  loader: Loader2, logout: LogOut, mapPin: MapPin, menu: Menu, minus: Minus, more: MoreHorizontal,
  package: Package, packageMinus: PackageMinus, packagePlus: PackagePlus, pencil: Pencil,
  phone: Phone, pieChart: PieChart, play: Play, plus: Plus, printer: Printer, refresh: RefreshCw,
  rotateCcw: RotateCcw, save: Save, scrollText: ScrollText, search: Search, send: Send,
  settings: Settings, settings2: Settings2, shield: ShieldCheck, sparkles: Sparkles,
  squarePen: SquarePen, star: Star, store: Store, table: Table2, thermometer: Thermometer,
  trash: Trash2, trendingDown: TrendingDown, trendingUp: TrendingUp, warning: TriangleAlert,
  undo: Undo2, user: User, userCog: UserCog, userPlus: UserPlus, users: Users, wallet: Wallet,
  wrench: Wrench, x: X, xCircle: XCircle,
}

const props = defineProps({
  nama: { type: String, default: 'sparkles' },
  ukuran: { type: [Number, String], default: 18 },
  tebal: { type: [Number, String], default: 1.8 },
})

const komponen = ICONS[props.nama] || Sparkles
</script>

<template>
  <component :is="komponen" :size="ukuran" :stroke-width="tebal" aria-hidden="true" />
</template>