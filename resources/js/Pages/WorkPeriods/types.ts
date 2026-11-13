export type Period = {
    id: string;
    year: number;
    weekNumber: number;
    dateRange: string;
    weekStartDate: string;
    planDeadlineDate: string;
    reportDeadlineDate: string;
    planDeadline: string;
    reportDeadline: string;
    status: 'open' | 'closed' | 'archived';
    planDeadlineState: string;
    reportDeadlineState: string;
    showUrl: string;
    editUrl: string;
    closeUrl: string;
};

export type NextPeriod = { year: number; weekNumber: number; weekStartDate: string };
