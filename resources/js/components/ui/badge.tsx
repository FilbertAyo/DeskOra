import { mergeProps } from '@base-ui/react/merge-props'
import { useRender } from '@base-ui/react/use-render'
import { cva, type VariantProps } from 'class-variance-authority'

import { cn } from '@/lib/utils'

const badgeVariants = cva(
  'group/badge focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 inline-flex h-5 w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-4xl border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-all focus-visible:ring-[3px] has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&>svg]:pointer-events-none [&>svg]:size-3!',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground [a]:hover:bg-primary/80',
        secondary: 'bg-secondary text-secondary-foreground [a]:hover:bg-secondary/80',
        destructive:
          'bg-destructive/10 text-destructive focus-visible:ring-destructive/20 dark:bg-destructive/20 dark:focus-visible:ring-destructive/40 [a]:hover:bg-destructive/20',
        success:
          'bg-emerald-500/10 text-emerald-700 focus-visible:ring-emerald-500/20 dark:bg-emerald-500/20 dark:text-emerald-300 dark:focus-visible:ring-emerald-500/40 [a]:hover:bg-emerald-500/20',
        warning:
          'bg-amber-500/10 text-amber-800 focus-visible:ring-amber-500/20 dark:bg-amber-500/20 dark:text-amber-300 dark:focus-visible:ring-amber-500/40 [a]:hover:bg-amber-500/20',
        info: 'bg-sky-500/10 text-sky-800 focus-visible:ring-sky-500/20 dark:bg-sky-500/20 dark:text-sky-300 dark:focus-visible:ring-sky-500/40 [a]:hover:bg-sky-500/20',
        outline: 'border-border text-foreground [a]:hover:bg-muted [a]:hover:text-muted-foreground',
        ghost: 'hover:bg-muted hover:text-muted-foreground dark:hover:bg-muted/50',
        link: 'text-primary underline-offset-4 hover:underline'
      }
    },
    defaultVariants: {
      variant: 'default'
    }
  }
)

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>

const SUCCESS_STATUSES = new Set([
  'approved',
  'authorized',
  'available',
  'active',
  'completed',
  'done',
  'open',
  'paid',
  'resolved',
  'submitted',
  'success'
])

const WARNING_STATUSES = new Set([
  'draft',
  'in progress',
  'in_progress',
  'partially completed',
  'partially done',
  'pending',
  'resubmission',
  'review'
])

const DESTRUCTIVE_STATUSES = new Set([
  'cancelled',
  'canceled',
  'closed',
  'failed',
  'inactive',
  'not available',
  'not completed',
  'not done',
  'rejected',
  'suspended'
])

const INFO_STATUSES = new Set(['new', 'shared', 'info'])

function normalizeStatus(status: string): string {
  return status.trim().toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ')
}

/** Map common workflow statuses to alert-style badge variants (not brand/primary). */
function statusBadgeVariant(status: string): BadgeVariant {
  const key = normalizeStatus(status)

  if (
    SUCCESS_STATUSES.has(key) ||
    (key.includes('done') && !key.includes('partially') && !key.includes('not') && !key.includes('carry'))
  ) {
    return 'success'
  }

  if (DESTRUCTIVE_STATUSES.has(key) || key.includes('reject') || key.includes('fail')) {
    return 'destructive'
  }

  if (WARNING_STATUSES.has(key) || key.includes('partial') || key.includes('pending') || key.includes('carry')) {
    return 'warning'
  }

  if (INFO_STATUSES.has(key)) {
    return 'info'
  }

  return 'secondary'
}

function Badge({
  className,
  variant = 'default',
  render,
  ...props
}: useRender.ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: 'span',
    props: mergeProps<'span'>(
      {
        className: cn(badgeVariants({ variant }), className)
      },
      props
    ),
    render,
    state: {
      slot: 'badge',
      variant
    }
  })
}

export { Badge, badgeVariants, statusBadgeVariant }
export type { BadgeVariant }
