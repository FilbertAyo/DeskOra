import { Button as ButtonPrimitive } from '@base-ui/react/button'
import { router } from '@inertiajs/react'
import { cva, type VariantProps } from 'class-variance-authority'
import { Loader2Icon } from 'lucide-react'
import { useId, useSyncExternalStore } from 'react'

import { cn } from '@/lib/utils'

let pendingId: string | null = null
let activeRequests = 0
const pendingListeners = new Set<() => void>()

function subscribePending(listener: () => void) {
  pendingListeners.add(listener)
  return () => pendingListeners.delete(listener)
}

function getPendingId() {
  return pendingId
}

function setPendingId(id: string | null) {
  pendingId = id
  pendingListeners.forEach((listener) => listener())
}

function clearPending() {
  activeRequests = 0
  setPendingId(null)
}

if (typeof window !== 'undefined') {
  router.on('start', () => {
    activeRequests += 1
  })
  router.on('finish', clearPending)
  router.on('error', clearPending)
  router.on('networkError', clearPending)
  router.on('httpException', clearPending)
  router.on('cancel', clearPending)
}

const buttonVariants = cva(
  "group/button focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 inline-flex shrink-0 items-center justify-center rounded-md border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:ring-3 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:ring-3 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary/80',
        outline:
          'border-border bg-background hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50 shadow-xs',
        secondary:
          'bg-secondary text-secondary-foreground aria-expanded:bg-secondary aria-expanded:text-secondary-foreground hover:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)]',
        ghost:
          'hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:hover:bg-muted/50',
        destructive:
          'bg-destructive/10 text-destructive hover:bg-destructive/20 focus-visible:border-destructive/40 focus-visible:ring-destructive/20 dark:bg-destructive/20 dark:hover:bg-destructive/30 dark:focus-visible:ring-destructive/40',
        link: 'text-primary underline-offset-4 hover:underline'
      },
      size: {
        default:
          'h-9 gap-1.5 px-2.5 in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2',
        xs: "h-6 gap-1 rounded-[min(var(--radius-md),8px)] px-2 text-xs in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
        sm: 'h-8 gap-1 rounded-[min(var(--radius-md),10px)] px-2.5 in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5',
        lg: 'h-10 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2',
        icon: 'size-9',
        'icon-xs':
          "size-6 rounded-[min(var(--radius-md),8px)] in-data-[slot=button-group]:rounded-md [&_svg:not([class*='size-'])]:size-3",
        'icon-sm': 'size-8 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-md',
        'icon-lg': 'size-10'
      }
    },
    defaultVariants: {
      variant: 'default',
      size: 'default'
    }
  }
)

function Button({
  className,
  variant = 'default',
  size = 'default',
  type,
  disabled,
  loading = false,
  onClick,
  children,
  'data-action': dataAction,
  ...props
}: ButtonPrimitive.Props &
  VariantProps<typeof buttonVariants> & {
    loading?: boolean
    'data-action'?: string
  }) {
  const id = useId()
  const activeId = useSyncExternalStore(subscribePending, getPendingId, getPendingId)
  const pending = loading || activeId === id
  const tracksAction = type === 'submit' || dataAction != null

  return (
    <ButtonPrimitive
      data-slot='button'
      data-action={dataAction}
      type={type}
      disabled={disabled || pending}
      aria-busy={pending || undefined}
      className={cn(buttonVariants({ variant, size, className }))}
      onClick={(event) => {
        if (tracksAction && !disabled && !pending && !loading) {
          setPendingId(id)
          window.setTimeout(() => {
            if (getPendingId() === id && activeRequests === 0) {
              setPendingId(null)
            }
          }, 700)
          window.setTimeout(() => {
            if (getPendingId() === id) {
              clearPending()
            }
          }, 12000)
        }
        onClick?.(event)
      }}
      {...props}
    >
      {pending ? <Loader2Icon data-icon='inline-start' className='animate-spin' /> : null}
      {children}
    </ButtonPrimitive>
  )
}

export { Button, buttonVariants }
