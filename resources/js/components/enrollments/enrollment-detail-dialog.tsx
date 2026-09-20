import { SectionLabel } from '@/components/enrollments/section-label';
import { StatusBadge } from '@/components/enrollments/status-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { ApiError, apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { Enrollment, EnrollmentDetail } from '@/types/enrollment';
import { useEffect, useState, type ReactNode } from 'react';

type LoadState = 'loading' | 'ready' | 'notfound' | 'error';

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

function formatDateTime(iso: string): string {
    return `${dateTime.format(new Date(iso))} WIB`;
}

function Field({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <div className={cn('min-w-0', className)}>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium break-words">{children}</dd>
        </div>
    );
}

function DetailBody({ detail }: { detail: EnrollmentDetail }) {
    return (
        <>
            <SectionLabel>Data Mahasiswa</SectionLabel>
            <dl className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <Field label="NIM">{detail.student.nim}</Field>
                <Field label="Nama Mahasiswa">{detail.student.name}</Field>
                <Field label="Email Mahasiswa" className="sm:col-span-2">
                    {detail.student.email}
                </Field>
            </dl>

            <SectionLabel>Data Mata Kuliah</SectionLabel>
            <dl className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <Field label="Kode MK">{detail.course.code}</Field>
                <Field label="SKS">{detail.course.credits}</Field>
                <Field label="Nama Mata Kuliah" className="sm:col-span-2">
                    {detail.course.name}
                </Field>
            </dl>

            <SectionLabel>Data KRS</SectionLabel>
            <dl className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <Field label="ID KRS">{detail.id}</Field>
                <Field label="Status">
                    <StatusBadge status={detail.status} />
                </Field>
                <Field label="Tahun Ajaran">{detail.academic_year}</Field>
                <Field label="Semester">{detail.semester}</Field>
                <Field label="Dibuat">{formatDateTime(detail.created_at)}</Field>
                <Field label="Terakhir diperbarui">{formatDateTime(detail.updated_at)}</Field>
            </dl>
        </>
    );
}

function DetailSkeleton() {
    return (
        <div className="grid gap-4" role="status" aria-label="Memuat detail KRS">
            {[2, 3, 6].map((rows) => (
                <div key={rows} className="grid gap-3">
                    <Skeleton className="h-4 w-32" />
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {Array.from({ length: rows }, (_, i) => (
                            <div key={i} className="grid gap-1.5">
                                <Skeleton className="h-3 w-20" />
                                <Skeleton className="h-4 w-36" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

interface EnrollmentDetailDialogProps {
    enrollment: Enrollment | null;
    onOpenChange: (open: boolean) => void;
}

export function EnrollmentDetailDialog({ enrollment, onOpenChange }: EnrollmentDetailDialogProps) {
    const id = enrollment?.id;
    const [detail, setDetail] = useState<EnrollmentDetail | null>(null);
    const [state, setState] = useState<LoadState>('loading');
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (id === undefined) {
            return;
        }

        const controller = new AbortController();
        setDetail(null);
        setState('loading');

        apiFetch<{ data: EnrollmentDetail }>(route('enrollments.show', id), { signal: controller.signal })
            .then((response) => {
                setDetail(response.data);
                setState('ready');
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) {
                    return;
                }

                setState(error instanceof ApiError && error.status === 404 ? 'notfound' : 'error');
            });

        return () => controller.abort();
    }, [id, attempt]);

    // Right after switching rows the previous row's detail is still in state for one render; don't flash it.
    const view: LoadState = id !== undefined && state === 'ready' && detail?.id !== id ? 'loading' : state;

    return (
        <Dialog open={enrollment !== null} onOpenChange={onOpenChange}>
            <DialogContent className="gap-3 sm:max-w-xl" onOpenAutoFocus={(e) => e.preventDefault()}>
                <DialogHeader>
                    <DialogTitle>Detail KRS</DialogTitle>
                    <DialogDescription>Data lengkap mahasiswa, mata kuliah, dan KRS ini.</DialogDescription>
                </DialogHeader>

                <div className="grid max-h-[70vh] gap-3 overflow-y-auto px-1 py-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {view === 'loading' && <DetailSkeleton />}

                    {view === 'ready' && detail && <DetailBody detail={detail} />}

                    {(view === 'error' || view === 'notfound') && (
                        <div role="alert" className="border-border grid gap-3 rounded-lg border p-4 text-sm">
                            <p>{view === 'notfound' ? 'Data KRS tidak ditemukan — mungkin sudah dihapus.' : 'Gagal memuat detail KRS.'}</p>
                            {view === 'error' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="justify-self-start"
                                    onClick={() => setAttempt((n) => n + 1)}
                                >
                                    Coba lagi
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
