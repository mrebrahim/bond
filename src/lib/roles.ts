// إخفاء/إظهار حسب الدور للتجربة بس — الحماية الحقيقية في الـ RLS
export const ROLES = [
  'admin', 'account_manager', 'sales', 'support', 'finance',
  'studio', 'editor', 'designer', 'social_media', 'scriptwriter', 'ads', 'media_lead',
] as const;
export type Role = (typeof ROLES)[number];

export const ROLE_LABELS: Record<Role, string> = {
  admin: 'أدمن',
  account_manager: 'Account Manager',
  sales: 'سيلز',
  support: 'دعم فني',
  finance: 'فلوس',
  studio: 'استوديو',
  editor: 'مونتير',
  designer: 'ديزاينر',
  social_media: 'سوشيال ميديا',
  scriptwriter: 'سكربت',
  ads: 'إعلانات',
  media_lead: 'Media Lead',
};

export function hasRole(roles: readonly string[], allowed: readonly Role[]): boolean {
  return roles.includes('admin') || allowed.some((r) => roles.includes(r));
}

/** صلاحيات العمليات (نفس مصفوفة PRD §2) */
export const CAN = {
  editClient: ['sales', 'support', 'account_manager'],
  subscription: ['sales', 'account_manager'],
  cancelSubscription: ['account_manager'],
  payment: ['finance'],
  book: ['support', 'account_manager', 'studio', 'media_lead'],
  sessionOps: ['support', 'account_manager', 'studio', 'media_lead'],
  stage: ['account_manager', 'studio', 'editor', 'designer', 'social_media', 'media_lead'],
  scripts: ['support', 'account_manager', 'media_lead', 'scriptwriter'],
  complaint: ['sales', 'support', 'account_manager', 'media_lead'],
  power: ['support', 'studio', 'media_lead'],
  settings: [],
} satisfies Record<string, Role[]>;

export type NavItem = { href: string; label: string; icon: string; roles: Role[] | 'all' };

export const NAV: NavItem[] = [
  { href: '/dashboard', label: 'الداشبورد', icon: '📊', roles: [] },
  { href: '/inbox', label: 'التنبيهات', icon: '🔔', roles: 'all' },
  { href: '/clients', label: 'العملاء', icon: '👥', roles: ['sales', 'support', 'account_manager', 'finance', 'media_lead'] },
  { href: '/calendar', label: 'الكالندر', icon: '📅', roles: ['support', 'account_manager', 'studio', 'media_lead', 'sales'] },
  { href: '/today', label: 'الاستوديو النهارده', icon: '🎬', roles: ['studio', 'support', 'media_lead'] },
  { href: '/production', label: 'الإنتاج', icon: '🎞️', roles: ['studio', 'editor', 'designer', 'media_lead', 'account_manager', 'social_media'] },
  { href: '/content', label: 'بنك المحتوى', icon: '🗂️', roles: ['social_media', 'support', 'account_manager', 'media_lead'] },
  { href: '/scripts', label: 'السكربتات', icon: '📝', roles: ['scriptwriter', 'support', 'account_manager', 'media_lead'] },
  { href: '/finance', label: 'الفلوس', icon: '💰', roles: ['finance', 'sales', 'account_manager'] },
  { href: '/game', label: 'التحديات', icon: '🏆', roles: 'all' },
  { href: '/power', label: 'الكهرباء', icon: '⚡', roles: ['support', 'studio', 'media_lead'] },
  { href: '/reports', label: 'التقارير', icon: '📈', roles: [] },
  { href: '/settings', label: 'الإعدادات', icon: '⚙️', roles: [] },
];

export function navFor(roles: readonly string[]): NavItem[] {
  return NAV.filter((n) => n.roles === 'all' || hasRole(roles, n.roles));
}

/** الصفحة الرئيسية حسب الدور (PRD §5) */
export function homeFor(roles: readonly string[]): string {
  if (roles.includes('admin')) return '/dashboard';
  if (roles.includes('sales')) return '/inbox?filter=sales';
  if (roles.includes('support') || roles.includes('account_manager')) return '/calendar?view=week';
  if (roles.includes('media_lead')) return '/production';
  if (roles.includes('studio') && !roles.includes('editor')) return '/today';
  if (roles.some((r) => ['editor', 'designer'].includes(r))) return '/production?mine=1';
  if (roles.includes('social_media')) return '/content?view=today';
  if (roles.includes('scriptwriter')) return '/scripts';
  if (roles.includes('finance')) return '/finance';
  return '/inbox';
}
