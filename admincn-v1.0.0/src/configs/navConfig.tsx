// Third-party Imports
import type * as Icon from 'lucide-react'

type IconName = keyof typeof Icon

export type MenuLeafSubItem = {
  label: string
  href: string
  activePath?: string
  badge?: string
  badgeClassName?: string
  target?: '_blank' | '_self' | '_parent' | '_top'
}

export type MenuGroupSubItem = {
  label: string
  childItems: MenuLeafSubItem[]
}

export type MenuSubItem = MenuLeafSubItem | MenuGroupSubItem

export type MenuItem = {
  icon: IconName
  label: string
} & (
  | {
      href: string
      badge?: string
      badgeClassName?: string
      childItems?: never
      target?: '_blank' | '_self' | '_parent' | '_top'
    }
  | {
      href?: never
      badge?: string
      badgeClassName?: string
      childItems: MenuSubItem[]
    }
)

export type NavItem = {
  groupLabel?: string
  items: MenuItem[]
}

export const navItems: NavItem[] = [
  {
    groupLabel: 'Dashboard & Layouts',
    items: [
      {
        icon: 'Package',
        label: 'Orders',
        href: '/dashboard/orders'
      },
      {
        icon: 'TrendingUp',
        label: 'Sales',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'Wallet',
        label: 'Finance',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'Truck',
        label: 'Logistics',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'Briefcase',
        label: 'Productivity',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'Megaphone',
        label: 'Campaign',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'BarChart3',
        label: 'Analytics',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'CreditCard',
        label: 'Payments',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'ShoppingCart',
        label: 'eCommerce',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'LayoutTemplate',
        label: 'Layouts',
        badge: 'Pro',
        badgeClassName: 'right-8',
        childItems: [
          {
            label: 'Full Navbar',
            href: '#!',
          },
          {
            label: 'Horizontal',
            href: '#!',
          },
          {
            label: 'Split',
            href: '#!',
          },
          {
            label: 'Icon Menu',
            href: '#!',
          },
          {
            label: 'Paper',
            href: '#!',
          }
        ]
      }
    ]
  },
  {
    groupLabel: 'Apps',
    items: [
      {
        icon: 'MailIcon',
        label: 'Mail',
        href: '/apps/mail'
      },
      {
        icon: 'CalendarIcon',
        label: 'Calendar',
        href: '/apps/calendar'
      },
      {
        icon: 'UsersIcon',
        label: 'Users',
        childItems: [
          { label: 'List', href: '/apps/users/list' },
          { label: 'View', href: '/apps/users/view' }
        ]
      },
      {
        icon: 'MessageCircleIcon',
        label: 'Chat',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'SquareKanbanIcon',
        label: 'Kanban',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'ContactIcon',
        label: 'Contact',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'ShieldCheckIcon',
        label: 'Roles & Permissions',
        badge: 'Pro',
        badgeClassName: 'right-8',
        childItems: [
          {
            label: 'Roles',
            href: '#!',
          },
          {
            label: 'Permissions',
            href: '#!',
          }
        ]
      }
    ]
  },
  {
    groupLabel: 'Pages',
    items: [
      {
        icon: 'UserCogIcon',
        label: 'User Settings',
        childItems: [
          {
            label: 'General',
            href: '/pages/user-settings?setting=general'
          },
          {
            label: 'Workspace',
            href: '/pages/user-settings?setting=workspace'
          },
          {
            label: 'Notifications',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Integrations',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Members',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Security',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Billing & Usage',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          }
        ]
      },
      {
        icon: 'UserIcon',
        label: 'User Profile',
        childItems: [
          {
            label: 'Profile',
            href: '/pages/user-profile?view=profile'
          },
          {
            label: 'Connections',
            href: '/pages/user-profile?view=connections'
          },
          {
            label: 'Teams',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Projects',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          }
        ]
      },
      {
        icon: 'LockKeyholeIcon',
        label: 'Authentication',
        childItems: [
          {
            label: 'Login',
            childItems: [
              { label: 'Login v1', href: '/pages/auth/login', target: '_blank' },
              {
                label: 'Login v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Login v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          },
          {
            label: 'Register',
            childItems: [
              { label: 'Register v1', href: '/pages/auth/register', target: '_blank' },
              {
                label: 'Register v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Register v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          },
          {
            label: 'Forgot Password',
            childItems: [
              { label: 'Forgot Password v1', href: '/pages/auth/forgot-password', target: '_blank' },
              {
                label: 'Forgot Password v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Forgot Password v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          },
          {
            label: 'Verify Email',
            childItems: [
              { label: 'Verify Email v1', href: '/pages/auth/verify-email', target: '_blank' },
              {
                label: 'Verify Email v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Verify Email v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          },
          {
            label: 'Reset Password',
            childItems: [
              { label: 'Reset Password v1', href: '/pages/auth/reset-password', target: '_blank' },
              {
                label: 'Reset Password v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Reset Password v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          },
          {
            label: 'Two Steps',
            childItems: [
              { label: 'Two Steps v1', href: '/pages/auth/two-steps', target: '_blank' },
              {
                label: 'Two Steps v2',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              },
              {
                label: 'Two Steps v3',
                href: '#!',
                badge: 'Pro',
                badgeClassName: 'right-8'
              }
            ]
          }
        ]
      },
      {
        icon: 'BugIcon',
        label: 'Error Pages',
        childItems: [
          { label: 'Error Page', href: '/pages/misc/error-page', target: '_blank' },
          {
            label: 'Error Page - 404',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Not Authorized - 401',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Forbidden - 403',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Server Error - 500',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          },
          {
            label: 'Under Maintenance',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          }
        ]
      },
      {
        icon: 'RocketIcon',
        label: 'Landing Page',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'DollarSignIcon',
        label: 'Pricing',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'CircleQuestionMarkIcon',
        label: 'FAQ',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'FootprintsIcon',
        label: 'Onboarding',
        badge: 'Pro',
        badgeClassName: 'right-8',
        childItems: [
          {
            label: 'Onboarding v1',
            href: '#!',
          },
          {
            label: 'Onboarding v2',
            href: '#!',
          }
        ]
      },
      {
        icon: 'FileIcon',
        label: 'Empty State',
        badge: 'Pro',
        badgeClassName: 'right-8',
        childItems: [
          {
            label: 'Empty State v1',
            href: '#!',
          },
          {
            label: 'Empty State v2',
            href: '#!',
          }
        ]
      }
    ]
  },
  {
    groupLabel: 'Forms & Tables',
    items: [
      {
        icon: 'LayoutTemplateIcon',
        label: 'Form Layouts',
        childItems: [
          { label: 'Vertical Layout', href: '/forms/form-layouts/vertical' },
          { label: 'Horizontal Layout', href: '/forms/form-layouts/horizontal' },
          {
            label: 'Sticky Actions',
            href: '#!',
            badge: 'Pro',
            badgeClassName: 'right-8'
          }
        ]
      },
      {
        icon: 'BadgeCheckIcon',
        label: 'Form Validation',
        href: '/forms/form-validation'
      },
      {
        icon: 'TableIcon',
        label: 'Data Table',
        href: '/datatable'
      },
      {
        icon: 'ListTodoIcon',
        label: 'Form Wizard',
        badge: 'Pro',
        badgeClassName: 'right-8',
        childItems: [
          {
            label: 'Icons',
            href: '#!',
          },
          {
            label: 'Numbered',
            href: '#!',
          }
        ]
      }
    ]
  },
  {
    groupLabel: 'Components & Charts',
    items: [
      {
        icon: 'LayoutGrid',
        label: 'Components',
        href: '#!',
      },
      {
        icon: 'LineChart',
        label: 'Charts',
        href: '#!',
      },
      {
        icon: 'ChartNoAxesColumnIncreasing',
        label: 'Statistics',
        href: '#!',
      },
      {
        icon: 'PanelTop',
        label: 'Card Nav',
        href: '#!',
        badge: 'Pro',
        badgeClassName: 'right-8'
      },
      {
        icon: 'Puzzle',
        label: 'Widgets',
        href: '#!',
      }
    ]
  },
  {
    groupLabel: 'Miscellaneous',
    items: [
      {
        icon: 'MenuIcon',
        label: 'Menu Level',
        childItems: [
          {
            label: 'Menu Item ',
            href: '#'
          },
          {
            label: 'Menu Level 1',
            childItems: [{ label: 'Menu Level 2', href: '#' }]
          }
        ]
      },
      {
        icon: 'InfoIcon',
        label: 'Support',
        href: '#!',
      },
      {
        icon: 'BookOpenTextIcon',
        label: 'Documentation',
        href: '#!',
      }
    ]
  }
]
