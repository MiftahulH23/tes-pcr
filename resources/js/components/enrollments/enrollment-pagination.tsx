import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PageMeta } from '@/types/enrollment';

const PAGE_SIZE_OPTIONS = [10, 20, 50, 100, 200];

interface EnrollmentPaginationProps {
    meta: PageMeta;
    onPageChange: (page: number) => void;
    onPageSizeChange: (pageSize: number) => void;
}

/** Page numbers with ellipsis around the current page — last_page can be in the hundreds of thousands at 5M+ rows. */
function pageNumbers(current: number, last: number): (number | 'ellipsis')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages: (number | 'ellipsis')[] = [1];

    if (current > 3) {
        pages.push('ellipsis');
    }

    for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
        pages.push(i);
    }

    if (current < last - 2) {
        pages.push('ellipsis');
    }

    pages.push(last);

    return pages;
}

export function EnrollmentPagination({ meta, onPageChange, onPageSizeChange }: EnrollmentPaginationProps) {
    const lastPage = Math.max(meta.last_page, 1);
    const goTo = (page: number) => (e: React.MouseEvent) => {
        e.preventDefault();
        onPageChange(page);
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-4 py-3">
            <p className="text-muted-foreground text-sm">
                Total <span className="text-foreground font-medium">{meta.total.toLocaleString('id-ID')}</span> baris
            </p>

            <Pagination className="mx-0 w-auto">
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious
                            href="#"
                            onClick={goTo(Math.max(1, meta.page - 1))}
                            aria-disabled={meta.page <= 1}
                            className={meta.page <= 1 ? 'pointer-events-none opacity-50' : ''}
                        />
                    </PaginationItem>

                    <PaginationItem className="sm:hidden">
                        <span className="text-muted-foreground px-2 text-sm whitespace-nowrap">
                            Hal {meta.page.toLocaleString('id-ID')} / {lastPage.toLocaleString('id-ID')}
                        </span>
                    </PaginationItem>

                    {pageNumbers(meta.page, lastPage).map((page, index) =>
                        page === 'ellipsis' ? (
                            <PaginationItem key={`ellipsis-${index}`} className="hidden sm:block">
                                <PaginationEllipsis />
                            </PaginationItem>
                        ) : (
                            <PaginationItem key={page} className="hidden sm:block">
                                <PaginationLink href="#" isActive={page === meta.page} onClick={goTo(page)}>
                                    {page}
                                </PaginationLink>
                            </PaginationItem>
                        ),
                    )}

                    <PaginationItem>
                        <PaginationNext
                            href="#"
                            onClick={goTo(Math.min(lastPage, meta.page + 1))}
                            aria-disabled={meta.page >= lastPage}
                            className={meta.page >= lastPage ? 'pointer-events-none opacity-50' : ''}
                        />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>

            <div className="flex items-center gap-2">
                <span className="text-muted-foreground text-sm">Baris/halaman</span>
                <Select value={String(meta.page_size)} onValueChange={(v) => onPageSizeChange(Number(v))}>
                    <SelectTrigger className="w-20">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PAGE_SIZE_OPTIONS.map((size) => (
                            <SelectItem key={size} value={String(size)}>
                                {size}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
