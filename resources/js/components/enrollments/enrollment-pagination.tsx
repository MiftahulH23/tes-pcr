import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PageMeta } from '@/types/enrollment';

const PAGE_SIZE_OPTIONS = [10, 20, 50, 100, 200];

interface EnrollmentPaginationProps {
    meta: PageMeta;
    onPageChange: (page: number) => void;
    onPageSizeChange: (pageSize: number) => void;
}

export function EnrollmentPagination({ meta, onPageChange, onPageSizeChange }: EnrollmentPaginationProps) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 py-3">
            <p className="text-muted-foreground text-sm">
                Total <span className="text-foreground font-medium">{meta.total.toLocaleString('id-ID')}</span> baris — halaman {meta.page} dari{' '}
                {Math.max(meta.last_page, 1)}
            </p>

            <div className="flex items-center gap-3">
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

                <div className="flex gap-1">
                    <Button variant="outline" size="sm" disabled={meta.page <= 1} onClick={() => onPageChange(meta.page - 1)}>
                        Sebelumnya
                    </Button>
                    <Button variant="outline" size="sm" disabled={meta.page >= meta.last_page} onClick={() => onPageChange(meta.page + 1)}>
                        Berikutnya
                    </Button>
                </div>
            </div>
        </div>
    );
}
