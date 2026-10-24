export const STAGE_LABELS: Record<string, string> = {
  filmed: 'اتصور',
  editing: 'مونتاج',
  review: 'مراجعة',
  approved: 'اتوافق',
  published: 'اتنشر',
  delivered: 'اتسلم',
  brief: 'بريف',
  designing: 'تصميم',
};

export const SESSION_STATUS: Record<string, { label: string; cls: string }> = {
  booked: { label: 'محجوز', cls: 'bg-blue-100 text-blue-800 border-blue-200' },
  checked_in: { label: 'وصل', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
  done: { label: 'خلصت', cls: 'bg-slate-100 text-slate-700 border-slate-200' },
  no_show: { label: 'مجاش', cls: 'bg-red-100 text-red-800 border-red-200' },
  cancelled: { label: 'اتلغى', cls: 'bg-white text-slate-400 border-slate-200 line-through' },
};

export const CONFIRMATION: Record<string, { label: string; cls: string }> = {
  pending: { label: 'مستني تأكيد', cls: 'bg-amber-100 text-amber-800' },
  confirmed: { label: 'أكّد', cls: 'bg-green-100 text-green-800' },
  reschedule_requested: { label: 'عايز يغيّر', cls: 'bg-orange-100 text-orange-800' },
};

export const INVOICE_STATUS: Record<string, { label: string; cls: string }> = {
  unpaid: { label: 'مش مدفوعة', cls: 'bg-red-100 text-red-800' },
  partial: { label: 'مدفوعة جزئيًا', cls: 'bg-amber-100 text-amber-800' },
  paid: { label: 'مدفوعة', cls: 'bg-green-100 text-green-800' },
  void: { label: 'ملغية', cls: 'bg-slate-100 text-slate-500' },
};

export const INVOICE_KIND: Record<string, string> = {
  subscription: 'اشتراك',
  topup: 'Top-up',
  overage: 'ساعات زيادة',
  one_off: 'مرة واحدة',
  proration: 'فرق',
};

export const PAYMENT_METHODS: Record<string, string> = {
  cash: 'كاش',
  instapay: 'InstaPay',
  vodafone_cash: 'فودافون كاش',
  bank_transfer: 'تحويل بنكي',
  card: 'كارت',
  other: 'تاني',
};

export const SCRIPT_STATUS: Record<string, { label: string; cls: string }> = {
  draft: { label: 'مسودة', cls: 'bg-slate-100 text-slate-700' },
  internal_review: { label: 'مراجعة داخلية', cls: 'bg-blue-100 text-blue-800' },
  sent_to_client: { label: 'اتبعت للعميل', cls: 'bg-violet-100 text-violet-800' },
  changes_requested: { label: 'طلب تعديل', cls: 'bg-amber-100 text-amber-800' },
  approved: { label: 'اتوافق', cls: 'bg-green-100 text-green-800' },
};

export const CLIENT_STATUS: Record<string, string> = {
  lead: 'Lead',
  active: 'نشط',
  paused: 'متوقف',
  churned: 'مشي',
};

export const EVENT_LABELS: Record<string, string> = {
  new: 'اشتراك جديد',
  renewal: 'تجديد',
  upgrade: 'Upgrade',
  downgrade: 'Downgrade',
  topup: 'Top-up',
  pause: 'إيقاف',
  resume: 'رجوع',
  cancel: 'إلغاء',
  reactivate: 'رجع تاني',
  special_offer: 'عرض خاص',
};

export const ALERT_LABELS: Record<string, string> = {
  client_unhappy: '🔴 عميل متضايق',
  complaint_escalated: '🚨 شكوى محدش رد عليها',
  feedback_low: 'تقييم وحش على شغلك',
  revision_requested: 'العميل طالب تعديل',
  published: 'اتنشر — رسالة للعميل',
  session_done: 'الجلسة خلصت — رسالة للعميل',
  session_24h: 'تذكير الجلسة (بكرة)',
  session_2h: 'تذكير الجلسة (بعد ساعتين)',
  session_unconfirmed: 'العميل لسه مأكدش الجلسة',
  no_show: 'العميل مجاش',
  expiry_7d: 'الباقة بتخلص بعد 7 أيام',
  expiry_3d: 'الباقة بتخلص بعد 3 أيام',
  expiry_1d: 'الباقة بتخلص بكرة',
  expired_summary: 'الباقة خلصت',
  usage_high: 'استهلك 80%',
  usage_full_early: 'الرصيد خلص بدري — فرصة upgrade',
  hours_low: 'ساعات المدربين قربت تخلص',
  low_usage_mid: 'استهلاك ضعيف — خطر churn',
  content_runway_low: 'المحتوى قرب يخلص',
  slot_missed: 'ميعاد نشر فات من غير محتوى',
  slot_missed_internal: 'ميعاد نشر فات والمحتوى كان جاهز',
  payment_overdue: 'فلوس متأخرة',
  renewal_invoice: 'فاتورة التجديد',
  editing_late: 'ريل متأخر في المونتاج',
  power_low: '⚡ الكهرباء قربت تخلص',
  script_sent: 'سكربت اتبعت للعميل',
  reschedule_requested: 'العميل عايز يغيّر ميعاد الجلسة',
};

export const AUDIENCE_LABELS: Record<string, string> = {
  client: 'للعميل',
  account_manager: 'الـ AM',
  sales: 'السيلز',
  support: 'الدعم',
  admin: 'الأدمن',
  finance: 'الفلوس',
  media_lead: 'Media Lead',
  staff: 'ليك',
};

export const PLATFORMS: Record<string, string> = {
  facebook: 'فيسبوك',
  instagram: 'إنستجرام',
  tiktok: 'تيك توك',
  youtube: 'يوتيوب',
};

export const SHOOT_FREQUENCY: Record<string, string> = {
  weekly: 'كل أسبوع',
  biweekly: 'كل أسبوعين',
  monthly: 'كل شهر',
  on_demand: 'حسب الطلب',
};
