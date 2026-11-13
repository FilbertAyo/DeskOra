import { usePage } from '@inertiajs/react';

import type { SharedProps } from '@/types/inertia';

const Footer = () => {
    const { branding } = usePage<SharedProps>().props;

    return (
        <footer>
            <div className="text-muted-foreground mx-auto flex size-full max-w-360 items-center justify-between gap-3 px-4 py-3 max-sm:flex-col sm:gap-6 sm:px-6">
                <p className="text-sm text-balance max-sm:text-center">
                    {`©${new Date().getFullYear()}`} {branding.appName}
                    <span className="px-1.5">·</span>
                    Powered by {branding.productName}
                </p>
            </div>
        </footer>
    );
};

export default Footer;
