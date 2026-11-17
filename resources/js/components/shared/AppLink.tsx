import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { forwardRef } from 'react';

const AppLink = forwardRef<HTMLAnchorElement, InertiaLinkProps>(function AppLink(
    { prefetch = true, cacheFor = '60s', ...props },
    ref,
) {
    return <Link ref={ref} prefetch={prefetch} cacheFor={cacheFor} {...props} />;
});

export default AppLink;
