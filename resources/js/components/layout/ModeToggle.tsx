'use client'

// Third-party Imports
import { useTheme } from 'next-themes'
import { MoonStarIcon, SunIcon } from 'lucide-react'

// Component Imports
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const ModeToggle = ({ className }: { className?: string }) => {
  const { setTheme, resolvedTheme } = useTheme()

  const handleModeChange = () => {
    setTheme(resolvedTheme === 'dark' ? 'light' : 'dark')
  }

  return (
    <Button variant='outline' size='icon-sm' className={cn('relative rounded-full', className)} type='button' onClick={handleModeChange}>
      <MoonStarIcon className='scale-100 dark:scale-0' />
      <SunIcon className='absolute scale-0 dark:scale-100' />
      <span className='sr-only'>Toggle theme</span>
    </Button>
  )
}

export default ModeToggle
